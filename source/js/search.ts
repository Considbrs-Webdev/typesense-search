// ---------------------------------------------------------------------------
// Search execution
// ---------------------------------------------------------------------------

import { Client as TypesenseClient } from "typesense";
import type { SearchResponse } from "typesense";
import type {
  HitDocument,
  SearchHit,
  FacetCount,
  FacetData,
  TypesenseSearchConfig,
} from "./types";
import type { UrlState } from "./url-state";
import { renderHit } from "./templates";
import { renderPagination } from "./pagination";

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

function extractFacetCounts(
  response: SearchResponse<HitDocument>,
  field: string,
): FacetCount[] {
  const facetResult = response.facet_counts?.find(
    (f) => f.field_name === field,
  );
  return (facetResult?.counts ?? []).map((c) => ({
    value: String(c.value),
    count: c.count,
  }));
}

export function createSearchRunner(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  resultsEl: HTMLElement,
  templates: Map<string, string>,
  paginationEl: HTMLElement | null,
  facetFields: string[],
  onPageChange: (page: number) => void,
): (state: UrlState) => Promise<FacetData | null> {
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

    // Main search promise.
    const mainSearchPromise = shouldSearch
      ? (client
          .collections(config.collection)
          .documents()
          .search({
            q,
            query_by: "title,excerpt,content,extra_terms,type_name",
            infix: "always,off,off,always,off",
            highlight_full_fields: "title,excerpt,content",
            highlight_affix_num_tokens: config.highlightAffixNumTokens ?? 15,
            per_page: config.hitsPerPage,
            page: state.page,
            ...(filterBy ? { filter_by: filterBy } : {}),
            ...(sortBy ? { sort_by: sortBy } : {}),
          }) as Promise<SearchResponse<HitDocument>>)
      : Promise.resolve(null);

    // For each facet field, run a disjunctive query:
    // Filter by all OTHER active facets (not this field) so the available
    // options for this facet are never constrained by its own selection.
    const facetPromises = facetFields.map((field) => {
      const otherFilters = Object.fromEntries(
        Object.entries(state.facetFilters).filter(([k]) => k !== field),
      );
      const filterByOthers = buildFilterBy(otherFilters);

      return client
        .collections(config.collection)
        .documents()
        .search({
          q,
          query_by: "title,excerpt,content,extra_terms,type_name",
          per_page: 0,
          facet_by: field,
          max_facet_values: 200,
          ...(filterByOthers ? { filter_by: filterByOthers } : {}),
        }) as Promise<SearchResponse<HitDocument>>;
    });

    const [response, ...facetResponses] = await Promise.all([
      mainSearchPromise,
      ...facetPromises,
    ]);

    if (shouldSearch && response) {
      const hits = (response.hits ?? []) as SearchHit[];

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
          .map((hit) => renderHit(hit, templates))
          .join("");

        if (paginationEl) {
          renderPagination(
            paginationEl,
            response.found ?? 0,
            response.request_params?.per_page ?? 20,
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

    // Build the result map: field → FacetCount[]
    const facetData: FacetData = {};
    facetFields.forEach((field, i) => {
      facetData[field] = extractFacetCounts(facetResponses[i], field);
    });

    return facetData;
  } catch (err) {
    console.error("[TypesenseSearch] Search error:", err);
    const errorMsg =
      window.typesenseI18n?.searchError ?? "Search failed. Please try again.";
    resultsEl.innerHTML = `<p class="ts-search-error">${errorMsg}</p>`;
    if (paginationEl) paginationEl.innerHTML = "";
    return null;
  }
}
