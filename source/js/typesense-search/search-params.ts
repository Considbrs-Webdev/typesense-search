import type { SearchMode, TypesenseSearchConfig } from "./types";

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
 *
 * Automatically falls back to lexical search when:
 * - Embedded search is not marked ready in `config.vectorSearch.enabled`
 * - `hasQuery` is false (wildcard `q: "*"` cannot run against vector fields)
 * - `mode` is `"keyword"`
 */
export function buildModeSearchParams(
  mode: SearchMode,
  hasQuery: boolean,
  config: TypesenseSearchConfig,
): ModeSearchParams {
  const baseWeights = getQueryByWeights(config);
  const effectiveMode: SearchMode = config.vectorSearch?.enabled
    ? mode
    : "keyword";

  if (!hasQuery || effectiveMode === "keyword") {
    return {
      query_by: QUERY_BY,
      infix: INFIX,
      ...(baseWeights ? { query_by_weights: baseWeights } : {}),
      exclude_fields: EXCLUDE_FIELDS,
    };
  }

  if (effectiveMode === "semantic") {
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
