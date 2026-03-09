import "@awesome.me/webawesome/dist/components/badge/badge";
import "@awesome.me/webawesome/dist/components/icon/icon";
import "@awesome.me/webawesome/dist/components/input/input";
import "@awesome.me/webawesome/dist/components/radio-group/radio-group";
import "@awesome.me/webawesome/dist/components/radio/radio";

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
  const loaderEl = container.querySelector<HTMLElement>("[data-js-loader]");
  const searchFormEl = container.querySelector<HTMLElement>(".ts-search");
  const searchHeadingEl = container.querySelector<HTMLElement>(
    ".ts-search__heading",
  );

  if (!inputEl) return;

  const client = createClient(config);
  const templates = getHitTemplates(container);
  if (!client) return;

  const facets = setupFacets(config.facets ?? []);
  const summaryEl = container.querySelector<HTMLElement>(
    "[data-js-search-summary]",
  );

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

  let isFirstSearch = true;
  const triggerSearch = (): void => {
    search(getUrlState()).then((facetData) => {
      facets.render(facetData);

      // Mirror count into the mobile sidebar panel
      const countEl = container.querySelector<HTMLElement>(
        "[data-js-search-results-count]",
      );
      const sidebarCountEl = container.querySelector<HTMLElement>(
        "[data-js-sidebar-results-count]",
      );
      if (sidebarCountEl) {
        sidebarCountEl.textContent = countEl?.textContent ?? "";
      }

      // Update the summary sentence below the search input.
      // Template (set via data-lang-template on the element from PHP):
      if (summaryEl) {
        const { query } = getUrlState();
        const countText = countEl?.textContent?.trim() ?? "";
        const template = summaryEl.dataset.langTemplate ?? "";

        if (query && countText && template) {
          summaryEl.innerHTML = template
            .replace("%1$s", query)
            .replace("%2$s", countText);
          summaryEl.hidden = false;
        } else {
          summaryEl.hidden = true;
        }
      }

      // Hide loader and show search interface after first search completes
      if (isFirstSearch && loaderEl) {
        loaderEl.hidden = true;
        if (searchFormEl) {
          searchFormEl.hidden = false;
        }
        if (searchHeadingEl) {
          searchHeadingEl.hidden = false;
        }
        isFirstSearch = false;
      }
    });
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
    if (config.debounce) {
      debounceTimer = setTimeout(
        () => updateUrlState({ query: value, page: 1 }),
        config.debounceDelay ?? 300,
      );
    } else {
      updateUrlState({ query: value, page: 1 });
    }
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

  // ── Breadcrumb navigation ────────────────────────────────────────────────
  // Breadcrumb segments inside hit cards carry data-href instead of being
  // real <a> tags (the whole card is already an <a>). We intercept clicks
  // and keyboard activation here, prevent the card link from firing, and
  // navigate to the breadcrumb URL instead.

  resultsEl.addEventListener("click", (e) => {
    const target = (e.target as Element).closest<HTMLElement>("[data-href]");
    if (!target) return;
    e.preventDefault();
    e.stopPropagation();
    const href = target.dataset.href;
    if (href) window.location.href = href;
  });

  resultsEl.addEventListener("keydown", (e) => {
    if (e.key !== "Enter" && e.key !== " ") return;
    const target = (e.target as Element).closest<HTMLElement>("[data-href]");
    if (!target) return;
    e.preventDefault();
    e.stopPropagation();
    const href = target.dataset.href;
    if (href) window.location.href = href;
  });

  // ── Mobile filter panel (CSS slide-in) ─────────────────────────────────────────

  const filterToggleEl = container.querySelector<HTMLElement>(
    "[data-js-filter-toggle]",
  );
  const filterSidebarEl = container.querySelector<HTMLElement>(
    "[data-js-filter-sidebar]",
  );
  const filterOverlayEl = document.querySelector<HTMLElement>(
    "[data-js-filter-overlay]",
  );
  const filterCloseEl = container.querySelector<HTMLElement>(
    "[data-js-filter-close]",
  );

  const openPanel = (): void => {
    filterSidebarEl?.classList.add("ts-filter-sidebar--open");
    filterOverlayEl?.classList.add("ts-filter-overlay--open");
    document.body.style.overflow = "hidden";
  };

  const closePanel = (): void => {
    filterSidebarEl?.classList.remove("ts-filter-sidebar--open");
    filterOverlayEl?.classList.remove("ts-filter-overlay--open");
    document.body.style.overflow = "";
  };

  filterToggleEl?.addEventListener("click", openPanel);
  filterCloseEl?.addEventListener("click", closePanel);
  filterOverlayEl?.addEventListener("click", closePanel);

  // ── Boot ─────────────────────────────────────────────────────────────────

  // Show loader initially
  if (loaderEl) {
    loaderEl.hidden = false;
  }

  // Hide search form and heading initially
  if (searchFormEl) {
    searchFormEl.hidden = true;
  }
  if (searchHeadingEl) {
    searchHeadingEl.hidden = true;
  }

  syncUiFromUrl();
  triggerSearch();
}

document.addEventListener("DOMContentLoaded", init);
