// ---------------------------------------------------------------------------
// Unified query execution for single-collection & cross-site union search
// ---------------------------------------------------------------------------

import type { Client as TypesenseClient, SearchResponse } from "typesense";
import type {
  FacetCount,
  FacetData,
  HitDocument,
  MultiSearchCollectionConfig,
  SearchHit,
  SearchMode,
  TypesenseSearchConfig,
} from "./types";
import { buildModeSearchParams } from "./search-params";
import type { ModeSearchParams } from "./search-params";

export interface DocumentSearchOptions {
  q: string;
  hasQuery: boolean;
  mode: SearchMode;
  page: number;
  perPage: number;
  filterBy?: string;
  sortBy?: string;
  highlightFullFields?: string;
}

export interface NormalizedSearchResult {
  hits: SearchHit[];
  found: number;
  perPage: number;
}

interface RawUnionResponse {
  found?: number;
  hits?: Array<
    SearchHit & {
      search_index?: number;
      collection?: string;
      collection_index?: number;
    }
  >;
}

interface RawFederatedFacetResponse {
  results?: Array<{
    code?: number;
    facet_counts?: SearchResponse<HitDocument>["facet_counts"];
  }>;
}

export function isMultiSearchActive(config: TypesenseSearchConfig): boolean {
  return Boolean(
    config.multiSearch?.enabled &&
      Array.isArray(config.multiSearch.collections) &&
      config.multiSearch.collections.length > 1,
  );
}

function normalizeBaseUrl(rawUrl: string): string {
  return rawUrl.trim().replace(/\/+$/, "");
}

function resolveHitCollection(
  hit: SearchHit & {
    search_index?: number;
    collection?: string;
    collection_index?: number;
  },
  collections: MultiSearchCollectionConfig[],
  sortedByHomeLength: Array<{
    base: string;
    config: MultiSearchCollectionConfig;
  }>,
): MultiSearchCollectionConfig | undefined {
  if (typeof hit.search_index === "number" && collections[hit.search_index]) {
    return collections[hit.search_index];
  }

  if (typeof hit.collection === "string" && hit.collection !== "") {
    const byName = collections.find((c) => c.collection === hit.collection);
    if (byName) return byName;
  }

  if (
    typeof hit.collection_index === "number" &&
    collections[hit.collection_index]
  ) {
    return collections[hit.collection_index];
  }

  const link = String(hit.document.permalink ?? hit.document.url ?? "").trim();
  if (!link) return undefined;

  for (const candidate of sortedByHomeLength) {
    if (
      link === candidate.base ||
      link.startsWith(`${candidate.base}/`) ||
      link.startsWith(`${candidate.base}?`)
    ) {
      return candidate.config;
    }
  }

  return undefined;
}

/**
 * Annotates union-search hits with `_ts_site_name` and `_ts_is_sibling_site`
 * so hit templates and quick search can attribute results from sibling sites.
 */
export function annotateMultiSearchHits(
  hits: Array<
    SearchHit & {
      search_index?: number;
      collection?: string;
      collection_index?: number;
    }
  >,
  collections: MultiSearchCollectionConfig[],
): SearchHit[] {
  if (collections.length === 0 || hits.length === 0) return hits;

  const hostSiteId = collections[0].siteId;
  const sortedByHomeLength = collections
    .map((c) => ({ base: normalizeBaseUrl(c.homeUrl), config: c }))
    .filter((entry) => entry.base !== "")
    .sort((a, b) => b.base.length - a.base.length);

  return hits.map((hit) => {
    const matched = resolveHitCollection(hit, collections, sortedByHomeLength);
    if (!matched) return hit;

    return {
      ...hit,
      document: {
        ...hit.document,
        _ts_site_name: matched.siteName,
        _ts_site_url: matched.homeUrl,
        _ts_is_sibling_site: matched.siteId !== hostSiteId,
      },
    };
  });
}

