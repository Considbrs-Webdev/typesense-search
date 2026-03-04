// ---------------------------------------------------------------------------
// HTML utilities and placeholder definitions
// ---------------------------------------------------------------------------

import type { HitDocument, HighlightField } from "./types";

export type PlaceholderResult = string | { value: string; highlighted: true };
export type PlaceholderFn = (
  doc: HitDocument,
  highlight?: Record<string, HighlightField>,
) => PlaceholderResult;

export const PLACEHOLDERS: Record<string, PlaceholderFn> = {
  SEARCH_HIT_HEADING: (d, h) => {
    const snippet = h?.title?.snippet;
    if (snippet) return { value: snippet, highlighted: true };
    return String(d.title ?? "");
  },
  SEARCH_HIT_SUBHEADING: (d) =>
    String(d.post_type_name ?? d.type_name ?? d.post_date_formatted ?? ""),
  SEARCH_HIT_EXCERPT: (d, h) => {
    const excerptSnippet = h?.excerpt?.snippet;
    const contentSnippet = h?.content?.snippet;
    const snippet = excerptSnippet ?? contentSnippet;
    if (snippet) {
      const fullText = String(
        excerptSnippet ? (d.excerpt ?? "") : (d.content ?? ""),
      ).trim();
      const snippetText = snippet.replace(/<\/?mark>/gi, "").trim();
      const atStart = !fullText || fullText.startsWith(snippetText);
      const atEnd = !fullText || fullText.endsWith(snippetText);
      const truncator =
        (typeof window !== "undefined" &&
          (window as any).typesenseConfig?.truncator) ||
        "[...]";

      // If user explicitly sets 'none', don't add truncation markers.
      if (truncator === "none") {
        return { value: snippet, highlighted: true };
      }

      const prefix = atStart ? "" : `${truncator} `;
      const suffix = atEnd ? "" : ` ${truncator}`;

      return { value: `${prefix}${snippet}${suffix}`, highlighted: true };
    }
    return String(d.excerpt ?? "");
  },
  SEARCH_HIT_LINK: (d) => String(d.permalink ?? d.url ?? "#"),
  SEARCH_HIT_IMAGE_URL: (d) => String(d.thumbnail ?? ""),
  SEARCH_HIT_IMAGE_ALT: (d) => String(d.thumbnail_alt ?? ""),
  SEARCH_HIT_ARIA_LABEL: (d) =>
    `${window.typesenseI18n?.readMore ?? "Read more: "}${d.title ?? ""}`,

  SEARCH_HIT_DATE: (d) => {
    if (d.post_date_formatted) return String(d.post_date_formatted);
    const ts = typeof d.date === "number" ? d.date : 0;
    return ts > 0 ? new Date(ts * 1000).toLocaleDateString() : "";
  },
  SEARCH_HIT_META: (d) => {
    const type = String(d.post_type_name ?? d.type_name ?? "");
    const date = String(d.post_date_formatted ?? "");
    return [type, date].filter(Boolean).join(" · ");
  },
  SEARCH_HIT_PATH: (d) => {
    const raw = String(d.path ?? "");
    if (!raw) return "";
    const parts = raw
      .split("/")
      .map((p) => p.trim())
      .filter(Boolean);
    const sep = ' <span class="separator">/</span> ';
    const html = parts
      .map((part, idx) => {
        const safe = escapeHtml(decodeHtmlEntities(String(part ?? "")));
        return idx === parts.length - 1 ? safe : `<strong>${safe}</strong>`;
      })
      .join(sep);
    const wrapped = `<span class="breadcrumbs">${html}</span>`;
    return { value: wrapped, highlighted: true } as const;
  },
};

export function escapeAttr(str: string): string {
  return str
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

export function escapeText(str: string): string {
  return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

export function escapeHtml(str: string): string {
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML;
}

export function decodeHtmlEntities(str: string): string {
  const ta = document.createElement("textarea");
  ta.innerHTML = str;
  return ta.value;
}

export function escapeHtmlPreservingMarks(str: string): string {
  const decoded = decodeHtmlEntities(str);
  const MARK_S = "\u0000\u0001MS\u0001\u0000";
  const MARK_E = "\u0000\u0001ME\u0001\u0000";
  const withPlaceholders = decoded
    .replace(/<mark>/gi, MARK_S)
    .replace(/<\/mark>/gi, MARK_E);
  const escaped = escapeHtml(withPlaceholders);
  return escaped
    .replace(new RegExp(MARK_S, "g"), "<mark>")
    .replace(new RegExp(MARK_E, "g"), "</mark>");
}

function getByPath(obj: Record<string, unknown>, path: string): unknown {
  const parts = path.split(".");
  let current: unknown = obj;
  for (const part of parts) {
    if (current == null || typeof current !== "object") return undefined;
    current = (current as Record<string, unknown>)[part];
  }
  return current;
}

function buildPlaceholders(): Record<string, PlaceholderFn> {
  const built = { ...PLACEHOLDERS };
  const mappings =
    (typeof window !== "undefined" &&
      (window as any).typesenseConfig?.placeholderMappings) ||
    {};
  for (const [placeholderKey, fieldPath] of Object.entries(mappings)) {
    if (typeof fieldPath !== "string" || !placeholderKey) continue;
    built[placeholderKey] = (d) =>
      String(getByPath(d as Record<string, unknown>, fieldPath) ?? "");
  }
  return built;
}

export function replacePlaceholders(
  html: string,
  doc: HitDocument,
  highlight?: Record<string, HighlightField>,
): string {
  const placeholders = buildPlaceholders();
  let result = html;
  for (const [key, fn] of Object.entries(placeholders)) {
    const out = fn(doc, highlight);
    let value: string;
    if (typeof out === "object" && out.highlighted) {
      // out.value may contain intentional HTML (e.g. <strong>, <span>)
      // assume we've escaped internal text when building it and decode entities
      // so we can insert the HTML as-is.
      value = decodeHtmlEntities(String(out.value));
    } else {
      value = escapeHtml(decodeHtmlEntities(String(out)));
    }
    result = result.replaceAll(`{${key}}`, value);
  }
  return result;
}
