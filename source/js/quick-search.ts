// ---------------------------------------------------------------------------
// Quick search – instant autocomplete for arbitrary input fields
// ---------------------------------------------------------------------------

import { createClient } from "./client";
import type { TypesenseSearchConfig, SearchHit } from "./types";

interface QuickSearchSelectorEntry {
  selector: string;
  sibling: boolean;
}

interface QuickSearchConfig {
  selectors: QuickSearchSelectorEntry[];
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

// Sibling mode: position relative to the nearest positioned ancestor (parent).
function positionDropdownSibling(
  anchor: HTMLElement,
  dropdown: HTMLElement,
): void {
  const parent = dropdown.parentElement as HTMLElement;
  const anchorRect = anchor.getBoundingClientRect();
  const parentRect = parent.getBoundingClientRect();
  dropdown.style.top = `${
    anchorRect.bottom - parentRect.top + parent.scrollTop + 4
  }px`;
  dropdown.style.left = `${
    anchorRect.left - parentRect.left + parent.scrollLeft
  }px`;
  dropdown.style.width = `${anchorRect.width}px`;
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
  sibling: boolean = false,
): void {
  if (!client) return;

  const dropdown = document.createElement("div");
  const dropdownId = `ts-qs-dropdown-${Math.random().toString(36).slice(2, 8)}`;
  dropdown.className = "ts-quick-search";
  dropdown.id = dropdownId;
  dropdown.setAttribute("role", "listbox");
  dropdown.setAttribute("aria-label", "Quick search results");
  dropdown.setAttribute("aria-live", "polite");
  dropdown.hidden = true;

  if (sibling) {
    // Inject as a sibling so the dropdown inherits the parent element's
    // stacking context (useful when the input is inside a dialog where
    // z-index alone cannot place the dropdown above the overlay).
    const parent = anchor.parentElement ?? document.body;
    if (getComputedStyle(parent).position === "static") {
      (parent as HTMLElement).style.position = "relative";
    }
    dropdown.classList.add("ts-quick-search--sibling");
    parent.appendChild(dropdown);
  } else {
    // Default: append to <body> so it escapes any overflow:hidden ancestor.
    document.body.appendChild(dropdown);
  }

  // ARIA wiring
  if (!input.id)
    input.id = `ts-qs-input-${Math.random().toString(36).slice(2, 8)}`;
  input.setAttribute("aria-autocomplete", "list");
  input.setAttribute("aria-controls", dropdownId);
  input.setAttribute("aria-expanded", "false");
  input.setAttribute("autocomplete", "off");

  // ── Virtual-window state ─────────────────────────────────────────────────

  let activeIndex = -1;
  let currentHits: SearchHit[] = [];
  let isOpen = false;
  let debounceTimer: ReturnType<typeof setTimeout>;
  let lastSearchedQuery = "";

  // Windowing
  let windowStart = 0; // index of first visible hit in the current slice
  let visibleCount = 0; // how many hits are shown at once (calculated from viewport)
  let itemHeight = 0; // px height of a single row (measured once from the DOM)

  // ── Sub-elements ─────────────────────────────────────────────────────────

  // .ts-qs-results holds only the currently visible slice; its height is set
  // explicitly so the dropdown never changes size as the window slides.
  const resultsEl = document.createElement("div");
  resultsEl.className = "ts-qs-results";
  dropdown.appendChild(resultsEl);

  let footerEl: HTMLAnchorElement | null = null;

  // Custom scrollbar track (lives inside resultsEl so it only spans the list area)
  const trackEl = document.createElement("div");
  trackEl.className = "ts-qs-track";
  trackEl.setAttribute("aria-hidden", "true");
  trackEl.hidden = true;

  const thumbEl = document.createElement("div");
  thumbEl.className = "ts-qs-track__thumb";
  trackEl.appendChild(thumbEl);
  resultsEl.appendChild(trackEl);

  // ── Build search-page URL (mirrors form GET submission) ───────────────────

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
    if (sibling) {
      positionDropdownSibling(anchor, dropdown);
    } else {
      positionDropdown(anchor, dropdown);
    }
  }

