// ---------------------------------------------------------------------------
// URL state – source of truth for all search parameters.
// Adapted from municipio-search/url-state.ts.
// ---------------------------------------------------------------------------

import type { SearchMode, TypesenseSearchConfig } from "./types";

export type { SearchMode };

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

export function isValidSearchMode(value: unknown): value is SearchMode {
  return value === "keyword" || value === "hybrid" || value === "semantic";
}

export function getDefaultSearchMode(
  config: TypesenseSearchConfig | undefined = typeof window !== "undefined"
    ? window.typesenseConfig
    : undefined,
): SearchMode {
  if (!config?.vectorSearch?.enabled) {
    return "keyword";
  }
  return isValidSearchMode(config.vectorSearch.defaultMode)
    ? config.vectorSearch.defaultMode
    : "hybrid";
}

function canVisitorSwitchMode(config?: TypesenseSearchConfig): boolean {
  return Boolean(
    config?.vectorSearch?.enabled && config.vectorSearch.showModeSwitcher,
  );
}

export function getUrlState(
  config: TypesenseSearchConfig | undefined = typeof window !== "undefined"
    ? window.typesenseConfig
    : undefined,
): UrlState {
  const params = new URLSearchParams(window.location.search);

  const query = params.get("s") ?? params.get("q") ?? "";
  const page = Math.max(1, parseInt(params.get(PAGE_KEY) ?? "1", 10) || 1);
  const sort = params.get(SORT_KEY) ?? "relevance";

  const defaultMode = getDefaultSearchMode(config);
  const rawMode = params.get(MODE_KEY);
  const mode: SearchMode =
    canVisitorSwitchMode(config) && isValidSearchMode(rawMode)
      ? rawMode
      : defaultMode;

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
  config: TypesenseSearchConfig | undefined = typeof window !== "undefined"
    ? window.typesenseConfig
    : undefined,
): void {
  const current = getUrlState(config);
  const defaultMode = getDefaultSearchMode(config);
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

  if (
    canVisitorSwitchMode(config) &&
    isValidSearchMode(merged.mode) &&
    merged.mode !== defaultMode
  ) {
    params.set(MODE_KEY, merged.mode);
  }

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
