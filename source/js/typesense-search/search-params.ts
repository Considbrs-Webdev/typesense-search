import type { TypesenseSearchConfig } from "./types";
import type { SearchMode } from "./url-state";

export const QUERY_BY = "title,excerpt,content,extra_terms,type_name";
export const INFIX = "always,off,off,always,off";
export const EXCLUDE_FIELDS = "embedding";

export const HYBRID_QUERY_BY = `${QUERY_BY},embedding`;
export const HYBRID_INFIX = `${INFIX},off`;
export const SEMANTIC_QUERY_BY = "embedding";

export const HYBRID_VECTOR_QUERY =
  "embedding:([], alpha: 0.3, distance_threshold: 0.75, k: 100)";
export const SEMANTIC_VECTOR_QUERY =
  "embedding:([], distance_threshold: 0.75, k: 100)";

export function getQueryByWeights(
  config: TypesenseSearchConfig,
): string | undefined {
  return config.queryByWeights?.trim() || undefined;
}

export interface ModeSearchParams {
  query_by: string;
  infix?: string;
  query_by_weights?: string;
  vector_query?: string;
  exclude_fields: string;
}

/**
 * Build mode-specific Typesense query parameters.
 * When `hasQuery` is false (e.g. wildcard `q: "*"` with only facet filters),
 * falls back to lexical `QUERY_BY` because vector fields cannot execute `*`.
 */
export function buildModeSearchParams(
  mode: SearchMode,
  hasQuery: boolean,
  config: TypesenseSearchConfig,
): ModeSearchParams {
  const baseWeights = getQueryByWeights(config);

  if (!hasQuery || mode === "keyword") {
    return {
      query_by: QUERY_BY,
      infix: INFIX,
      ...(baseWeights ? { query_by_weights: baseWeights } : {}),
      exclude_fields: EXCLUDE_FIELDS,
    };
  }

  if (mode === "semantic") {
    return {
      query_by: SEMANTIC_QUERY_BY,
      vector_query: SEMANTIC_VECTOR_QUERY,
      exclude_fields: EXCLUDE_FIELDS,
    };
  }

  return {
    query_by: HYBRID_QUERY_BY,
    infix: HYBRID_INFIX,
    ...(baseWeights ? { query_by_weights: `${baseWeights},1` } : {}),
    vector_query: HYBRID_VECTOR_QUERY,
    exclude_fields: EXCLUDE_FIELDS,
  };
}
