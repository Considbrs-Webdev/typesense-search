// ---------------------------------------------------------------------------
// Template selection and rendering
// ---------------------------------------------------------------------------

import type { HitDocument, HighlightField, SearchHit } from "./types";
import { replacePlaceholders } from "./html";

export function getHitTemplates(container: Element): Map<string, string> {
  const map = new Map<string, string>();
  container.querySelectorAll("template").forEach((tpl) => {
    for (let i = 0; i < tpl.attributes.length; i++) {
      const attrName = tpl.attributes[i].name;
      if (attrName.startsWith("data-js-search-hit-template-")) {
        const type =
          attrName.replace("data-js-search-hit-template-", "") || "default";
        map.set(type, tpl.innerHTML);
        break;
      }
    }
  });
  return map;
}

export function pickTemplate(
  doc: HitDocument,
  templates: Map<string, string>,
): string {
  const postType = String(doc.post_type ?? doc.type ?? "");
  const hasImage = !!(doc.thumbnail && String(doc.thumbnail).length > 0);

  const templateMapping =
    (typeof window !== "undefined" &&
      (window as any).typesenseConfig?.templateMapping) ||
    {};
  const mappedKey = templateMapping[postType];
  console.log("Mapped key for post type", postType, "is", mappedKey, templates);
  if (mappedKey && templates.has(mappedKey)) return templates.get(mappedKey)!;

  if (!hasImage) {
    const noimageKey = `${postType}-noimage`;
    if (templates.has(noimageKey)) return templates.get(noimageKey)!;
  }

  if (templates.has(postType)) return templates.get(postType)!;
  if (hasImage && templates.has("image")) return templates.get("image")!;
  if (templates.has("noimage")) return templates.get("noimage")!;
  return templates.get("default") ?? "";
}

export function renderHit(
  hit: SearchHit,
  templates: Map<string, string>,
): string {
  const templateHtml = pickTemplate(hit.document, templates);
  return replacePlaceholders(templateHtml, hit.document, hit.highlight);
}
