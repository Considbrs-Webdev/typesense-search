// ---------------------------------------------------------------------------
// Shared types
// ---------------------------------------------------------------------------

export interface FacetConfig {
  field: string;
  label: string;
  placeholder: string;
  display_as: "dropdown" | "button_group";
}

export interface TypesenseSearchConfig {
  host: string;
  collection: string;
  searchKey: string;
  hitsPerPage: number;
  quickSearchHitsPerPage?: number;
  facets?: FacetConfig[];
  debounce?: boolean;
  debounceDelay?: number;
  highlightAffixNumTokens?: number;
  queryByWeights?: string;
  sortDisplay?: "radio" | "dropdown";
  /** Web Awesome translation module key (e.g. `sv`); omit for English internals. */
  webAwesomeLocale?: string | null;
}

export interface TypesenseI18n {
  resultSingular: string;
  resultPlural: string;
  searchError: string;
  clearSearchField: string;
  paginationLabel: string;
  paginationPrevious: string;
  paginationNext: string;
  loadingSearch: string;
}

export interface TypesenseQuickSearchI18n {
  seeAllResults: string;
  noResults?: string;
  dialogTitle?: string;
  dialogHint?: string;
  closeDialog?: string;
  searchLabel?: string;
  submitSearch?: string;
}

declare global {
  interface Window {
    typesenseConfig?: TypesenseSearchConfig;
    typesenseI18n?: TypesenseI18n;
    typesenseQuickSearchI18n?: TypesenseQuickSearchI18n;
  }
}

export type HighlightField = { snippet?: string };
export type HitDocument = Record<string, unknown>;

export interface SearchHit {
  document: HitDocument;
  highlight?: Record<string, HighlightField>;
}

export interface FacetCount {
  value: string;
  count: number;
}

/** Dynamic map of facet field → counts array returned by runSearch. */
export type FacetData = Record<string, FacetCount[]>;
