// ---------------------------------------------------------------------------
// Search execution
// ---------------------------------------------------------------------------

import { Client as TypesenseClient } from "typesense";
import type { SearchResponse } from "typesense";
import type { HitDocument, SearchHit, FacetCount, FacetData } from "./types";
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

export async function runSearch(
  client: TypesenseClient,
  collection: string,
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
  }

  try {
    const filterBy = buildFilterBy(state.facetFilters);
    const sortBy = buildSortBy(state.sort);

    // Main search promise.
    const mainSearchPromise = shouldSearch
      ? (client
          .collections(collection)
          .documents()
          .search({
            q,
            query_by: "title,excerpt,content",
            highlight_full_fields: "title,excerpt,content",
            per_page: 20,
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
        .collections(collection)
        .documents()
        .search({
          q,
          query_by: "title,excerpt,content",
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

      if (hits.length === 0) {
        resultsEl.innerHTML = `<p class="ts-no-results">No results found.</p>`;
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
    resultsEl.innerHTML = `<p class="ts-search-error">Search failed. Please try again.</p>`;
    if (paginationEl) paginationEl.innerHTML = "";
    return null;
  }
}
