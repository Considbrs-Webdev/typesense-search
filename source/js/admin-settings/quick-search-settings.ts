import { esc, escAttr } from '../admin/dom';

function i18n(key: string, fallback: string): string {
    return (window as Record<string, unknown> & { tsAdminI18n?: Record<string, string> }).tsAdminI18n?.[key] ?? fallback;
}

export function init(): void {
    const panel = document.getElementById('ts-tab-quick-search');
    if (!panel) return;

    const enabledToggle  = document.getElementById('ts-quick-search-enabled') as HTMLInputElement | null;
    const selectorsCard  = document.getElementById('ts-quick-search-selectors-card');
    const hitsField      = document.getElementById('ts-quick-search-hits-field');
    const selectorList   = document.getElementById('ts-qs-selector-list');
    const addSelectorBtn = document.getElementById('ts-qs-add-selector');
    const selectorEmpty  = document.getElementById('ts-qs-selector-empty');

    let counter = selectorList ? selectorList.querySelectorAll('.ts-qs-selector-row').length : 0;

    function syncVisibility(): void {
        if (!selectorsCard) return;
        selectorsCard.hidden = !enabledToggle?.checked;
        if (hitsField) hitsField.hidden = !enabledToggle?.checked;
    }

    function updateEmptyState(): void {
        if (!selectorEmpty || !selectorList) return;
        selectorEmpty.hidden = selectorList.querySelectorAll('.ts-qs-selector-row').length > 0;
    }

    function appendSelectorRow(): void {
        if (!selectorList) return;
        const index   = counter++;
        const optName = 'typesense_quick_search_selectors';

        const row = document.createElement('div');
        row.className    = 'ts-qs-selector-row';
        row.dataset.index = String(index);
        row.innerHTML = `
            <div class="ts-qs-selector-row__field" style="flex:1">
                <label class="ts-qs-selector-row__label">${esc(i18n('qsSelectorLabel', 'CSS selector'))}</label>
                <input type="text" name="${escAttr(optName)}[${index}][selector]" value="" placeholder="${escAttr(i18n('qsSelectorPlaceholder', 'e.g. .site-header input[type=search]'))}" class="regular-text ts-qs-selector-row__input" spellcheck="false"/>
            </div>
            <div class="ts-qs-selector-row__field">
                <label class="ts-qs-selector-row__label">${esc(i18n('qsPlacementLabel', 'Placement'))}</label>
                <select name="${escAttr(optName)}[${index}][sibling]" class="ts-qs-selector-row__sibling-select">
                    <option value="0">${esc(i18n('qsPlacementDefault', 'Default (body)'))}</option>
                    <option value="1">${esc(i18n('qsPlacementSibling', 'Sibling'))}</option>
                </select>
            </div>
            <div class="ts-qs-selector-row__field">
                <label class="ts-qs-selector-row__label" for="ts-qs-mobile-behavior-${index}">${esc(i18n('qsMobileBehaviorLabel', 'Mobile behavior'))}</label>
                <select id="ts-qs-mobile-behavior-${index}" name="${escAttr(optName)}[${index}][mobile_behavior]" class="ts-qs-selector-row__mobile-behavior-select">
                    <option value="regular">${esc(i18n('qsMobileBehaviorRegular', 'Regular behavior'))}</option>
                    <option value="overlay">${esc(i18n('qsMobileBehaviorOverlay', 'Open in modal'))}</option>
                </select>
            </div>
            <button type="button" class="button ts-qs-selector-row__remove" title="${escAttr(i18n('removeSelector', 'Remove selector'))}" aria-label="${escAttr(i18n('removeSelector', 'Remove selector'))}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>`;

        row.querySelector('.ts-qs-selector-row__remove')!.addEventListener('click', () => { row.remove(); updateEmptyState(); });
        selectorList.appendChild(row);
        updateEmptyState();
        (row.querySelector('input') as HTMLElement | null)?.focus();
    }

    enabledToggle?.addEventListener('change', syncVisibility);

    selectorList?.querySelectorAll('.ts-qs-selector-row__remove').forEach((btn) => {
        btn.addEventListener('click', () => { (btn.closest('.ts-qs-selector-row') as HTMLElement)?.remove(); updateEmptyState(); });
    });

    addSelectorBtn?.addEventListener('click', appendSelectorRow);

    syncVisibility();
    updateEmptyState();
}
