// ---------------------------------------------------------------------------
// Shared types
// ---------------------------------------------------------------------------

export interface TypesenseSearchConfig {
  host: string;
  collection: string;
  searchKey: string;
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

export interface FacetData {
  top_most_parent: FacetCount[];
  type_name: FacetCount[];
}
