import { ajaxPost } from '../admin/ajax';
import { setButtonLoading } from '../admin/button';
import { esc, escAttr } from '../admin/dom';

declare const tsSettings: {
    actionGetFacetFields: string;
    nonceGetFacetFields: string;
} & Record<string, string>;

function i18n(key: string, fallback: string): string {
    return (window as Record<string, unknown> & { tsAdminI18n?: Record<string, string> }).tsAdminI18n?.[key] ?? fallback;
}

type FacetField = { name: string };

export function init(): void {
    if (!document.getElementById('ts-tab-advanced-settings')) return;

    const facetList   = document.getElementById('ts-facet-list');
    const addFacetBtn = document.getElementById('ts-add-facet');
    const facetNotice = document.getElementById('ts-facet-notice');
    const facetEmpty  = document.getElementById('ts-facet-empty');

    if (!facetList) return;

    let cachedFields: FacetField[] | null = null;
    let rowCounter = facetList.querySelectorAll('.ts-facet-row').length;

    function showFacetNotice(message: string, isError = false): void {
        if (!facetNotice) return;
        const msgEl = facetNotice.querySelector('.ts-facet-notice__message');
        if (msgEl) msgEl.textContent = message;
        facetNotice.className = 'ts-facet-notice ' + (isError ? 'is-error' : 'is-info');
        facetNotice.hidden = false;
    }

    function hideFacetNotice(): void {
        if (facetNotice) facetNotice.hidden = true;
    }

    function updateEmptyState(): void {
        if (!facetEmpty) return;
        facetEmpty.hidden = facetList!.querySelectorAll('.ts-facet-row').length > 0;
    }

    function updateDisabledOptions(): void {
        const selects = Array.from(facetList!.querySelectorAll<HTMLSelectElement>('.ts-facet-row__select'));
        const chosen  = selects.map((s) => s.value).filter(Boolean);
        selects.forEach((s) => {
            const current = s.value;
            Array.from(s.options).forEach((opt) => {
                if (!opt.value) { opt.disabled = false; return; }
                opt.disabled = opt.value !== current && chosen.includes(opt.value);
            });
        });
    }

    function populateSelect(select: HTMLSelectElement, fields: FacetField[]): void {
        const savedValue = select.dataset.savedValue ?? '';
        select.innerHTML = '';

        if (!fields.length) {
            const opt = document.createElement('option');
            opt.value    = '';
            opt.textContent = i18n('noFacetableFields', '— No facetable fields found —');
            opt.disabled = true;
            opt.selected = true;
            select.appendChild(opt);
            return;
        }

        fields.forEach((field) => {
            const opt = document.createElement('option');
            opt.value       = field.name;
            opt.textContent = field.name;
            if (field.name === savedValue) opt.selected = true;
            select.appendChild(opt);
        });

        if (!select.value && fields.length) select.value = fields[0].name;
        updateDisabledOptions();
    }

    async function fetchFacetFields(): Promise<FacetField[] | null> {
        if (cachedFields !== null) return cachedFields;

        let data: Record<string, unknown>;
        try {
            data = await ajaxPost({ action: tsSettings.actionGetFacetFields, nonce: tsSettings.nonceGetFacetFields }) as Record<string, unknown>;
        } catch (err) {
            data = { success: false, data: { message: i18n('requestFailed', 'Request failed: ') + (err as Error).message } };
        }

        if (!data.success) {
            showFacetNotice((data?.data as Record<string, string>)?.message ?? i18n('couldNotLoadFields', 'Could not load facetable fields.'), true);
            return null;
        }

        cachedFields = ((data.data as Record<string, unknown>)?.fields as FacetField[]) || [];
        hideFacetNotice();
        return cachedFields;
    }

    async function hydrateExistingRows(): Promise<void> {
        const selects = facetList!.querySelectorAll<HTMLSelectElement>('.ts-facet-row__select');
        if (!selects.length) return;

        facetList!.querySelectorAll<HTMLElement>('.ts-facet-row__spinner').forEach((s) => { s.style.display = 'block'; });
        const fields = await fetchFacetFields();
        facetList!.querySelectorAll<HTMLElement>('.ts-facet-row__spinner').forEach((s) => { s.style.display = ''; });

        if (!fields) return;

        selects.forEach((select) => {
            populateSelect(select, fields);
            select.disabled = false;
            select.addEventListener('change', updateDisabledOptions);
        });

        facetList!.querySelectorAll<HTMLSelectElement>('.ts-facet-row__display').forEach((d) => {
            d.value    = d.dataset.savedDisplay || 'dropdown';
            d.disabled = false;
        });

        updateDisabledOptions();
    }

    function appendFacetRow(fields: FacetField[]): void {
        const index   = rowCounter++;
        const chosen  = Array.from(facetList!.querySelectorAll<HTMLSelectElement>('.ts-facet-row__select')).map((s) => s.value).filter(Boolean);
        const initial = (fields.find((f) => !chosen.includes(f.name)) ?? fields[0]).name;
        const optName = `typesense_search_facets[${index}]`;

        const optionHtml = fields.map((f) => `<option value="${escAttr(f.name)}"${f.name === initial ? ' selected' : ''}>${esc(f.name)}</option>`).join('');

        const row = document.createElement('div');
        row.className    = 'ts-facet-row';
        row.dataset.index = String(index);
        row.innerHTML = `
            <div class="ts-facet-row__field">
                <label class="ts-facet-row__label">${esc(i18n('facetFieldLabel', 'Field'))}</label>
                <div class="ts-facet-row__select-wrap">
                    <select name="${escAttr(optName)}[field]" class="ts-facet-row__select">${optionHtml}</select>
                </div>
            </div>
            <div class="ts-facet-row__field">
                <label class="ts-facet-row__label">${esc(i18n('facetLabelLabel', 'Label'))}</label>
                <input type="text" name="${escAttr(optName)}[label]" value="" placeholder="${escAttr(i18n('facetLabelPlaceholder', 'e.g. Category'))}" class="regular-text ts-facet-row__input"/>
            </div>
            <div class="ts-facet-row__field">
                <label class="ts-facet-row__label">${esc(i18n('facetPlaceholderLabel', 'Placeholder'))}</label>
                <input type="text" name="${escAttr(optName)}[placeholder]" value="" placeholder="${escAttr(i18n('facetPlaceholderPh', 'e.g. All categories'))}" class="regular-text ts-facet-row__input"/>
            </div>
            <div class="ts-facet-row__field">
                <label class="ts-facet-row__label">${esc(i18n('facetDisplayAsLabel', 'Display as'))}</label>
                <select name="${escAttr(optName)}[display_as]" class="ts-facet-row__display">
                    <option value="dropdown" selected>${esc(i18n('facetOptDropdown', 'Dropdown'))}</option>
                    <option value="button_group">${esc(i18n('facetOptButtonGroup', 'Button group'))}</option>
                </select>
            </div>
            <button type="button" class="button ts-facet-row__remove" title="${escAttr(i18n('removeFacet', 'Remove facet'))}" aria-label="${escAttr(i18n('removeFacet', 'Remove facet'))}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>`;

        row.querySelector('.ts-facet-row__remove')!.addEventListener('click', () => { row.remove(); updateEmptyState(); });
        row.querySelector<HTMLSelectElement>('.ts-facet-row__select')!.addEventListener('change', updateDisabledOptions);

        facetList!.appendChild(row);
        updateDisabledOptions();
        updateEmptyState();
    }

    // Bind remove buttons on PHP-rendered rows
    facetList.querySelectorAll('.ts-facet-row__remove').forEach((btn) => {
        btn.addEventListener('click', () => { (btn.closest('.ts-facet-row') as HTMLElement)?.remove(); updateEmptyState(); });
    });

    addFacetBtn?.addEventListener('click', async () => {
        setButtonLoading(addFacetBtn, true);
        const fields = await fetchFacetFields();
        setButtonLoading(addFacetBtn, false);

        if (!fields) return;

        if (!fields.length) {
            showFacetNotice(i18n('noFieldsInSchema', 'No facetable fields found in the collection schema. Make sure the collection exists and has fields with facet: true.'), true);
            return;
        }

        const chosen = Array.from(facetList!.querySelectorAll<HTMLSelectElement>('.ts-facet-row__select')).map((s) => s.value).filter(Boolean);
        if (chosen.length >= fields.length) {
            showFacetNotice(i18n('allFieldsAdded', 'All facetable fields have already been added as facets.'), true);
            return;
        }

        appendFacetRow(fields);
    });

    void hydrateExistingRows();
}
