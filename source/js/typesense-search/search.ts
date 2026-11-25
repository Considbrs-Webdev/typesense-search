// ---------------------------------------------------------------------------
// Search execution
// ---------------------------------------------------------------------------

import { Client as TypesenseClient } from "typesense";
import type {
  FacetData,
  TypesenseSearchConfig,
} from "./types";
import type { UrlState } from "./url-state";
import { renderHit } from "./templates";
import { renderPagination } from "./pagination";
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
    if (paginationEl) paginationEl.innerHTML = "";
    const resultsContainer = resultsEl.closest<HTMLElement>(
      "[data-js-search-results-container]",
    );
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

    const mainSearchPromise = shouldSearch
      ? executeDocumentSearch(client, config, {
          q,
          hasQuery,
          mode: state.mode,
          page: state.page,
          perPage: config.hitsPerPage,
          filterBy: filterBy || undefined,
          sortBy,
          highlightFullFields: "title,excerpt,content",
        })
      : Promise.resolve(null);

    const facetsPromise = executeDisjunctiveFacets(client, config, {
      q,
      hasQuery,
      mode: state.mode,
      facetFields,
      facetFilters: state.facetFilters,
      buildFilterBy,
    });

    const [response, facetData] = await Promise.all([
      mainSearchPromise,
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
      const hits = response.hits;
      if (hasQuery) {
        onSearchComplete?.(state.query, response.found);
      }

      const resultsContainer = resultsEl.closest<HTMLElement>(
        "[data-js-search-results-container]",
      );
      const noHitsContainer =
        resultsContainer?.parentElement?.querySelector<HTMLElement>(
          "[data-js-no-hits-container]",
        );

      if (hits.length === 0) {
        resultsEl.innerHTML = "";
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
        resultsEl.innerHTML = hits
          .map(
            (hit) =>
              `<li class="ts-search-results__item">${renderHit(hit, templates)}</li>`,
          )
          .join("");

        if (paginationEl) {
          renderPagination(
            paginationEl,
            response.found,
            response.perPage || config.hitsPerPage || 20,
            state.page,
            onPageChange,
          );
        }

        if (noHitsContainer) noHitsContainer.style.display = "none";
        if (resultsContainer) {
          resultsContainer.style.display = "";
          const countEl = resultsContainer.querySelector<HTMLElement>(
            "[data-js-search-results-count]",
          );
          if (countEl) {
            const found = response.found ?? hits.length;
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
        }
      }
    }

    return facetData;
  } catch (err) {
    console.error("[TypesenseSearch] Search error:", err);
    const errorMsg =
      window.typesenseI18n?.searchError ?? "Search failed. Please try again.";
    resultsEl.innerHTML = `<li class="ts-search-results__item" role="alert"><p class="ts-search-error">${errorMsg}</p></li>`;
    if (paginationEl) paginationEl.innerHTML = "";
    return null;
  }
}
