import type {
  QueryExecutionMode,
  SearchHit,
  SubsiteProfile,
  TypesenseSearchConfig,
  VectorSearchStrategy,
} from "./types";

export const QUERY_BY = "title,excerpt,content,extra_terms,type_name";
export const INFIX = "always,off,off,always,off";
export const EXCLUDE_FIELDS = "embedding";

export const HYBRID_QUERY_BY = `${QUERY_BY},embedding`;
export const HYBRID_INFIX = `${INFIX},off`;
export const SEMANTIC_QUERY_BY = "embedding";

export function getQueryByWeights(
  config: TypesenseSearchConfig,
): string | undefined {
  return config.queryByWeights?.trim() || undefined;
}

export function getActiveStrategy(
  config?: TypesenseSearchConfig,
): VectorSearchStrategy {
  if (!config?.vectorSearch?.enabled) {
    return "keyword";
  }

  const raw = config.vectorSearch.strategy;
  if (raw === "parallel" || raw === "blended" || raw === "rescue") {
    return raw;
  }

  return "blended";
}

export interface ModeSearchParams {
  query_by: string;
  infix?: string;
  query_by_weights?: string;
  text_match_type?: string;
  drop_tokens_mode?: string;
  vector_query?: string;
  rerank_hybrid_matches?: boolean;
  exclude_fields: string;
}

const DROP_TOKENS_BOTH_SIDES = "both_sides:12";

function buildLexicalParams(
  baseWeights?: string,
  hasQuery = false,
): ModeSearchParams {
  return {
    query_by: QUERY_BY,
    infix: INFIX,
    ...(baseWeights
      ? { query_by_weights: baseWeights, text_match_type: "max_weight" }
      : {}),
    ...(hasQuery ? { drop_tokens_mode: DROP_TOKENS_BOTH_SIDES } : {}),
    exclude_fields: EXCLUDE_FIELDS,
  };
}

/**
 * Build mode-specific Typesense query parameters, applying site ranking settings
 * and per-subsite tightening profiles for cross-site multi-search legs.
 *
 * Automatically falls back to lexical search when:
 * - Embedded search is not marked ready in `config.vectorSearch.enabled`
 * - `hasQuery` is false (wildcard `q: "*"` cannot run against vector fields)
 * - `mode` is `"keyword"`, or `mode` is `"quick_hybrid"` with a 1–2 char prefix
 */
export function buildModeSearchParams(
  mode: QueryExecutionMode,
  hasQuery: boolean,
  config: TypesenseSearchConfig,
  subsiteProfile: SubsiteProfile = "standard",
  queryLength = 3,
): ModeSearchParams {
  const baseWeights = getQueryByWeights(config);
  const vectorCfg = config.vectorSearch;

  if (!hasQuery || !vectorCfg?.enabled || mode === "keyword") {
    return buildLexicalParams(baseWeights, hasQuery);
  }

  if (mode === "quick_hybrid" && queryLength < 3) {
    return buildLexicalParams(baseWeights, true);
  }

  if (mode === "spotlight" || mode === "semantic") {
    const baseThreshold =
      mode === "spotlight"
        ? (vectorCfg.spotlightDistanceThreshold ?? 0.68)
        : (vectorCfg.distanceThreshold ?? 0.75);
    const baseK =
      mode === "spotlight"
        ? Math.max(20, (vectorCfg.spotlightMaxHits ?? 3) * 5)
        : Math.min(vectorCfg.k ?? 100, 40);

    let threshold = baseThreshold;
    let k = baseK;

    if (subsiteProfile === "tightened") {
      threshold = Number((baseThreshold * 0.85).toFixed(2));
      k = Math.min(baseK, 15);
    } else if (subsiteProfile === "keyword_only") {
      threshold = 0;
      k = 1;
    }

    return {
      query_by: SEMANTIC_QUERY_BY,
      vector_query: `embedding:([], distance_threshold: ${threshold}, k: ${k})`,
      exclude_fields: EXCLUDE_FIELDS,
    };
  }

  const isQuick = mode === "quick_hybrid";
  let alpha = isQuick
    ? (vectorCfg.quickAlpha ?? 0.58)
    : (vectorCfg.alpha ?? 0.58);
  let distanceThreshold = isQuick
    ? (vectorCfg.quickDistanceThreshold ?? 0.68)
    : (vectorCfg.distanceThreshold ?? 0.75);
  let k = isQuick ? (vectorCfg.quickK ?? 40) : (vectorCfg.k ?? 100);
  const rerank = vectorCfg.rerankHybridMatches === true;

  if (subsiteProfile === "tightened") {
    // Keep `alpha` equal to the primary leg so genuine #1 semantic matches on a
    // sibling subsite (e.g. foretag.eslov.se) tie on rank_fusion_score and win
    // via `_vector_distance: asc`, while tighter `distance_threshold` and `k`
    // filter out loosely related subsite pages before fusion.
    distanceThreshold = Number((distanceThreshold * 0.85).toFixed(2));
    k = Math.min(k, isQuick ? 15 : 20);
  } else if (subsiteProfile === "keyword_only") {
    // Keep identical _rank_fusion sort signature across Typesense union legs
    // while giving 0% vector weight and 0 semantic-only candidates.
    alpha = 0;
    distanceThreshold = 0;
    k = 1;
  }

  return {
    query_by: HYBRID_QUERY_BY,
    infix: HYBRID_INFIX,
    ...(baseWeights
      ? {
          query_by_weights: `${baseWeights},1`,
          text_match_type: "max_weight",
        }
      : {}),
    drop_tokens_mode: DROP_TOKENS_BOTH_SIDES,
    vector_query: `embedding:([], alpha: ${alpha}, distance_threshold: ${distanceThreshold}, k: ${k})`,
    ...(rerank ? { rerank_hybrid_matches: true } : {}),
    exclude_fields: EXCLUDE_FIELDS,
  };
}

/**
 * Identifies hits matched purely via vector embeddings with zero exact word matches.
 */
export function isSemanticOnlyMatch(hit: SearchHit): boolean {
  const hasVectorSignal =
    typeof hit.vector_distance === "number" ||
    hit.hybrid_search_info !== undefined;
  if (!hasVectorSignal) {
    return false;
  }

  if (typeof hit.text_match === "number") {
    return hit.text_match === 0;
  }

  if (typeof hit.text_match_info?.tokens_matched === "number") {
    return hit.text_match_info.tokens_matched === 0;
  }

  if (hit.text_match_info?.best_field_score !== undefined) {
    return hit.text_match_info.best_field_score === "0";
  }

  const highlights = hit.highlight ? Object.values(hit.highlight) : [];
  const hasMark = highlights.some(
    (field) => typeof field?.snippet === "string" && /<mark>/i.test(field.snippet),
  );
  return !hasMark;
}
