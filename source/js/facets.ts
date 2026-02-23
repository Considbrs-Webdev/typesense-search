import "@awesome.me/webawesome/dist/components/button/button";
import "@awesome.me/webawesome/dist/components/button-group/button-group";
import "@awesome.me/webawesome/dist/components/select/select";
import "@awesome.me/webawesome/dist/components/option/option";

import { getUrlState, updateUrlState } from "./url-state";
import { escapeAttr, escapeText } from "./html";
import type { FacetConfig, FacetCount, FacetData } from "./types";

// ---------------------------------------------------------------------------
// Programmatic-update guard
// Shared with the main file so the sort handler can use the same set.
// ---------------------------------------------------------------------------

export const programmaticUpdates = new WeakSet<Element>();

// ---------------------------------------------------------------------------
// Renderers
// ---------------------------------------------------------------------------

/**
 * Re-populate a `<wa-select multiple>` with fresh facet counts.
 * Selected values that dropped out of the results are kept as "ghosts" so the
 * user can still deselect them.
 */
function renderDropdown(
  selectEl: Element,
  counts: FacetCount[],
  selectedValues: string[],
): void {
  const countValues = new Set(counts.map((c) => c.value));
  const ghosts = selectedValues
    .filter((v) => !countValues.has(v))
    .map((v) => ({ value: v, count: 0 }));

  programmaticUpdates.add(selectEl);

  selectEl.innerHTML = [...ghosts, ...counts]
    .map(
      ({ value, count }) =>
        `<wa-option value="${escapeAttr(value)}">${escapeText(value)}${count > 0 ? ` (${count})` : ""}</wa-option>`,
    )
    .join("");

  requestAnimationFrame(() => {
    (selectEl as any).value = selectedValues;
    requestAnimationFrame(() => programmaticUpdates.delete(selectEl));
  });
}

/**
 * Re-populate a `<wa-button-group>` with one toggle button per facet option.
 */
function renderButtonGroup(
  groupEl: Element,
  counts: FacetCount[],
  selectedValues: string[],
): void {
  const selectedSet = new Set(selectedValues);
  const countValues = new Set(counts.map((c) => c.value));
  const ghosts = selectedValues
    .filter((v) => !countValues.has(v))
    .map((v) => ({ value: v, count: 0 }));

  groupEl.innerHTML = [...ghosts, ...counts]
    .map(({ value, count }) => {
      const active = selectedSet.has(value);
      return `<wa-button
        class="${active ? "active" : "not-active"}"
        data-facet-value="${escapeAttr(value)}"
        aria-pressed="${active}"
      >${escapeText(value)}${count > 0 ? ` (${count})` : ""}</wa-button>`;
    })
    .join("");
}

// ---------------------------------------------------------------------------
// Element builder
// ---------------------------------------------------------------------------

/**
 * Create the widget element (`<wa-select>` or `<wa-button-group>`) for a
 * single facet, append it to `parentEl`, and return the widget element.
 */
function buildFacetElement(parentEl: HTMLElement, facet: FacetConfig): Element {
  if (facet.display_as === "button_group") {
    const wrapper = document.createElement("div");
    wrapper.className = "wa-stack ts-facet-button-group";

    const label = document.createElement("span");
    label.className = "ts-facet-label";
    label.textContent = facet.label;

    const group = document.createElement("wa-button-group");
    group.setAttribute("label", facet.label);
    group.setAttribute("data-facet-field", facet.field);

    wrapper.appendChild(label);
    wrapper.appendChild(group);
    parentEl.appendChild(wrapper);
    return group;
  } else {
    const select = document.createElement("wa-select");
    select.setAttribute("multiple", "");
    select.setAttribute("size", "medium");
    select.setAttribute("label", facet.label);
    select.setAttribute("placeholder", facet.placeholder);
    select.setAttribute("with-clear", "");
    select.setAttribute("data-facet-field", facet.field);

    parentEl.appendChild(select);
    return select;
  }
}

