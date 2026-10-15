// ---------------------------------------------------------------------------
// URL state – source of truth for all search parameters.
// Adapted from municipio-search/url-state.ts.
// ---------------------------------------------------------------------------

export type SearchMode = "keyword" | "hybrid" | "semantic";

export const DEFAULT_SEARCH_MODE: SearchMode = "hybrid";

export interface UrlState {
  query: string;
  page: number;
  sort: string;
  mode: SearchMode;
  facetFilters: Record<string, string[]>;
}

const SORT_KEY = "sort";
const MODE_KEY = "mode";
const PAGE_KEY = "page";
const FACET_PREFIX = "facet_";

function parseSearchMode(raw: string | null): SearchMode {
  if (raw === "keyword" || raw === "hybrid" || raw === "semantic") {
    return raw;
  }
  return DEFAULT_SEARCH_MODE;
}

export function getUrlState(): UrlState {
  const params = new URLSearchParams(window.location.search);

  const query = params.get("s") ?? params.get("q") ?? "";
  const page = Math.max(1, parseInt(params.get(PAGE_KEY) ?? "1", 10) || 1);
  const sort = params.get(SORT_KEY) ?? "relevance";
  const mode = parseSearchMode(params.get(MODE_KEY));

  const facetFilters: Record<string, string[]> = {};
  params.forEach((value, key) => {
    if (key.startsWith(FACET_PREFIX)) {
      const attr = key.slice(FACET_PREFIX.length);
      if (!facetFilters[attr]) facetFilters[attr] = [];
      facetFilters[attr].push(value);
    }
  });

  return { query, page, sort, mode, facetFilters };
}

export function updateUrlState(
  state: Partial<UrlState>,
  { push = false }: { push?: boolean } = {},
): void {
  const current = getUrlState();
  const merged: UrlState = {
    query: state.query !== undefined ? state.query : current.query,
    page: state.page !== undefined ? state.page : current.page,
    sort: state.sort !== undefined ? state.sort : current.sort,
    mode: state.mode !== undefined ? state.mode : current.mode,
    facetFilters:
      state.facetFilters !== undefined
        ? state.facetFilters
        : current.facetFilters,
  };

  const params = new URLSearchParams();

  // Always keep "s" so WordPress recognises this as a search page,
  // even when the query is empty.
  params.set("s", merged.query);

  if (merged.page > 1) params.set(PAGE_KEY, String(merged.page));

  if (merged.sort && merged.sort !== "relevance")
    params.set(SORT_KEY, merged.sort);

  if (merged.mode && merged.mode !== DEFAULT_SEARCH_MODE)
    params.set(MODE_KEY, merged.mode);

  Object.entries(merged.facetFilters).forEach(([attr, values]) => {
    if (Array.isArray(values)) {
      values.forEach((v) => params.append(FACET_PREFIX + attr, v));
    }
  });

  const newUrl = `${window.location.pathname}?${params.toString()}`;

  if (push) {
    window.history.pushState({}, "", newUrl);
  } else {
    window.history.replaceState({}, "", newUrl);
  }

  // Notify listeners that the URL state has changed (replaceState/pushState
  // do not fire "popstate", so we use a custom event).
  window.dispatchEvent(new CustomEvent("urlstatechange"));
}
