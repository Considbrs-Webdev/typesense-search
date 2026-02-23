import "@awesome.me/webawesome/dist/components/button/button";
import "@awesome.me/webawesome/dist/components/icon/icon";
import "@awesome.me/webawesome/dist/components/input/input";
import "@awesome.me/webawesome/dist/components/select/select";
import "@awesome.me/webawesome/dist/components/option/option";

import { createClient } from "./client";
import { runSearch } from "./search";
import { getHitTemplates } from "./templates";
import { getUrlState, updateUrlState } from "./url-state";
import type { FacetCount } from "./types";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function escapeAttr(str: string): string {
  return str
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function escapeText(str: string): string {
  return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

/**
 * Elements currently being updated programmatically.
 * Handlers check this set to avoid treating programmatic value-changes as
 * user interactions (which would overwrite the URL state with stale data).
 */
const programmaticUpdates = new WeakSet<Element>();

/**
 * Re-populate a `<wa-select multiple>` element with fresh facet counts.
 * The currently selected values are preserved even if they are no longer
 * present in the returned counts (so the user can still deselect them).
 */
function renderFacetOptions(
  selectEl: Element,
  counts: FacetCount[],
  selectedValues: string[],
): void {
  // Include any selected values that have dropped out of the facet results
  // so the user can still deselect them.
  const countValues = new Set(counts.map((c) => c.value));
  const ghosts = selectedValues
    .filter((v) => !countValues.has(v))
    .map((v) => ({ value: v, count: 0 }));

  const allOptions = [...ghosts, ...counts];

  // Guard against wa-change/wa-clear firing during programmatic DOM updates.
  programmaticUpdates.add(selectEl);

  selectEl.innerHTML = allOptions
    .map(
      ({ value, count }) =>
        `<wa-option value="${escapeAttr(value)}">${escapeText(value)}${count > 0 ? ` (${count})` : ""}</wa-option>`,
    )
    .join("");

  // Re-apply selection after updating the DOM.
  // Wrapped in rAF so the web-component can register new child options first.
  requestAnimationFrame(() => {
    (selectEl as any).value = selectedValues;
    // Release the programmatic-update guard after the value assignment
    // has settled (one more rAF so any synchronous wa-change is already done).
    requestAnimationFrame(() => programmaticUpdates.delete(selectEl));
  });
}

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------

function init(): void {
  const cfg = window.typesenseConfig;
  if (!cfg?.host || !cfg?.collection || !cfg?.searchKey) {
    return;
  }

  const container = document.querySelector<HTMLElement>(
    "[data-js-search-page-container]",
  );
  if (!container) return;

  const inputEl = container.querySelector<HTMLElement>(
    "[data-js-search-page-search-input]",
  );
  if (!inputEl) return;

  const client = createClient(cfg);
  if (!client) return;

  const templates = getHitTemplates(container);

  // Use the existing results container from the markup, or create one after the form.
  let resultsEl = container.querySelector<HTMLElement>(
    "[data-js-search-results]",
  );
  if (!resultsEl) {
    resultsEl = document.createElement("div");
    resultsEl.className = "ts-search-results wa-stack";
    resultsEl.setAttribute("data-js-search-results", "");
    const form = container.querySelector("form");
    form ? form.after(resultsEl) : container.appendChild(resultsEl);
  }

  const paginationEl = container.querySelector<HTMLElement>(
    "[data-js-search-pagination]",
  );

  // Facet filter selects
  const departmentEl = container.querySelector<HTMLElement>(
    "[data-js-filter-department]",
  );
  const typeEl = container.querySelector<HTMLElement>("[data-js-filter-type]");
  const sortEl = container.querySelector<HTMLElement>("[data-js-sort]");

  // ---------------------------------------------------------------------------
  // URL → UI sync
  // Reads all current URL state and pushes values into the UI elements.
  // ---------------------------------------------------------------------------

  const syncUiFromUrl = (): void => {
    const state = getUrlState();

    (inputEl as any).value = state.query;

    if (sortEl) {
      programmaticUpdates.add(sortEl);
      requestAnimationFrame(() => {
        (sortEl as any).value = state.sort || "relevance";
        requestAnimationFrame(() => programmaticUpdates.delete(sortEl));
      });
    }

    // Facet selects are rebuilt after each search response, but we
    // pre-seed the selection here so it survives the async round-trip.
    if (departmentEl) {
      programmaticUpdates.add(departmentEl);
      requestAnimationFrame(() => {
        (departmentEl as any).value = state.facetFilters.top_most_parent ?? [];
        requestAnimationFrame(() => programmaticUpdates.delete(departmentEl));
      });
    }

    if (typeEl) {
      programmaticUpdates.add(typeEl);
      requestAnimationFrame(() => {
        (typeEl as any).value = state.facetFilters.type_name ?? [];
        requestAnimationFrame(() => programmaticUpdates.delete(typeEl));
      });
    }
  };

  // ---------------------------------------------------------------------------
  // Search trigger – always reads from URL state (URL is source of truth).
  // ---------------------------------------------------------------------------

  const triggerSearch = (): void => {
    const state = getUrlState();

    runSearch(
      client,
      cfg.collection,
      state,
      resultsEl!,
      templates,
      paginationEl,
      // Pagination: update URL only; the urlstatechange event fires the search.
      (page) => {
        updateUrlState({ page });
        container.scrollIntoView({ behavior: "smooth", block: "start" });
      },
    ).then((facetData) => {
      if (!facetData) {
        if (departmentEl) departmentEl.innerHTML = "";
        if (typeEl) typeEl.innerHTML = "";
        return;
      }

      const currentState = getUrlState();

      if (departmentEl) {
        renderFacetOptions(
          departmentEl,
          facetData.top_most_parent,
          currentState.facetFilters.top_most_parent ?? [],
        );
      }

      if (typeEl) {
        renderFacetOptions(
          typeEl,
          facetData.type_name,
          currentState.facetFilters.type_name ?? [],
        );
      }
    });
  };

  // ---------------------------------------------------------------------------
  // URL change handler – single place that runs search.
  // Covers both replaceState/pushState (urlstatechange) and back/forward
  // (popstate).
  // ---------------------------------------------------------------------------

  const onUrlChange = (): void => {
    syncUiFromUrl();
    triggerSearch();
  };

  window.addEventListener("urlstatechange", onUrlChange);
  window.addEventListener("popstate", onUrlChange);

  // ---------------------------------------------------------------------------
  // UI → URL only (no direct search calls).
  // ---------------------------------------------------------------------------

  // Text input – debounced.
  let debounceTimer: ReturnType<typeof setTimeout>;

  const readValue = (): string => (inputEl as any).value ?? "";

  const onInput = (value: string): void => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      updateUrlState({ query: value, page: 1 });
    }, 300);
  };

  inputEl.addEventListener("input", () => onInput(readValue()));
  inputEl.addEventListener("change", () => onInput(readValue()));
  inputEl.addEventListener("wa-clear", () => onInput(""));

  // Facet selects.
  const onFacetChange = (field: string, selectEl: Element): void => {
    if (programmaticUpdates.has(selectEl)) return;
    const raw = (selectEl as any).value;
    const values: string[] = Array.isArray(raw)
      ? raw
      : raw
        ? [String(raw)]
        : [];
    const currentFilters = getUrlState().facetFilters;
    updateUrlState({
      page: 1,
      facetFilters: { ...currentFilters, [field]: values },
    });
  };

  const onFacetClear = (field: string, selectEl: Element): void => {
    if (programmaticUpdates.has(selectEl)) return;
    const currentFilters = getUrlState().facetFilters;
    updateUrlState({
      page: 1,
      facetFilters: { ...currentFilters, [field]: [] },
    });
  };

  if (departmentEl) {
    departmentEl.addEventListener("wa-change", () =>
      onFacetChange("top_most_parent", departmentEl),
    );
    departmentEl.addEventListener("wa-clear", () =>
      onFacetClear("top_most_parent", departmentEl),
    );
  }

  if (typeEl) {
    typeEl.addEventListener("wa-change", () =>
      onFacetChange("type_name", typeEl),
    );
    typeEl.addEventListener("wa-clear", () =>
      onFacetClear("type_name", typeEl),
    );
  }

  // Sort select.
  if (sortEl) {
    sortEl.addEventListener("wa-change", () => {
      if (programmaticUpdates.has(sortEl)) return;
      const raw = (sortEl as any).value;
      const sort = raw ? String(raw) : "relevance";
      updateUrlState({ sort, page: 1 });
    });
  }

  // ---------------------------------------------------------------------------
  // Seed UI from URL and run initial search on page load.
  // ---------------------------------------------------------------------------

  syncUiFromUrl();
  triggerSearch();
}

document.addEventListener("DOMContentLoaded", init);
