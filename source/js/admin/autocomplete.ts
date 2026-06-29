// ---------------------------------------------------------------------------
// Generic autocomplete widget — DOM-based, framework-free.
//
// Usage:
//   const ac = createAutocomplete({
//       input,
//       onSearch: async (q) => fetchResults(q),
//       renderItem: (item) => item.label,
//       onSelect: (item) => doSomething(item),
//   });
//   ac.destroy();
// ---------------------------------------------------------------------------

export interface AutocompleteOptions<T> {
    input: HTMLInputElement;
    onSearch(query: string): Promise<T[]>;
    renderItem(item: T): string;
    onSelect(item: T): void;
    minChars?: number;
    debounceMs?: number;
    noResultsText?: string;
    loadingText?: string;
}

export interface Autocomplete {
    destroy(): void;
}

export function createAutocomplete<T>(options: AutocompleteOptions<T>): Autocomplete {
    const {
        input,
        onSearch,
        renderItem,
        onSelect,
        minChars    = 2,
        debounceMs  = 250,
        noResultsText = 'No results found.',
        loadingText   = 'Searching…',
    } = options;

    let items: T[]                              = [];
    let activeIndex                             = -1;
    let isOpen                                  = false;
    let debounceTimer: ReturnType<typeof setTimeout>;
    let latestRequest                           = 0;

    // ── Dropdown container ────────────────────────────────────────────────────

    const dropdownId = `ts-ac-${Math.random().toString(36).slice(2, 8)}`;
    const dropdown = document.createElement('div');
    dropdown.id        = dropdownId;
    dropdown.className = 'ts-ac';
    dropdown.setAttribute('role', 'listbox');
    dropdown.hidden = true;

    if (!input.id) input.id = `ts-ac-input-${Math.random().toString(36).slice(2, 8)}`;
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', dropdownId);
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('autocomplete', 'off');

    const parent = input.parentElement ?? document.body;
    parent.appendChild(dropdown);

    // ── Open / close ──────────────────────────────────────────────────────────

    function open(): void {
        if (isOpen) return;
        isOpen = true;
        dropdown.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function close(): void {
        if (!isOpen) return;
        isOpen = false;
        activeIndex = -1;
        dropdown.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    // ── Render ────────────────────────────────────────────────────────────────

    function renderDropdown(state: 'loading' | 'empty' | 'results'): void {
        if (state === 'loading') {
            dropdown.innerHTML = `<div class="ts-ac__state">${loadingText}</div>`;
            open();
            return;
        }
        if (state === 'empty') {
            dropdown.innerHTML = `<div class="ts-ac__state">${noResultsText}</div>`;
            open();
            return;
        }

        dropdown.innerHTML = items.map((item, i) => `
            <button type="button" class="ts-ac__item" role="option" id="${dropdownId}-item-${i}" aria-selected="false" data-index="${i}">
                ${renderItem(item)}
            </button>
        `).join('');

        open();
    }

    function setActive(index: number): void {
        activeIndex = index;
        dropdown.querySelectorAll<HTMLElement>('.ts-ac__item').forEach((el, i) => {
            const on = i === index;
            el.classList.toggle('is-active', on);
            el.setAttribute('aria-selected', String(on));
        });
        const el = document.getElementById(`${dropdownId}-item-${index}`);
        if (el) input.setAttribute('aria-activedescendant', el.id);
    }

    // ── Search ────────────────────────────────────────────────────────────────

    async function runSearch(query: string): Promise<void> {
        if (query.trim().length < minChars) { close(); return; }

        const requestId = ++latestRequest;
        renderDropdown('loading');

        try {
            const results = await onSearch(query);
            if (requestId !== latestRequest) return;
            items = results;
            if (!items.length) { renderDropdown('empty'); return; }
            renderDropdown('results');
        } catch {
            close();
        }
    }

    // ── Events ────────────────────────────────────────────────────────────────

    function onInput(): void {
        latestRequest++;
        clearTimeout(debounceTimer);
        if (!input.value.trim()) { close(); return; }
        debounceTimer = setTimeout(() => void runSearch(input.value), debounceMs);
    }

    function onKeyDown(e: KeyboardEvent): void {
        if (!isOpen) return;
        const count = items.length;
        if (!count) return;

        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((activeIndex + 1) % count); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); setActive((activeIndex - 1 + count) % count); }
        if (e.key === 'Escape')    { close(); input.focus(); }
        if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
            e.preventDefault();
            onSelect(items[activeIndex]);
            close();
        }
    }

    function onDocumentClick(e: MouseEvent): void {
        if (!isOpen) return;
        if (!input.contains(e.target as Node) && !dropdown.contains(e.target as Node)) close();
    }

    function onDropdownClick(e: MouseEvent): void {
        const btn = (e.target as HTMLElement).closest<HTMLElement>('.ts-ac__item');
        if (!btn) return;
        const index = Number(btn.dataset.index);
        if (items[index] !== undefined) { onSelect(items[index]); close(); }
    }

    input.addEventListener('input', onInput);
    input.addEventListener('keydown', onKeyDown);
    dropdown.addEventListener('click', onDropdownClick);
    document.addEventListener('click', onDocumentClick);

    return {
        destroy() {
            input.removeEventListener('input', onInput);
            input.removeEventListener('keydown', onKeyDown);
            dropdown.removeEventListener('click', onDropdownClick);
            document.removeEventListener('click', onDocumentClick);
            dropdown.remove();
        },
    };
}
