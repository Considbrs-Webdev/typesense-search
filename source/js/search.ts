// ---------------------------------------------------------------------------
// Search execution
// ---------------------------------------------------------------------------

import { Client as TypesenseClient } from "typesense";
import type { SearchResponse } from "typesense";
import type { HitDocument, SearchHit } from "./types";
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
  return sort;
}

export async function runSearch(
  client: TypesenseClient,
  collection: string,
  state: UrlState,
  resultsEl: HTMLElement,
  templates: Map<string, string>,
  paginationEl: HTMLElement | null,
  onPageChange: (page: number) => void,
): Promise<void> {
  if (!state.query.trim()) {
    resultsEl.innerHTML = "";
    return;
  }

  try {
    const filterBy = buildFilterBy(state.facetFilters);
    const sortBy = buildSortBy(state.sort);

    const response = (await client
      .collections(collection)
      .documents()
      .search({
        q: state.query,
        query_by: "title,excerpt,content",
        highlight_full_fields: "title,excerpt,content",
        per_page: 20,
        page: state.page,
        ...(filterBy ? { filter_by: filterBy } : {}),
        ...(sortBy ? { sort_by: sortBy } : {}),
      })) as SearchResponse<HitDocument>;

    const hits = (response.hits ?? []) as SearchHit[];

    if (hits.length === 0) {
      resultsEl.innerHTML = `<p class="ts-no-results">No results found.</p>`;
      if (paginationEl) paginationEl.innerHTML = "";
      return;
    }

    resultsEl.innerHTML = hits.map((hit) => renderHit(hit, templates)).join("");

    if (paginationEl) {
      renderPagination(
        paginationEl,
        response.found ?? 0,
        response.request_params?.per_page ?? 20,
        state.page,
        onPageChange,
      );
    }
  } catch (err) {
    console.error("[TypesenseSearch] Search error:", err);
    resultsEl.innerHTML = `<p class="ts-search-error">Search failed. Please try again.</p>`;
    if (paginationEl) paginationEl.innerHTML = "";
  }
}
