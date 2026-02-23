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
  facets?: FacetConfig[];
}

declare global {
  interface Window {
    typesenseConfig?: TypesenseSearchConfig;
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
