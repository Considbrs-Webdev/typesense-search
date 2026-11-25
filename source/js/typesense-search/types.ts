// ---------------------------------------------------------------------------
// Shared types
// ---------------------------------------------------------------------------

export type VectorSearchStrategy = "keyword" | "parallel" | "blended" | "rescue";

export type SubsiteProfile = "standard" | "tightened" | "keyword_only";

export type QueryExecutionMode =
  | "keyword"
  | "hybrid"
  | "semantic"
  | "spotlight"
  | "quick_hybrid";

export interface FacetConfig {
  field: string;
  label: string;
  placeholder: string;
  display_as: "dropdown" | "button_group";
}

export interface VectorSearchConfig {
  enabled: boolean;
  strategy?: VectorSearchStrategy;
  alpha?: number;
  distanceThreshold?: number;
  k?: number;
  rerankHybridMatches?: boolean;
  spotlightMaxHits?: number;
  spotlightDistanceThreshold?: number;
  spotlightMinWords?: number;
  spotlightDedupe?: boolean;
  rescueMaxKeywordHits?: number;
  quickAlpha?: number;
  quickDistanceThreshold?: number;
  quickK?: number;
}

export interface MultiSearchCollectionConfig {
  collection: string;
  searchKey: string;
  siteId: number;
  siteName: string;
  homeUrl: string;
  enableCurations: boolean;
  subsiteProfile?: SubsiteProfile;
}

export interface MultiSearchConfig {
  enabled: boolean;
  collections: MultiSearchCollectionConfig[];
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
  truncator?: string;
  sortDisplay?: "radio" | "dropdown";
  searchLogging?: SearchStatisticsConfig;
  /** Web Awesome translation module key (e.g. `sv`); omit for English internals. */
  webAwesomeLocale?: string | null;
  documentTitle?: { template: string; empty: string };
  templateMapping?: Record<string, string>;
  placeholderMappings?: Record<string, string>;
  vectorSearch?: VectorSearchConfig;
  multiSearch?: MultiSearchConfig;
}

export interface SearchStatisticsConfig {
  enabled: boolean;
  requireConsent: boolean;
  delayMs: number;
  minimumChars: number;
  endpoint: string;
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
  semanticMatchBadge?: string;
  rescueHeading?: string;
  rescueZeroHitsHeading?: string;
}

export interface TypesenseQuickSearchI18n {
  seeAllResults: string;
  noResults?: string;
  dialogTitle?: string;
  dialogHint?: string;
  closeDialog?: string;
  searchLabel?: string;
  submitSearch?: string;
  semanticMatchBadge?: string;
}

declare global {
  interface Window {
    typesenseConfig?: TypesenseSearchConfig;
    typesenseI18n?: TypesenseI18n;
    typesenseQuickSearchI18n?: TypesenseQuickSearchI18n;
    typesenseSearchStatisticsConsent?: boolean;
  }
}

export type HighlightField = { snippet?: string };
export type HitDocument = Record<string, unknown>;

export interface SearchHit {
  document: HitDocument;
  highlight?: Record<string, HighlightField>;
  text_match?: number;
  text_match_info?: {
    best_field_score?: string;
    tokens_matched?: number;
  };
  vector_distance?: number;
  hybrid_search_info?: {
    rank_fusion_score?: number;
  };
}

export interface FacetCount {
  value: string;
  count: number;
}

/** Dynamic map of facet field → counts array returned by runSearch. */
export type FacetData = Record<string, FacetCount[]>;