function isRecoverableQueryError(err: unknown): boolean {
  const httpStatus = (err as { httpStatus?: number })?.httpStatus;
  return httpStatus === 400 || httpStatus === 404;
}

async function runSingleCollectionSearch(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  options: DocumentSearchOptions,
  modeParams: ModeSearchParams,
): Promise<NormalizedSearchResult> {
  const response = (await client
    .collections(config.collection)
    .documents()
    .search({
      q: options.q,
      ...(modeParams as Record<string, unknown>),
      ...(options.highlightFullFields
        ? {
            highlight_full_fields: options.highlightFullFields,
            highlight_affix_num_tokens: config.highlightAffixNumTokens ?? 15,
          }
        : {}),
      per_page: options.perPage,
      page: options.page,
      ...(options.filterBy ? { filter_by: options.filterBy } : {}),
      ...(options.sortBy ? { sort_by: options.sortBy } : {}),
    })) as SearchResponse<HitDocument>;

  return {
    hits: (response.hits ?? []) as SearchHit[],
    found: response.found ?? 0,
    perPage: response.request_params?.per_page ?? options.perPage,
  };
}

async function runUnionMultiSearch(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  options: DocumentSearchOptions,
  modeParams: ModeSearchParams,
): Promise<NormalizedSearchResult> {
  const collections = config.multiSearch?.collections ?? [];

  const searches = collections.map((col) => ({
    collection: col.collection,
    "x-typesense-api-key": col.searchKey,
    q: options.q,
    ...modeParams,
    ...(options.highlightFullFields
      ? {
          highlight_full_fields: options.highlightFullFields,
          highlight_affix_num_tokens: config.highlightAffixNumTokens ?? 15,
        }
      : {}),
    ...(options.filterBy ? { filter_by: options.filterBy } : {}),
    ...(options.sortBy ? { sort_by: options.sortBy } : {}),
    ...(col.enableCurations
      ? {}
      : { enable_curations: false, enable_overrides: false }),
  }));

  // Call apiCall.post directly because typesense-js v2.0.3 strips
  // `remove_duplicates: false` inside `client.multiSearch.perform(...)`, which
  // would otherwise deduplicate distinct WordPress posts sharing the same ID.
  const response = await client.apiCall.post<RawUnionResponse>(
    "/multi_search",
    {
      union: true,
      remove_duplicates: false,
      searches,
    },
    {
      page: options.page,
      per_page: options.perPage,
    },
  );

  const annotatedHits = annotateMultiSearchHits(
    response.hits ?? [],
    collections,
  );

  return {
    hits: annotatedHits,
    found: response.found ?? annotatedHits.length,
    perPage: options.perPage,
  };
}

/**
 * Executes either a cross-site union search or a single-collection search,
 * with automatic fallback to lexical mode or the primary collection if an
 * out-of-band schema/collection drift occurs in Typesense.
 */
export async function executeDocumentSearch(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  options: DocumentSearchOptions,
): Promise<NormalizedSearchResult> {
  const primaryModeParams = buildModeSearchParams(
    options.mode,
    options.hasQuery,
    config,
  );
  const lexicalFallbackParams = primaryModeParams.vector_query
    ? buildModeSearchParams("keyword", options.hasQuery, config)
    : null;

  if (isMultiSearchActive(config)) {
    try {
      return await runUnionMultiSearch(
        client,
        config,
        options,
        primaryModeParams,
      );
    } catch (err) {
      if (lexicalFallbackParams && isRecoverableQueryError(err)) {
        try {
          console.warn(
            "[TypesenseSearch] Vector union query rejected; falling back to keyword union search.",
            err,
          );
          return await runUnionMultiSearch(
            client,
            config,
            options,
            lexicalFallbackParams,
          );
        } catch {
          // Continue to single-collection fallback below.
        }
      }

      if (isRecoverableQueryError(err)) {
        console.warn(
          "[TypesenseSearch] Cross-site multi-search rejected; falling back to primary collection.",
          err,
        );
        return runSingleCollectionSearch(
          client,
          config,
          options,
          lexicalFallbackParams ?? primaryModeParams,
        );
      }

      throw err;
    }
  }

  try {
    return await runSingleCollectionSearch(
      client,
      config,
      options,
      primaryModeParams,
    );
  } catch (err) {
    if (lexicalFallbackParams && isRecoverableQueryError(err)) {
      console.warn(
        "[TypesenseSearch] Vector query rejected; falling back to keyword search.",
        err,
      );
      return runSingleCollectionSearch(
        client,
        config,
        options,
        lexicalFallbackParams,
      );
    }
    throw err;
  }
}

