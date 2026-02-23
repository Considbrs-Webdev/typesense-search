import "@awesome.me/webawesome/dist/components/icon/icon";
import "@awesome.me/webawesome/dist/components/input/input";

import { createClient } from "./client";
import { createSearchRunner } from "./search";
import { getHitTemplates } from "./templates";
import { getUrlState, updateUrlState } from "./url-state";
import { setupFacets, programmaticUpdates } from "./facets";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function resolveResultsEl(container: HTMLElement): HTMLElement {
  let el = container.querySelector<HTMLElement>("[data-js-search-results]");
  if (!el) {
    el = document.createElement("div");
    el.className = "ts-search-results wa-stack";
    el.setAttribute("data-js-search-results", "");
    const form = container.querySelector("form");
    form ? form.after(el) : container.appendChild(el);
  }
  return el;
}

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------

function init(): void {
  const config = window.typesenseConfig;
  if (!config?.host || !config?.collection || !config?.searchKey) return;

  const container = document.querySelector<HTMLElement>(
    "[data-js-search-page-container]",
  );
  if (!container) return;

  const inputEl = container.querySelector<HTMLElement>(
    "[data-js-search-page-search-input]",
  );
  const sortEl = container.querySelector<HTMLElement>("[data-js-sort]");
  const paginationEl = container.querySelector<HTMLElement>(
    "[data-js-search-pagination]",
  );
  const resultsEl = resolveResultsEl(container);

  if (!inputEl) return;

  const client = createClient(config);
  const templates = getHitTemplates(container);
  if (!client) return;

  const facets = setupFacets(config.facets ?? []);

  // ── Search ───────────────────────────────────────────────────────────────

  const search = createSearchRunner(
    client,
    config,
    resultsEl,
    templates,
    paginationEl,
    facets.fields,
    (page) => {
      updateUrlState({ page });
      container.scrollIntoView({ behavior: "smooth", block: "start" });
    },
  );

  const triggerSearch = (): void => {
    search(getUrlState()).then(facets.render);
  };

  // ── URL → UI sync ────────────────────────────────────────────────────────

  const syncUiFromUrl = (): void => {
    const { query, sort, facetFilters } = getUrlState();

    (inputEl as any).value = query;

    if (sortEl) {
      programmaticUpdates.add(sortEl);
      requestAnimationFrame(() => {
        (sortEl as any).value = sort || "relevance";
        requestAnimationFrame(() => programmaticUpdates.delete(sortEl));
      });
    }

    facets.sync(facetFilters);
  };

  // ── Event listeners ──────────────────────────────────────────────────────

  facets.bindEvents();

  let debounceTimer: ReturnType<typeof setTimeout>;
  const onInput = (value: string): void => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(
      () => updateUrlState({ query: value, page: 1 }),
      100,
    );
  };
  inputEl.addEventListener("input", () =>
    onInput((inputEl as any).value ?? ""),
  );
  inputEl.addEventListener("change", () =>
    onInput((inputEl as any).value ?? ""),
  );
  inputEl.addEventListener("wa-clear", () => onInput(""));

  if (sortEl) {
    sortEl.addEventListener("change", () => {
      if (programmaticUpdates.has(sortEl)) return;
      updateUrlState({ sort: (sortEl as any).value || "relevance", page: 1 });
    });
  }

  window.addEventListener("urlstatechange", () => {
    syncUiFromUrl();
    triggerSearch();
  });
  window.addEventListener("popstate", () => {
    syncUiFromUrl();
    triggerSearch();
  });

  // ── Boot ─────────────────────────────────────────────────────────────────

  syncUiFromUrl();
  triggerSearch();
}

document.addEventListener("DOMContentLoaded", init);
