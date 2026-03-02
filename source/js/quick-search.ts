// ---------------------------------------------------------------------------
// Quick search – instant autocomplete for arbitrary input fields
// ---------------------------------------------------------------------------

import { createClient } from "./client";
import type { TypesenseSearchConfig, SearchHit } from "./types";

interface QuickSearchConfig {
  selectors: string[];
}

declare global {
  interface Window {
    typesenseQuickSearchConfig?: QuickSearchConfig;
  }
}

const DEFAULT_HITS = 5;
const ITEM_CLASS = "ts-qs-item";
const FOOTER_CLASS = "ts-qs-footer";

// ---------------------------------------------------------------------------
// Positioning helper (fixed, using viewport coords)
// ---------------------------------------------------------------------------

function positionDropdown(anchor: HTMLElement, dropdown: HTMLElement): void {
  const rect = anchor.getBoundingClientRect();
  dropdown.style.top = `${rect.bottom + 4}px`;
  dropdown.style.left = `${rect.left}px`;
  dropdown.style.width = `${rect.width}px`;
}

// ---------------------------------------------------------------------------
// Per-input instance
// ---------------------------------------------------------------------------

function attachQuickSearch(
  client: ReturnType<typeof createClient>,
  config: TypesenseSearchConfig,
  hitsPerPage: number,
  anchor: HTMLElement,
  input: HTMLInputElement,
): void {
  if (!client) return;

  // Append dropdown to <body> so it escapes any overflow:hidden ancestor
  const dropdown = document.createElement("div");
  const dropdownId = `ts-qs-dropdown-${Math.random().toString(36).slice(2, 8)}`;
  dropdown.className = "ts-quick-search";
  dropdown.id = dropdownId;
  dropdown.setAttribute("role", "listbox");
  dropdown.setAttribute("aria-label", "Quick search results");
  dropdown.setAttribute("aria-live", "polite");
  dropdown.hidden = true;
  document.body.appendChild(dropdown);

  // ARIA wiring
  if (!input.id)
    input.id = `ts-qs-input-${Math.random().toString(36).slice(2, 8)}`;
  input.setAttribute("aria-autocomplete", "list");
  input.setAttribute("aria-controls", dropdownId);
  input.setAttribute("aria-expanded", "false");
  input.setAttribute("autocomplete", "off");

  let activeIndex = -1;
  let currentHits: SearchHit[] = [];
  let isOpen = false;
  let debounceTimer: ReturnType<typeof setTimeout>;
  let lastSearchedQuery = "";
  let footerEl: HTMLAnchorElement | null = null;

  // ── Build search-page URL (mirrors form GET submission) ────────────────────────────

  function buildSearchUrl(): string {
    const form = input.closest("form");
    if (form && (form.method || "get").toLowerCase() === "get") {
      const url = new URL(
        form.action || window.location.href,
        window.location.href,
      );
      url.search = "";
      new FormData(form).forEach((value, key) =>
        url.searchParams.append(key, String(value)),
      );
      return url.toString();
    }
    // Fallback: append ?s= to the current URL
    const url = new URL(window.location.href);
    url.searchParams.set("s", input.value);
    return url.toString();
  }

  // ── Open / close ─────────────────────────────────────────────────────────

  function open(): void {
    if (isOpen) return;
    isOpen = true;
    dropdown.hidden = false;
    input.setAttribute("aria-expanded", "true");
    positionDropdown(anchor, dropdown);
  }

  function close(): void {
    if (!isOpen) return;
    isOpen = false;
    activeIndex = -1;
    dropdown.hidden = true;
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
  }

  // ── Active item ──────────────────────────────────────────────────────────

  function setActive(index: number): void {
    const items = dropdown.querySelectorAll<HTMLElement>(`.${ITEM_CLASS}`);
    const isFooter = footerEl !== null && index === currentHits.length;

    items.forEach((item, i) => {
      const active = !isFooter && i === index;
      item.classList.toggle("is-active", active);
      item.setAttribute("aria-selected", String(active));
    });

    if (footerEl) {
      footerEl.classList.toggle("is-active", isFooter);
      footerEl.setAttribute("aria-selected", String(isFooter));
    }

    activeIndex = index;

    if (isFooter && footerEl) {
      input.setAttribute("aria-activedescendant", footerEl.id);
      footerEl.scrollIntoView({ block: "nearest" });
    } else {
      const activeItem = items[index];
      if (activeItem) {
        input.setAttribute("aria-activedescendant", activeItem.id);
        activeItem.scrollIntoView({ block: "nearest" });
      } else {
        input.removeAttribute("aria-activedescendant");
      }
    }
  }

  // ── Render results ────────────────────────────────────────────────────────

  function render(hits: SearchHit[]): void {
    dropdown.innerHTML = "";
    activeIndex = -1;
    footerEl = null;

    if (!hits.length) {
      close();
      return;
    }

    hits.forEach((hit, i) => {
      const doc = hit.document;
      const title = String(doc.title ?? "");
      const typeName = String(doc.type_name ?? "");
      const url = String(doc.url ?? "#");

      const item = document.createElement("a");
      item.className = ITEM_CLASS;
      item.id = `${dropdownId}-item-${i}`;
      item.href = url;
      item.tabIndex = -1;
      item.setAttribute("role", "option");
      item.setAttribute("aria-selected", "false");

      const nameEl = document.createElement("span");
      nameEl.className = "ts-qs-item__name";
      nameEl.textContent = title;

      const typeEl = document.createElement("span");
      typeEl.className = "ts-qs-item__type";
      typeEl.textContent = typeName;

      item.appendChild(nameEl);
      item.appendChild(typeEl);

      item.addEventListener("mouseenter", () => setActive(i));
      item.addEventListener("click", (e) => {
        e.preventDefault();
        window.location.href = url;
      });

      dropdown.appendChild(item);
    });

    // ── "See all results" footer ─────────────────────────────────────────────
    const footerLabel =
      window.typesenseQuickSearchI18n?.seeAllResults ?? "See all results";
    footerEl = document.createElement("a");
    footerEl.className = FOOTER_CLASS;
    footerEl.id = `${dropdownId}-footer`;
    footerEl.href = buildSearchUrl();
    footerEl.tabIndex = -1;
    footerEl.setAttribute("role", "option");
    footerEl.setAttribute("aria-selected", "false");
    footerEl.textContent = footerLabel;
    footerEl.addEventListener("mouseenter", () =>
      setActive(currentHits.length),
    );
    footerEl.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href = buildSearchUrl();
    });
    dropdown.appendChild(footerEl);

    open();
  }

  // ── Search ────────────────────────────────────────────────────────────────

  async function runSearch(query: string): Promise<void> {
    if (!query.trim()) {
      close();
      return;
    }
    try {
      // Cast: client is non-null after the guard at the top
      const response = await (client as NonNullable<typeof client>)
        .collections(config.collection)
        .documents()
        .search({
          q: query,
          query_by: "title,excerpt,content,extra_terms",
          per_page: hitsPerPage,
        });
      currentHits = (response.hits as SearchHit[]) ?? [];
      lastSearchedQuery = query;
      render(currentHits);
    } catch (e) {
      console.error("[TypesenseQuickSearch]", e);
      close();
    }
  }

  // ── Input events ──────────────────────────────────────────────────────────

  input.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    const q = input.value;
    if (!q.trim()) {
      lastSearchedQuery = "";
      close();
      return;
    }
    // Don't re-search (and reset activeIndex) if the query hasn't changed.
    // This handles browser autofill and programmatic value sets.
    if (q === lastSearchedQuery) return;
    const delay = config.debounce !== false ? (config.debounceDelay ?? 300) : 0;
    debounceTimer = setTimeout(() => runSearch(q), delay);
  });

  // ── Re-open on focus with cached results ─────────────────────────────────

  input.addEventListener("focus", () => {
    if (!isOpen && currentHits.length > 0) {
      open();
    }
  });

  // ── Keyboard navigation ───────────────────────────────────────────────────

  input.addEventListener("keydown", (e) => {
    if (!isOpen) return;
    const count = currentHits.length + (footerEl ? 1 : 0);
    if (!count) return;

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        setActive((activeIndex + 1) % count);
        break;

      case "ArrowUp":
        e.preventDefault();
        setActive((activeIndex - 1 + count) % count);
        break;

      case "Enter":
        if (footerEl && activeIndex === currentHits.length) {
          e.preventDefault();
          window.location.href = buildSearchUrl();
        } else if (activeIndex >= 0 && currentHits[activeIndex]) {
          e.preventDefault();
          window.location.href = String(
            currentHits[activeIndex].document.url ?? "#",
          );
        }
        // No activeIndex — let the event bubble so the form submits normally
        break;

      case "Escape":
        close();
        input.focus();
        break;
    }
  });

  // ── Tab lock (document-level capture) ────────────────────────────────────
  // When the dropdown is open, Tab / Shift+Tab must not leave the result list.

  function tabLock(e: KeyboardEvent): void {
    if (!isOpen || e.key !== "Tab") return;
    e.preventDefault();
    const count = currentHits.length + (footerEl ? 1 : 0);
    if (!count) return;
    const next = e.shiftKey
      ? (activeIndex - 1 + count) % count
      : (activeIndex + 1) % count;
    setActive(next);
    // Keep focus on the input; active item is reflected via aria-activedescendant
    input.focus();
  }

  document.addEventListener("keydown", tabLock, true);

  // ── Close on outside click ────────────────────────────────────────────────

  document.addEventListener("click", (e) => {
    if (!isOpen) return;
    const target = e.target as Node;
    if (!anchor.contains(target) && !dropdown.contains(target)) {
      close();
    }
  });

  // ── Close when focus leaves both anchor and dropdown ─────────────────────

  input.addEventListener("blur", (e) => {
    const to = (e as FocusEvent).relatedTarget as Node | null;
    if (!to || (!dropdown.contains(to) && !anchor.contains(to))) {
      // Delay so item click events fire before we close
      setTimeout(() => {
        if (!dropdown.contains(document.activeElement)) close();
      }, 150);
    }
  });

  // ── Reposition on scroll / resize ────────────────────────────────────────

  const reposition = () => {
    if (isOpen) positionDropdown(anchor, dropdown);
  };
  window.addEventListener("scroll", reposition, { passive: true });
  window.addEventListener("resize", reposition, { passive: true });
}

// ---------------------------------------------------------------------------
// Init
// ---------------------------------------------------------------------------

function init(): void {
  const config = window.typesenseConfig;
  const qsConfig = window.typesenseQuickSearchConfig;

  if (!config?.host || !config?.collection || !config?.searchKey) return;
  if (!qsConfig?.selectors?.length) return;

  const client = createClient(config);
  if (!client) return;

  const hitsPerPage = config.quickSearchHitsPerPage ?? DEFAULT_HITS;

  for (const selector of qsConfig.selectors) {
    const el = document.querySelector<HTMLElement>(selector);
    if (!el) continue;

    let anchor: HTMLElement;
    let input: HTMLInputElement | null;

    if (el instanceof HTMLInputElement) {
      anchor = el;
      input = el;
    } else {
      anchor = el;
      input = el.querySelector<HTMLInputElement>(
        'input[type="text"], input[type="search"], input:not([type])',
      );
    }

    if (!input) continue;
    attachQuickSearch(client, config, hitsPerPage, anchor, input);
  }
}

document.addEventListener("DOMContentLoaded", init);