function extractSingleFacetCounts(
  facetCounts: SearchResponse<HitDocument>["facet_counts"],
  field: string,
): FacetCount[] {
  const facetResult = facetCounts?.find((f) => f.field_name === field);
  return (facetResult?.counts ?? []).map((c) => ({
    value: String(c.value),
    count: c.count,
  }));
}

async function runSingleCollectionFacets(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  q: string,
  facetFields: string[],
  facetFilters: Record<string, string[]>,
  buildFilterBy: (filters: Record<string, string[]>) => string,
  modeParams: ModeSearchParams,
): Promise<FacetData> {
  const responses = await Promise.all(
    facetFields.map((field) => {
      const otherFilters = Object.fromEntries(
        Object.entries(facetFilters).filter(([k]) => k !== field),
      );
      const filterByOthers = buildFilterBy(otherFilters);

      return client
        .collections(config.collection)
        .documents()
        .search({
          q,
          ...(modeParams as Record<string, unknown>),
          per_page: 0,
          facet_by: field,
          max_facet_values: 200,
          ...(filterByOthers ? { filter_by: filterByOthers } : {}),
        }) as Promise<SearchResponse<HitDocument>>;
    }),
  );

  const facetData: FacetData = {};
  facetFields.forEach((field, index) => {
    facetData[field] = extractSingleFacetCounts(
      responses[index].facet_counts,
      field,
    );
  });
  return facetData;
}

/**
 * Executes disjunctive facet count queries across one or multiple collections.
 * In multi-search mode, batches all `facetFields × collections` into a single
 * federated `/multi_search` HTTP request and sums counts per facet value.
 */
