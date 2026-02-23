// ---------------------------------------------------------------------------
// Typesense client factory
// ---------------------------------------------------------------------------

import { Client as TypesenseClient } from "typesense";
import type { TypesenseSearchConfig } from "./types";

export function createClient(
  cfg: TypesenseSearchConfig,
): TypesenseClient | null {
  try {
    const url = new URL(cfg.host);
    const protocol = url.protocol.replace(":", "");
    const host = url.hostname;
    const port = url.port
      ? parseInt(url.port, 10)
      : protocol === "https"
        ? 443
        : 80;

    return new TypesenseClient({
      nodes: [{ host, port, protocol }],
      apiKey: cfg.searchKey,
      connectionTimeoutSeconds: 5,
    });
  } catch (e) {
    console.error("[TypesenseSearch] Invalid host config:", e);
    return null;
  }
}
