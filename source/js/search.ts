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

    // Filters for each individual facet dimension (used for disjunctive queries).
    const filterByTopMostParent = buildFilterBy({
      top_most_parent: state.facetFilters.top_most_parent ?? [],
    });
    const filterByTypeName = buildFilterBy({
      type_name: state.facetFilters.type_name ?? [],
    });

    // Run main search + two disjunctive facet queries in parallel.
    // • top_most_parent options → filtered by type_name only (no top_most_parent filter)
    // • type_name options        → filtered by top_most_parent only (no type_name filter)
    // When there is no real query and no active facets we skip the main search
    // (results stay empty) but still fetch facets using q:* so the selects are
    // populated on page load.
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

    const [response, topMostParentRes, typeNameRes] = await Promise.all([
      mainSearchPromise,

      client
        .collections(collection)
        .documents()
        .search({
          q,
          query_by: "title,excerpt,content",
          per_page: 0,
          facet_by: "top_most_parent",
          max_facet_values: 200,
          ...(filterByTypeName ? { filter_by: filterByTypeName } : {}),
        }) as Promise<SearchResponse<HitDocument>>,

      client
        .collections(collection)
        .documents()
        .search({
          q,
          query_by: "title,excerpt,content",
          per_page: 0,
          facet_by: "type_name",
          max_facet_values: 100,
          ...(filterByTopMostParent
            ? { filter_by: filterByTopMostParent }
            : {}),
        }) as Promise<SearchResponse<HitDocument>>,
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

    return {
      top_most_parent: extractFacetCounts(topMostParentRes, "top_most_parent"),
      type_name: extractFacetCounts(typeNameRes, "type_name"),
    };
  } catch (err) {
    console.error("[TypesenseSearch] Search error:", err);
    resultsEl.innerHTML = `<p class="ts-search-error">Search failed. Please try again.</p>`;
    if (paginationEl) paginationEl.innerHTML = "";
    return null;
  }
}