  function close(): void {
    if (!isOpen) return;
    isOpen = false;
    activeIndex = -1;
    dropdown.hidden = true;
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
  }

  // ── Build a single hit element ────────────────────────────────────────────
  // Item IDs are based on the hit's absolute index so they stay stable as
  // the window slides and items are re-rendered.

  function buildItem(hit: SearchHit, hitIndex: number): HTMLAnchorElement {
    const doc = hit.document;
    const title = String(doc.title ?? "");
    const typeName = String(doc.type_name ?? "");
    const url = String(doc.url ?? "#");

    const item = document.createElement("a");
    item.className = ITEM_CLASS;
    item.id = `${dropdownId}-item-${hitIndex}`;
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

    item.addEventListener("mouseenter", () => setActive(hitIndex));
    item.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href = url;
    });

    return item;
  }

  // ── Update scrollbar track ────────────────────────────────────────────────

  function updateTrack(): void {
    const total = currentHits.length;
    if (!total || visibleCount >= total) {
      trackEl.hidden = true;
      return;
    }
    trackEl.hidden = false;
    const trackH = resultsEl.offsetHeight;
    const ratio = visibleCount / total;
    const thumbH = Math.max(16, Math.round(ratio * trackH));
    const maxTop = trackH - thumbH;
    const thumbTop =
      total - visibleCount > 0
        ? Math.round((windowStart / (total - visibleCount)) * maxTop)
        : 0;
    thumbEl.style.height = `${thumbH}px`;
    thumbEl.style.top = `${thumbTop}px`;
  }

  // ── Render only the visible window slice ──────────────────────────────────

  function renderWindow(): void {
    // Remove all item elements while keeping the track element.
    Array.from(resultsEl.children).forEach((child) => {
      if (!child.classList.contains("ts-qs-track")) child.remove();
    });

    if (!currentHits.length || !visibleCount) return;

    const end = Math.min(windowStart + visibleCount, currentHits.length);
    for (let i = windowStart; i < end; i++) {
      resultsEl.insertBefore(buildItem(currentHits[i], i), trackEl);
    }

    // Re-apply active state if the active hit is inside this window.
    if (activeIndex >= windowStart && activeIndex < end) {
      const el = document.getElementById(`${dropdownId}-item-${activeIndex}`);
      if (el) {
        el.classList.add("is-active");
        el.setAttribute("aria-selected", "true");
      }
    }

    updateTrack();
  }

  // ── Calibrate: measure layout, compute visibleCount, lock height ──────────
  // Called once after first paint and again on viewport resize.

  function calibrate(): void {
    if (!currentHits.length || !footerEl) return;

    // Measure one item if we don't have a reading yet.
    const firstItem = resultsEl.querySelector<HTMLElement>(`.${ITEM_CLASS}`);
    if (firstItem && firstItem.offsetHeight > 0) {
      itemHeight = firstItem.offsetHeight;
    }
    if (!itemHeight) return;

    const footerHeight = footerEl.offsetHeight;
    const anchorRect = anchor.getBoundingClientRect();
    const available = window.innerHeight - anchorRect.bottom - 8;
    const spaceForItems = Math.max(itemHeight, available - footerHeight);

    const newVisible = Math.min(
      currentHits.length,
      Math.max(1, Math.floor(spaceForItems / itemHeight)),
    );

    // Clamp windowStart so the window does not overshoot after a resize.
    windowStart = Math.min(
      windowStart,
      Math.max(0, currentHits.length - newVisible),
    );

    visibleCount = newVisible;

    // Lock the dropdown to an exact pixel height so it never jumps.
    const resultsH = visibleCount * itemHeight;
    resultsEl.style.height = `${resultsH}px`;
    dropdown.style.height = `${resultsH + footerHeight}px`;

    renderWindow();
  }

  // ── Set the active (highlighted) item ────────────────────────────────────

  function setActive(index: number): void {
    const isFooter = footerEl !== null && index === currentHits.length;
    activeIndex = index;

    if (!isFooter) {
      // Slide the window by exactly one row if needed (never jump by more).
      if (index < windowStart) {
        windowStart = index;
        renderWindow();
      } else if (index >= windowStart + visibleCount) {
        windowStart = index - visibleCount + 1;
        renderWindow();
      } else {
        // Item is already in the window – just toggle classes.
        resultsEl
          .querySelectorAll<HTMLElement>(`.${ITEM_CLASS}`)
          .forEach((el) => {
            const on = el.id === `${dropdownId}-item-${index}`;
            el.classList.toggle("is-active", on);
            el.setAttribute("aria-selected", String(on));
          });
      }
    } else {
      // Footer active – clear all item highlights.
      resultsEl
        .querySelectorAll<HTMLElement>(`.${ITEM_CLASS}`)
        .forEach((el) => {
          el.classList.remove("is-active");
          el.setAttribute("aria-selected", "false");
        });
    }

    if (footerEl) {
      footerEl.classList.toggle("is-active", isFooter);
      footerEl.setAttribute("aria-selected", String(isFooter));
    }

    // ARIA activedescendant
    if (isFooter && footerEl) {
      input.setAttribute("aria-activedescendant", footerEl.id);
    } else {
      const el = document.getElementById(`${dropdownId}-item-${index}`);
      if (el) {
        input.setAttribute("aria-activedescendant", el.id);
      } else {
        input.removeAttribute("aria-activedescendant");
      }
    }

    updateTrack();
  }

  // ── Render results (called after each search) ─────────────────────────────

  function render(hits: SearchHit[]): void {
    windowStart = 0;
    activeIndex = -1;
    footerEl = null;
    dropdown.style.height = "";

    if (!hits.length) {
      close();
      return;
    }

    // ── Footer (always visible, lives outside resultsEl) ─────────────────────
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

    // Replace any existing footer.
    dropdown.querySelectorAll(`.${FOOTER_CLASS}`).forEach((el) => el.remove());
    dropdown.appendChild(footerEl);

    // Seed resultsEl with two items (when available) so item[0] is not
    // :last-of-type and its border-bottom is included in the measurement.
    Array.from(resultsEl.children).forEach((child) => {
      if (!child.classList.contains("ts-qs-track")) child.remove();
    });
    resultsEl.insertBefore(buildItem(hits[0], 0), trackEl);
    if (hits.length > 1) {
      resultsEl.insertBefore(buildItem(hits[1], 1), trackEl);
    }

    open();

    // Calibrate after layout is committed (needs offsetHeight).
    requestAnimationFrame(() => calibrate());
  }

  // ── Search ────────────────────────────────────────────────────────────────

  async function runSearch(query: string): Promise<void> {
    if (!query.trim()) {
      close();
      return;
    }
    try {
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
    if (q === lastSearchedQuery) return;
    const delay = config.debounce !== false ? (config.debounceDelay ?? 300) : 0;
    debounceTimer = setTimeout(() => runSearch(q), delay);
  });

  // ── Re-open on focus with cached results ─────────────────────────────────

  input.addEventListener("focus", () => {
    if (!isOpen && currentHits.length > 0) {
      open();
      requestAnimationFrame(() => calibrate());
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
        break;

      case "Escape":
        close();
        input.focus();
        break;
    }
  });

  // ── Tab lock (document-level capture) ────────────────────────────────────

  function tabLock(e: KeyboardEvent): void {
    if (!isOpen || e.key !== "Tab") return;
    e.preventDefault();
    const count = currentHits.length + (footerEl ? 1 : 0);
    if (!count) return;
    const next = e.shiftKey
      ? (activeIndex - 1 + count) % count
      : (activeIndex + 1) % count;
    setActive(next);
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
      setTimeout(() => {
        if (!dropdown.contains(document.activeElement)) close();
      }, 150);
    }
  });

  // ── Reposition + recalibrate on scroll / resize ───────────────────────────

  const reposition = () => {
    if (!isOpen) return;
    if (sibling) {
      positionDropdownSibling(anchor, dropdown);
    } else {
      positionDropdown(anchor, dropdown);
    }
    // Recalibrate because available height may have changed.
    calibrate();
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

  for (const entry of qsConfig.selectors) {
    const { selector, sibling } = entry;
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
    attachQuickSearch(client, config, hitsPerPage, anchor, input, sibling);
  }
}

document.addEventListener("DOMContentLoaded", init);
