// ---------------------------------------------------------------------------
// URL state – source of truth for all search parameters.
// Adapted from municipio-search/url-state.ts.
// ---------------------------------------------------------------------------

export interface UrlState {
  query: string;
  page: number;
  sort: string;
  facetFilters: Record<string, string[]>;
}

const SORT_KEY = "sort";
const PAGE_KEY = "page";
const FACET_PREFIX = "facet_";

export function getUrlState(): UrlState {
  const params = new URLSearchParams(window.location.search);

  const query = params.get("s") ?? params.get("q") ?? "";
  const page = Math.max(1, parseInt(params.get(PAGE_KEY) ?? "1", 10) || 1);
  const sort = params.get(SORT_KEY) ?? "relevance";

  const facetFilters: Record<string, string[]> = {};
  params.forEach((value, key) => {
    if (key.startsWith(FACET_PREFIX)) {
      const attr = key.slice(FACET_PREFIX.length);
      if (!facetFilters[attr]) facetFilters[attr] = [];
      facetFilters[attr].push(value);
    }
  });

  return { query, page, sort, facetFilters };
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
    facetFilters:
      state.facetFilters !== undefined
        ? state.facetFilters
        : current.facetFilters,
  };

  const params = new URLSearchParams();

  if (merged.query) params.set("s", merged.query);

  if (merged.page > 1) params.set(PAGE_KEY, String(merged.page));

  if (merged.sort && merged.sort !== "relevance")
    params.set(SORT_KEY, merged.sort);

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
}
