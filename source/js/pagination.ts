// ---------------------------------------------------------------------------
// Pagination renderer
// Builds a responsive wa-button–based pagination bar from Typesense totals.
// Templates are read from <template data-pagination-tmpl="…"> elements in
// the page so markup stays server-side in search.blade.php.
// ---------------------------------------------------------------------------

type OnPageChange = (page: number) => void;

/**
 * Returns the page numbers (and `null` for ellipsis gaps) to render.
 * The window of pages shown around the current page is controlled by
 * `delta` – use a smaller value on narrow viewports.
 */
function buildPageRange(
  current: number,
  total: number,
  delta = 2,
): (number | null)[] {
  const range: (number | null)[] = [];

  // Always include first and last; build the inner window.
  const left = Math.max(2, current - delta);
  const right = Math.min(total - 1, current + delta);

  // First page
  range.push(1);

  // Leading ellipsis
  if (left > 2) range.push(null);

  // Inner window
  for (let p = left; p <= right; p++) range.push(p);

  // Trailing ellipsis
  if (right < total - 1) range.push(null);

  // Last page (only if more than 1 page total)
  if (total > 1) range.push(total);

  return range;
}

/**
 * Clones the first child element of a <template data-pagination-tmpl="name">.
 * Returns null if the template is not found.
 */
function cloneTemplate(name: string): HTMLElement | null {
  const tmpl = document.querySelector<HTMLTemplateElement>(
    `[data-pagination-tmpl="${name}"]`,
  );
  if (!tmpl) return null;
  return (
    (tmpl.content.firstElementChild?.cloneNode(true) as HTMLElement) ?? null
  );
}

export function renderPagination(
  el: HTMLElement,
  totalFound: number,
  perPage: number,
  currentPage: number,
  onPageChange: OnPageChange,
): void {
  const totalPages = Math.ceil(totalFound / perPage);

  // Hide pagination when there is only one page.
  if (totalPages <= 1) {
    el.innerHTML = "";
    return;
  }

  const prevDisabled = currentPage <= 1;
  const nextDisabled = currentPage >= totalPages;

  // -----------------------------------------------------------------------
  // Compact "X / Y" view shown on narrow screens via CSS only.
  // -----------------------------------------------------------------------
  const compact = document.createElement("span");
  compact.className = "ts-pagination__compact";

  const prevCompact = cloneTemplate("prev");
  if (prevCompact) {
    prevCompact.dataset.page = String(currentPage - 1);
    if (prevDisabled) prevCompact.setAttribute("disabled", "");
    compact.appendChild(prevCompact);
  }

  const label = cloneTemplate("compact-label");
  if (label) {
    label.textContent = `${currentPage} / ${totalPages}`;
    compact.appendChild(label);
  }

  const nextCompact = cloneTemplate("next");
  if (nextCompact) {
    nextCompact.dataset.page = String(currentPage + 1);
    if (nextDisabled) nextCompact.setAttribute("disabled", "");
    compact.appendChild(nextCompact);
  }

  // -----------------------------------------------------------------------
  // Full numbered view hidden on narrow screens via CSS only.
  // -----------------------------------------------------------------------
  const full = document.createElement("span");
  full.className = "ts-pagination__full";

  const prevFull = cloneTemplate("prev");
  if (prevFull) {
    prevFull.dataset.page = String(currentPage - 1);
    if (prevDisabled) prevFull.setAttribute("disabled", "");
    full.appendChild(prevFull);
  }

  const pages = buildPageRange(currentPage, totalPages);
  pages.forEach((p) => {
    if (p === null) {
      const ellipsis = cloneTemplate("ellipsis");
      if (ellipsis) full.appendChild(ellipsis);
      return;
    }
    const isActive = p === currentPage;
    const pageBtn = cloneTemplate(isActive ? "page-active" : "page");
    if (pageBtn) {
      pageBtn.dataset.page = String(p);
      pageBtn.textContent = String(p);
      full.appendChild(pageBtn);
    }
  });

  const nextFull = cloneTemplate("next");
  if (nextFull) {
    nextFull.dataset.page = String(currentPage + 1);
    if (nextDisabled) nextFull.setAttribute("disabled", "");
    full.appendChild(nextFull);
  }

  // Assemble and mount.
  const nav = document.createElement("nav");
  nav.className = "ts-pagination";
  nav.setAttribute(
    "aria-label",
    window.typesenseI18n?.paginationLabel ?? "Search result pages",
  );
  nav.appendChild(compact);
  nav.appendChild(full);

  // Single delegated click listener on the nav element (not the outer
  // container) so it is discarded along with the nav on each re-render and
  // does not accumulate across renderPagination calls.
  nav.addEventListener("click", (e) => {
    const btn = (e.target as Element).closest<HTMLElement>("[data-page]");
    if (!btn || btn.hasAttribute("disabled")) return;
    const page = parseInt(btn.dataset.page ?? "", 10);
    if (!isNaN(page) && page >= 1 && page <= totalPages) {
      onPageChange(page);
    }
  });

  el.innerHTML = "";
  el.appendChild(nav);
}