function buildElements(
  containerEl: HTMLElement,
  facetCfg: FacetConfig[],
): Map<string, Element> {
  const map = new Map<string, Element>();
  facetCfg.forEach((facet) => {
    map.set(facet.field, buildFacetElement(containerEl, facet));
  });
  return map;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

export interface FacetController {
  /** Ordered list of facet field names — passed to runSearch. */
  fields: string[];
  /** Update the facet UI after a search response. */
  render(facetData: FacetData | null): void;
  /** Push URL filter state into the dropdown UI controls. */
  sync(facetFilters: Record<string, string[]>): void;
  /** Attach all facet event listeners (call once after setup). */
  bindEvents(): void;
}

/**
 * Locate facet elements and return a controller the main search loop can
 * call to keep everything in sync.
 *
 * Resolution order:
 * 1. `[data-js-search-page-container] [data-js-facets-container]` — if that
 *    element exists, new facet widgets are built inside it (original behaviour).
 * 2. Otherwise, existing DOM elements matched by
 *    `[data-js-facet-type="<field>"]` are wired up directly.
 */
export function setupFacets(facetCfg: FacetConfig[]): FacetController {
  const searchContainer = document.querySelector<HTMLElement>(
    "[data-js-search-page-container]",
  );
  const containerEl =
    searchContainer?.querySelector<HTMLElement>("[data-js-facets-container]") ??
    null;

  let elMap: Map<string, Element>;

  if (containerEl) {
    elMap = buildElements(containerEl, facetCfg);
  } else {
    elMap = new Map<string, Element>();
    facetCfg.forEach((facet) => {
      const container = document.querySelector<HTMLElement>(
        `[data-js-facet-type="${facet.field}"]`,
      );
      if (container) {
        elMap.set(facet.field, buildFacetElement(container, facet));
      }
    });
  }

  const fields = facetCfg.map((f) => f.field);

  // -------------------------------------------------------------------------

  function render(facetData: FacetData | null): void {
    if (!facetData) {
      elMap.forEach((el) => (el.innerHTML = ""));
      return;
    }

    const { facetFilters } = getUrlState();

    facetCfg.forEach((facet) => {
      const el = elMap.get(facet.field);
      if (!el) return;
      const counts = facetData[facet.field] ?? [];
      const selected = facetFilters[facet.field] ?? [];

      const hasOptions = counts.length > 0 || selected.length > 0;

      if (facet.display_as === "button_group") {
        renderButtonGroup(el, counts, selected);
        const wrapper = el.closest<HTMLElement>(".ts-facet-button-group");
        (wrapper ?? (el as HTMLElement)).hidden = !hasOptions;
      } else {
        renderDropdown(el, counts, selected);
        el.toggleAttribute("disabled", !hasOptions);
      }
    });
  }

  // -------------------------------------------------------------------------

  function sync(facetFilters: Record<string, string[]>): void {
    facetCfg.forEach((facet) => {
      if (facet.display_as !== "dropdown") return;
      const el = elMap.get(facet.field);
      if (!el) return;
      const selected = facetFilters[facet.field] ?? [];
      programmaticUpdates.add(el);
      requestAnimationFrame(() => {
        (el as any).value = selected;
        requestAnimationFrame(() => programmaticUpdates.delete(el));
      });
    });
  }

  // -------------------------------------------------------------------------

  function bindEvents(): void {
    facetCfg.forEach((facet) => {
      const el = elMap.get(facet.field);
      if (!el) return;

      if (facet.display_as === "button_group") {
        // Delegation handles re-rendered buttons without re-binding.
        el.addEventListener("click", (e: Event) => {
          const btn = (e.target as Element).closest<HTMLElement>(
            "wa-button[data-facet-value]",
          );
          if (!btn) return;

          const value = btn.dataset.facetValue ?? "";
          const { facetFilters } = getUrlState();
          const current = facetFilters[facet.field] ?? [];
          const next = current.includes(value)
            ? current.filter((v) => v !== value)
            : [...current, value];

          updateUrlState({
            page: 1,
            facetFilters: { ...facetFilters, [facet.field]: next },
          });
        });
      } else {
        el.addEventListener("change", () => {
          if (programmaticUpdates.has(el)) return;
          const raw = (el as any).value;
          const values: string[] = Array.isArray(raw)
            ? raw
            : raw
              ? [String(raw)]
              : [];
          const { facetFilters } = getUrlState();
          updateUrlState({
            page: 1,
            facetFilters: { ...facetFilters, [facet.field]: values },
          });
        });

        el.addEventListener("wa-clear", () => {
          if (programmaticUpdates.has(el)) return;
          const { facetFilters } = getUrlState();
          updateUrlState({
            page: 1,
            facetFilters: { ...facetFilters, [facet.field]: [] },
          });
        });
      }
    });
  }

  // -------------------------------------------------------------------------

  return { fields, render, sync, bindEvents };
}
