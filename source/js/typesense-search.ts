import "@awesome.me/webawesome/dist/components/button/button";
import "@awesome.me/webawesome/dist/components/icon/icon";
import "@awesome.me/webawesome/dist/components/input/input";
import "@awesome.me/webawesome/dist/components/select/select";

import { createClient } from "./client";
import { runSearch } from "./search";
import { getHitTemplates } from "./templates";
import { getUrlState, updateUrlState } from "./url-state";

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

  const triggerSearch = (): void => {
    runSearch(
      client,
      cfg.collection,
      getUrlState(),
      resultsEl!,
      templates,
      paginationEl,
      (page) => {
        updateUrlState({ page });
        triggerSearch();
        container.scrollIntoView({ behavior: "smooth", block: "start" });
      },
    );
  };

  let debounceTimer: ReturnType<typeof setTimeout>;

  const onInput = (value: string): void => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      updateUrlState({ query: value, page: 1 });
      triggerSearch();
    }, 50);
  };

  // Listen to value changes from the WebAwesome `wa-input` element.
  // The component emits native `input` and `change` events and exposes a
  // `value` property. Read that property when events fire.
  const readValue = (): string => (inputEl as any).value ?? "";
  inputEl.addEventListener("input", () => onInput(readValue()));
  inputEl.addEventListener("change", () => onInput(readValue()));
  // Some versions may emit a custom clear event; handle it defensively.
  inputEl.addEventListener("wa-clear", () => onInput(""));

  // Sync UI and results when the user navigates back/forward.
  window.addEventListener("popstate", () => {
    const state = getUrlState();
    (inputEl as any).value = state.query;
    triggerSearch();
  });

  // Seed UI from URL on page load and run initial search.
  const initialState = getUrlState();
  if (initialState.query) {
    (inputEl as any).value = initialState.query;
    triggerSearch();
  }
}

document.addEventListener("DOMContentLoaded", init);
