import type { TypesenseSearchConfig } from "./types";

export const QUERY_BY = "title,excerpt,content,extra_terms,type_name";
export const INFIX = "always,off,off,always,off";

export function getQueryByWeights(
  config: TypesenseSearchConfig,
): string | undefined {
  return config.queryByWeights?.trim() || undefined;
}
