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
}

export interface TypesenseI18n {
  resultSingular: string;
  resultPlural: string;
  searchError: string;
  readMore: string;
  paginationLabel: string;
}

export interface TypesenseQuickSearchI18n {
  seeAllResults: string;
  noResults?: string;
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