export async function executeDisjunctiveFacets(
  client: TypesenseClient,
  config: TypesenseSearchConfig,
  options: {
    q: string;
    hasQuery: boolean;
    mode: SearchMode;
    facetFields: string[];
    facetFilters: Record<string, string[]>;
    buildFilterBy: (filters: Record<string, string[]>) => string;
  },
): Promise<FacetData> {
  const { q, hasQuery, mode, facetFields, facetFilters, buildFilterBy } =
    options;
  if (facetFields.length === 0) {
    return {};
  }

  const primaryModeParams = buildModeSearchParams(mode, hasQuery, config);
  const lexicalFallbackParams = primaryModeParams.vector_query
    ? buildModeSearchParams("keyword", hasQuery, config)
    : null;

  if (isMultiSearchActive(config)) {
    const collections = config.multiSearch?.collections ?? [];
    const legMeta: Array<{
      field: string;
      col: MultiSearchCollectionConfig;
      filterByOthers: string;
      isPrimaryCollection: boolean;
    }> = [];

    const buildLegSearch = (
      col: MultiSearchCollectionConfig,
      field: string,
      filterByOthers: string,
      modeParamsToUse: ModeSearchParams,
    ): Record<string, unknown> => ({
      collection: col.collection,
      "x-typesense-api-key": col.searchKey,
      q,
      ...modeParamsToUse,
      per_page: 0,
      facet_by: field,
      max_facet_values: 200,
      ...(filterByOthers ? { filter_by: filterByOthers } : {}),
      ...(col.enableCurations
        ? {}
        : { enable_curations: false, enable_overrides: false }),
    });

    const searches: Array<Record<string, unknown>> = [];

    for (const field of facetFields) {
      const otherFilters = Object.fromEntries(
        Object.entries(facetFilters).filter(([k]) => k !== field),
      );
      const filterByOthers = buildFilterBy(otherFilters);

      collections.forEach((col, colIndex) => {
        legMeta.push({
          field,
          col,
          filterByOthers,
          isPrimaryCollection: colIndex === 0,
        });
        searches.push(
          buildLegSearch(col, field, filterByOthers, primaryModeParams),
        );
      });
    }

    try {
      const federated = await client.apiCall.post<RawFederatedFacetResponse>(
        "/multi_search",
        { searches },
        {},
      );
      const legResults = [...(federated.results ?? [])];

      // Federated `/multi_search` returns HTTP 200 with per-leg `code >= 400`
      // when a collection rejects a vector query. Retry failed legs lexically.
      if (lexicalFallbackParams) {
        const failedIndices: number[] = [];
        legMeta.forEach((_, idx) => {
          const leg = legResults[idx];
          if (!leg || (typeof leg.code === "number" && leg.code >= 400)) {
            failedIndices.push(idx);
          }
        });

        if (failedIndices.length > 0) {
          const retrySearches = failedIndices.map((idx) => {
            const meta = legMeta[idx];
            return buildLegSearch(
              meta.col,
              meta.field,
              meta.filterByOthers,
              lexicalFallbackParams,
            );
          });
          const retryResponse =
            await client.apiCall.post<RawFederatedFacetResponse>(
              "/multi_search",
              { searches: retrySearches },
              {},
            );
          (retryResponse.results ?? []).forEach((retryLeg, retryIdx) => {
            const originalIdx = failedIndices[retryIdx];
            if (originalIdx !== undefined && retryLeg) {
              legResults[originalIdx] = retryLeg;
            }
          });
        }
      }

      const anyPrimaryLegSucceeded = legMeta.some((meta, idx) => {
        if (!meta.isPrimaryCollection) return false;
        const leg = legResults[idx];
        return Boolean(
          leg &&
            (typeof leg.code !== "number" || leg.code < 400) &&
            leg.facet_counts,
        );
      });

      if (anyPrimaryLegSucceeded) {
        const aggregated = new Map<string, Map<string, number>>();
        facetFields.forEach((f) => aggregated.set(f, new Map()));

        legResults.forEach((legResult, idx) => {
          const meta = legMeta[idx];
          if (!meta || !legResult?.facet_counts) return;
          if (typeof legResult.code === "number" && legResult.code >= 400) {
            return;
          }
          const bucket = aggregated.get(meta.field);
          if (!bucket) return;

          for (const item of extractSingleFacetCounts(
            legResult.facet_counts,
            meta.field,
          )) {
            bucket.set(item.value, (bucket.get(item.value) ?? 0) + item.count);
          }
        });

        const facetData: FacetData = {};
        facetFields.forEach((field) => {
          const bucket = aggregated.get(field) ?? new Map<string, number>();
          facetData[field] = Array.from(bucket.entries()).map(
            ([value, count]) => ({ value, count }),
          );
        });
        return facetData;
      }

      console.warn(
        "[TypesenseSearch] Federated facet legs failed for primary collection; falling back to single-collection facets.",
      );
    } catch (err) {
      console.warn(
        "[TypesenseSearch] Federated facet query failed; falling back to primary collection facets.",
        err,
      );
    }
  }

  try {
    return await runSingleCollectionFacets(
      client,
      config,
      q,
      facetFields,
      facetFilters,
      buildFilterBy,
      primaryModeParams,
    );
  } catch (err) {
    if (lexicalFallbackParams && isRecoverableQueryError(err)) {
      return runSingleCollectionFacets(
        client,
        config,
        q,
        facetFields,
        facetFilters,
        buildFilterBy,
        lexicalFallbackParams,
      );
    }
    throw err;
  }
}
