// ---------------------------------------------------------------------------
// Search execution
// ---------------------------------------------------------------------------

import { Client as TypesenseClient } from "typesense";
import type {
  FacetData,
  QueryExecutionMode,
  SearchHit,
  TypesenseSearchConfig,
} from "./types";
import type { UrlState } from "./url-state";
import { renderHit } from "./templates";
import { renderSpotlightCard } from "./html";
import { renderPagination } from "./pagination";
import { getActiveStrategy } from "./search-params";
import {
  executeDisjunctiveFacets,
  executeDocumentSearch,
} from "./multi-search";

function buildFilterBy(facetFilters: Record<string, string[]>): string {
  return Object.entries(facetFilters)
    .filter(([, values]) => values.length > 0)
    .map(
      ([attr, values]) =>
        `${attr}:=[${values.map((v) => `\`${v}\``).join(",")}]`,
    )
    .join(" && ");
}

function buildSortBy(sort: string): string | undefined {
  if (!sort || sort === "relevance") return undefined;
  if (sort === "dateDesc") return "date:desc";
  if (sort === "dateAsc") return "date:asc";
  return undefined;
}

function getHitIdentityKey(hit: SearchHit): string {
  const d = hit.document;
  const link = String(d.permalink ?? d.url ?? "").trim();
  if (link) {
    return link.replace(/\/+$/, "");
  }
  return `${String(d._ts_site_name ?? "")}:${String(d.id ?? "")}`;
}

function resetAuxiliarySections(resultsContainer: HTMLElement | null): void {
  if (!resultsContainer) return;

  const spotlightSection = resultsContainer.querySelector<HTMLElement>(
    "[data-js-search-spotlight]",
  );
  const spotlightList = resultsContainer.querySelector<HTMLElement>(
    "[data-js-search-spotlight-list]",
  );
  if (spotlightList) spotlightList.innerHTML = "";
  if (spotlightSection) spotlightSection.hidden = true;

  const rescueSection = resultsContainer.querySelector<HTMLElement>(
    "[data-js-search-rescue]",
  );
  const rescueList = resultsContainer.querySelector<HTMLElement>(
    "[data-js-search-rescue-list]",
  );
  if (rescueList) rescueList.innerHTML = "";
  if (rescueSection) rescueSection.hidden = true;
}

function updateCountElement(
  resultsContainer: HTMLElement | null,
  found: number,
): void {
  if (!resultsContainer) return;
  const countEl = resultsContainer.querySelector<HTMLElement>(
    "[data-js-search-results-count]",
  );
  if (!countEl) return;

  const singular =
    countEl.getAttribute("data-lang-singular") ??
    window.typesenseI18n?.resultSingular ??
    "%d result";
  const plural =
    countEl.getAttribute("data-lang-plural") ??
    window.typesenseI18n?.resultPlural ??
    "%d results";
  const template = found === 1 ? singular : plural;
  countEl.textContent = template.replace("%d", String(found));
}

export function createSearchRunner(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  resultsEl: HTMLElement,
  templates: Map<string, string>,
  paginationEl: HTMLElement | null,
  facetFields: string[],
  onPageChange: (page: number) => void,
  onSearchComplete?: (query: string, found: number) => void,
): (state: UrlState) => Promise<FacetData | null> {
  let latestRequestId = 0;

  return (state: UrlState) =>
    runSearch(
      client,
      config,
      state,
      resultsEl,
      templates,
      paginationEl,
      facetFields,
      onPageChange,
      onSearchComplete,
      ++latestRequestId,
      () => latestRequestId,
    );
}

export async function runSearch(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  state: UrlState,
  resultsEl: HTMLElement,
  templates: Map<string, string>,
  paginationEl: HTMLElement | null,
  facetFields: string[],
  onPageChange: (page: number) => void,
  onSearchComplete?: (query: string, found: number) => void,
  requestId?: number,
  getLatestRequestId?: () => number,
): Promise<FacetData | null> {
  const q = state.query.trim() || "*";
  const hasQuery = !!state.query.trim();
  const hasFacets = Object.values(state.facetFilters).some(
    (values) => values.length > 0,
  );
  // Run (and render) the main search when there is either a text query or
  // at least one active facet filter.
  const shouldSearch = hasQuery || hasFacets;

  // Clear results only when there is truly nothing to search for.
  if (!shouldSearch) {
    resultsEl.innerHTML = "";
    resultsEl.hidden = false;
    if (paginationEl) paginationEl.innerHTML = "";
    const resultsContainer = resultsEl.closest<HTMLElement>(
      "[data-js-search-results-container]",
    );
    resetAuxiliarySections(resultsContainer);
    const noHitsContainer =
      resultsContainer?.parentElement?.querySelector<HTMLElement>(
        "[data-js-no-hits-container]",
      );
    if (resultsContainer) {
      resultsContainer.style.display = "none";
      const countEl = resultsContainer.querySelector<HTMLElement>(
        "[data-js-search-results-count]",
      );
      if (countEl) countEl.textContent = "";
    }
    if (noHitsContainer) noHitsContainer.style.display = "none";
  }

  try {
    const filterBy = buildFilterBy(state.facetFilters);
    const sortBy = buildSortBy(state.sort);
    const strategy = getActiveStrategy(config);
    const isFirstRelevancePage =
      hasQuery && state.page === 1 && (!state.sort || state.sort === "relevance");

    const mainMode: QueryExecutionMode =
      strategy === "blended" ? "hybrid" : "keyword";

    let auxMode: QueryExecutionMode | null = null;
    if (shouldSearch && isFirstRelevancePage) {
      if (strategy === "parallel") {
        auxMode = "spotlight";
      } else if (strategy === "rescue") {
        auxMode = "semantic";
      }
    }

    const auxPerPage =
      auxMode === "spotlight"
        ? Math.max(6, (config.vectorSearch?.spotlightMaxHits ?? 3) * 2)
        : Math.max(8, (config.vectorSearch?.spotlightMaxHits ?? 3) + 5);

    const mainSearchPromise = shouldSearch
      ? executeDocumentSearch(client, config, {
          q,
          hasQuery,
          mode: mainMode,
          page: state.page,
          perPage: config.hitsPerPage,
          filterBy: filterBy || undefined,
          sortBy,
          highlightFullFields: "title,excerpt,content",
        })
      : Promise.resolve(null);

    const auxSearchPromise = auxMode
      ? executeDocumentSearch(client, config, {
          q,
          hasQuery: true,
          mode: auxMode,
          page: 1,
          perPage: auxPerPage,
          filterBy: filterBy || undefined,
          highlightFullFields: "title,excerpt,content",
          allowLexicalFallback: false,
        }).catch((err) => {
          console.warn(
            "[TypesenseSearch] Auxiliary semantic search skipped:",
            err,
          );
          return null;
        })
      : Promise.resolve(null);

    const facetsPromise = executeDisjunctiveFacets(client, config, {
      q,
      hasQuery,
      mode: mainMode,
      facetFields,
      facetFilters: state.facetFilters,
      buildFilterBy,
    });

    const [response, auxResponse, facetData] = await Promise.all([
      mainSearchPromise,
      auxSearchPromise,
      facetsPromise,
    ]);

    if (
      requestId !== undefined &&
      getLatestRequestId &&
      requestId !== getLatestRequestId()
    ) {
      return null;
    }

    if (shouldSearch && response) {
      const resultsContainer = resultsEl.closest<HTMLElement>(
        "[data-js-search-results-container]",
      );
      const noHitsContainer =
        resultsContainer?.parentElement?.querySelector<HTMLElement>(
          "[data-js-no-hits-container]",
        );

      resetAuxiliarySections(resultsContainer);

      let mainHitsToRender = response.hits;
      let spotlightShownCount = 0;
      let rescueShownCount = 0;

      // 1. Parallel strategy: Top Matches Spotlight box above keyword hits
      if (
        strategy === "parallel" &&
        isFirstRelevancePage &&
        auxResponse &&
        auxResponse.hits.length > 0 &&
        resultsContainer
      ) {
        const wordCount = state.query
          .trim()
          .split(/\s+/)
          .filter(Boolean).length;
        const minWords = config.vectorSearch?.spotlightMinWords ?? 2;
        const maxSpotlight = config.vectorSearch?.spotlightMaxHits ?? 3;
        const shouldDedupe = config.vectorSearch?.spotlightDedupe !== false;

        if (wordCount >= minWords || response.found === 0) {
          const spotlightHits = auxResponse.hits.slice(0, maxSpotlight);
          const spotlightSection = resultsContainer.querySelector<HTMLElement>(
            "[data-js-search-spotlight]",
          );
          const spotlightList = resultsContainer.querySelector<HTMLElement>(
            "[data-js-search-spotlight-list]",
          );

          if (spotlightSection && spotlightList && spotlightHits.length > 0) {
            spotlightList.innerHTML = spotlightHits
              .map(
                (hit) =>
                  `<li class="ts-spotlight__item">${renderSpotlightCard(hit)}</li>`,
              )
              .join("");
            spotlightSection.hidden = false;
            spotlightShownCount = spotlightHits.length;

            if (shouldDedupe) {
              const spotlightKeys = new Set(
                spotlightHits.map((hit) => getHitIdentityKey(hit)),
              );
              mainHitsToRender = response.hits.filter(
                (hit) => !spotlightKeys.has(getHitIdentityKey(hit)),
              );
            }
          }
        }
      }

      // 2. Rescue strategy: Semantic Rescue section when exact keyword hits are zero or sparse
      if (
        strategy === "rescue" &&
        isFirstRelevancePage &&
        auxResponse &&
        auxResponse.hits.length > 0 &&
        resultsContainer
      ) {
        const maxKeywordHits = config.vectorSearch?.rescueMaxKeywordHits ?? 2;
        const maxRescueHits = Math.max(
          3,
          config.vectorSearch?.spotlightMaxHits ?? 3,
        );

        if (response.found <= maxKeywordHits) {
          const mainKeys = new Set(
            response.hits.map((hit) => getHitIdentityKey(hit)),
          );
          const rescueHits = auxResponse.hits
            .filter((hit) => !mainKeys.has(getHitIdentityKey(hit)))
            .slice(0, maxRescueHits);

          const rescueSection = resultsContainer.querySelector<HTMLElement>(
            "[data-js-search-rescue]",
          );
          const rescueList = resultsContainer.querySelector<HTMLElement>(
            "[data-js-search-rescue-list]",
          );
          const rescueHeadingEl = resultsContainer.querySelector<HTMLElement>(
            "[data-js-search-rescue-heading]",
          );

          if (rescueSection && rescueList && rescueHits.length > 0) {
            if (rescueHeadingEl) {
              const zeroHeading =
                rescueHeadingEl.getAttribute("data-lang-zero") ??
                window.typesenseI18n?.rescueZeroHitsHeading ??
                "No exact word matches — showing related pages";
              const defaultHeading =
                rescueHeadingEl.getAttribute("data-lang-default") ??
                window.typesenseI18n?.rescueHeading ??
                "Related pages";
              rescueHeadingEl.textContent =
                response.found === 0 ? zeroHeading : defaultHeading;
            }

            rescueList.innerHTML = rescueHits
              .map((hit) => {
                const flaggedHit: SearchHit = {
                  ...hit,
                  document: { ...hit.document, _ts_semantic_only: true },
                };
                return `<li class="ts-search-results__item">${renderHit(flaggedHit, templates)}</li>`;
              })
              .join("");
            rescueSection.hidden = false;
            rescueShownCount = rescueHits.length;
          }
        }
      }

      const auxiliaryCount = spotlightShownCount + rescueShownCount;
      const effectiveFound =
        response.found > 0 ? response.found : auxiliaryCount;

      if (hasQuery) {
        onSearchComplete?.(state.query, effectiveFound);
      }

      if (response.hits.length === 0 && auxiliaryCount === 0) {
        resultsEl.innerHTML = "";
        resultsEl.hidden = false;
        if (resultsContainer) {
          resultsContainer.style.display = "none";
          const countEl = resultsContainer.querySelector<HTMLElement>(
            "[data-js-search-results-count]",
          );
          if (countEl) countEl.textContent = "";
        }
        if (noHitsContainer) noHitsContainer.style.display = "";
        if (paginationEl) paginationEl.innerHTML = "";
      } else {
        if (mainHitsToRender.length > 0) {
          resultsEl.innerHTML = mainHitsToRender
            .map(
              (hit) =>
                `<li class="ts-search-results__item">${renderHit(hit, templates)}</li>`,
            )
            .join("");
          resultsEl.hidden = false;
        } else {
          resultsEl.innerHTML = "";
          resultsEl.hidden = true;
        }

        if (paginationEl) {
          if (response.found > 0) {
            renderPagination(
              paginationEl,
              response.found,
              response.perPage || config.hitsPerPage || 20,
              state.page,
              onPageChange,
            );
          } else {
            paginationEl.innerHTML = "";
          }
        }

        if (noHitsContainer) noHitsContainer.style.display = "none";
        if (resultsContainer) {
          resultsContainer.style.display = "";
          updateCountElement(resultsContainer, effectiveFound);
        }
      }
    }

    return facetData;
  } catch (err) {
    console.error("[TypesenseSearch] Search error:", err);
    const errorMsg =
      window.typesenseI18n?.searchError ?? "Search failed. Please try again.";
    resultsEl.hidden = false;
    resultsEl.innerHTML = `<li class="ts-search-results__item" role="alert"><p class="ts-search-error">${errorMsg}</p></li>`;
    if (paginationEl) paginationEl.innerHTML = "";
    return null;
  }
}
