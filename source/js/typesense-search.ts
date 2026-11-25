import "@awesome.me/webawesome/dist/components/badge/badge.js";
import "@awesome.me/webawesome/dist/components/icon/icon.js";
import "@awesome.me/webawesome/dist/components/input/input.js";
import "@awesome.me/webawesome/dist/components/radio-group/radio-group.js";
import "@awesome.me/webawesome/dist/components/radio/radio.js";

import { createClient } from "./typesense-search/client";
import { createSearchRunner } from "./typesense-search/search";
import { getHitTemplates } from "./typesense-search/templates";
import { getUrlState, updateUrlState } from "./typesense-search/url-state";
import { setupFacets, programmaticUpdates } from "./typesense-search/facets";
import { loadWebAwesomeLocale } from "./typesense-search/webawesome-locale";
import { createSearchStatisticsTracker } from "./typesense-search/search-statistics";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function escapeHtml(value: string): string {
  const el = document.createElement("div");
  el.textContent = value;
  return el.innerHTML;
}

function resolveResultsEl(container: HTMLElement): HTMLElement {
  let el = container.querySelector<HTMLElement>("[data-js-search-results]");
  if (!el) {
    el = document.createElement("ol");
    el.className = "ts-search-results wa-stack";
    el.setAttribute("data-js-search-results", "");
    el.setAttribute("role", "list");
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

  // If we cannot start, release the server-rendered loading state instead of
  // leaving the page stuck on the spinner.
  const release = (): void => {
    container.removeAttribute("aria-busy");
    container
      .querySelector<HTMLElement>("[data-js-loader]")
      ?.setAttribute("hidden", "");
  };

  const inputEl = container.querySelector<HTMLElement>(
    "[data-js-search-page-search-input]",
  );
  const sortEl = container.querySelector<HTMLElement>("[data-js-sort]");
  const paginationEl = container.querySelector<HTMLElement>(
    "[data-js-search-pagination]",
  );
  const resultsEl = resolveResultsEl(container);
  const loaderEl = container.querySelector<HTMLElement>("[data-js-loader]");

  if (!inputEl) return release();

  const client = createClient(config);
  const templates = getHitTemplates(container);
  if (!client) return release();
  const searchStatistics = createSearchStatisticsTracker(config.searchLogging);

  const facets = setupFacets(config.facets ?? []);
  const summaryEl = container.querySelector<HTMLElement>(
    "[data-js-search-summary]",
  );

  container.setAttribute("aria-busy", "true");

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
    (query, found) => searchStatistics.track(query, found, "regular"),
  );

  let isFirstSearch = true;

  const finishFirstLoad = (): void => {
    if (!isFirstSearch) return;
    if (loaderEl) loaderEl.hidden = true;
    container.removeAttribute("aria-busy");
    isFirstSearch = false;
  };

  const triggerSearch = (): void => {
    search(getUrlState())
      .then((facetData) => {
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
        if (summaryEl) {
          const { query } = getUrlState();
          const countText = countEl?.textContent?.trim() ?? "";
          const template = summaryEl.dataset.langTemplate ?? "";

          // Keep the live region in the DOM (never hidden) so that changes,
          // including "0 results", are announced by screen readers.
          const zeroText = (summaryEl.dataset.langPlural ?? "").replace(
            "%d",
            "0",
          );
          // An error message also leaves the count empty; don't report that as
          // "0 results".
          const hasError = !!resultsEl.querySelector(".ts-search-error");
          const resultText = hasError ? "" : countText || zeroText;

          if (query && resultText && template) {
            summaryEl.innerHTML = template
              .replace("%1$s", () => escapeHtml(query))
              .replace("%2$s", resultText);
          } else {
            summaryEl.textContent = "";
          }
        }

        finishFirstLoad();
      })
      .catch(() => {
        finishFirstLoad();
      });
  };

  // ── Document title ───────────────────────────────────────────────────────

  const titleConfig = config.documentTitle;
  const updateDocumentTitle = (): void => {
    if (!titleConfig?.template) return;
    const query = getUrlState().query.trim();
    document.title = query
      ? titleConfig.template.split("%s").join(query)
      : titleConfig.empty;
  };

  // ── Language (translate) links ───────────────────────────────────────────
  // The server HTML is cached without a search term, so translation links that
  // embed the page URL must be pointed at the current URL.

  const updateTranslateLinks = (): void => {
    document
      .querySelectorAll<HTMLAnchorElement>('a[href*="translate.google.com"]')
      .forEach((link) => {
        try {
          const url = new URL(link.href);
          if (!url.searchParams.has("u")) return;
          url.searchParams.set("u", window.location.href);
          link.href = url.toString();
        } catch {
          // Ignore malformed URLs.
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

  inputEl.closest("form")?.addEventListener("submit", (event) => {
    event.preventDefault();
    // Submit immediately, cancelling any pending search-as-you-type update.
    clearTimeout(debounceTimer);
    updateUrlState({ query: (inputEl as any).value ?? "", page: 1 });
  });

  if (sortEl) {
    sortEl.addEventListener("change", () => {
      if (programmaticUpdates.has(sortEl)) return;
      updateUrlState({ sort: (sortEl as any).value || "relevance", page: 1 });
    });
  }

  window.addEventListener("urlstatechange", () => {
    updateDocumentTitle();
    updateTranslateLinks();
    syncUiFromUrl();
    triggerSearch();
  });
  window.addEventListener("popstate", () => {
    updateDocumentTitle();
    updateTranslateLinks();
    syncUiFromUrl();
    triggerSearch();
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

  if (loaderEl) {
    loaderEl.hidden = false;
  }

  updateDocumentTitle();
  updateTranslateLinks();
  syncUiFromUrl();
  triggerSearch();
}

async function boot(): Promise<void> {
  await loadWebAwesomeLocale();
  init();
}

document.addEventListener("DOMContentLoaded", () => {
  void boot();
});
