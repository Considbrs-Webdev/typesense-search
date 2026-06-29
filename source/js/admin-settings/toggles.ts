export function init(): void {
    // ── Section select navigation ─────────────────────────────────────────────

    const sectionSelect = document.querySelector<HTMLSelectElement>('[data-js-settings-section-select]');
    sectionSelect?.addEventListener('change', () => { window.location.href = sectionSelect.value; });

    // ── Password visibility toggles ───────────────────────────────────────────

    document.querySelectorAll<HTMLElement>('.ts-field__toggle-visibility').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target ?? '') as HTMLInputElement | null;
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            const eyeIcon    = btn.querySelector<HTMLElement>('.ts-icon-eye');
            const eyeOffIcon = btn.querySelector<HTMLElement>('.ts-icon-eye-off');
            if (eyeIcon)    eyeIcon.style.display    = isHidden ? 'none' : '';
            if (eyeOffIcon) eyeOffIcon.style.display = isHidden ? ''     : 'none';
        });
    });

    // ── Unsaved-changes guard ─────────────────────────────────────────────────

    const settingsForm = document.querySelector<HTMLFormElement>('#ts-tab-settings form');
    if (settingsForm) {
        let isDirty = false;
        settingsForm.addEventListener('change', () => { isDirty = true; });
        settingsForm.addEventListener('submit', () => { isDirty = false; });
        window.addEventListener('beforeunload', (e) => {
            if (!isDirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    }

    // ── Logging tab: clear log ────────────────────────────────────────────────

    const clearLogBtn = document.getElementById('ts-log-clear');
    if (clearLogBtn && document.getElementById('ts-tab-logging')) {
        const i18n = (window as Record<string, unknown> & { tsAdminI18n?: Record<string, string> }).tsAdminI18n ?? {};

        clearLogBtn.addEventListener('click', async () => {
            if (!confirm(i18n['confirmClearLog'] ?? 'Clear the indexing log?')) return;

            (clearLogBtn as HTMLButtonElement).disabled = true;
            clearLogBtn.classList.add('is-loading');

            try {
                const tsSettings = (window as Record<string, unknown> & { tsSettings?: Record<string, string> }).tsSettings;
                if (!tsSettings) return;
                const body = new URLSearchParams({ action: tsSettings['actionClearLog'] ?? '', nonce: tsSettings['nonceClearLog'] ?? '' });
                const resp = await fetch(tsSettings['ajaxUrl'] ?? '', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
                const data = await resp.json() as Record<string, unknown>;
                if (data.success) { window.location.reload(); return; }
                alert((data?.data as Record<string, string>)?.message ?? i18n['unknownError'] ?? 'Unknown error.');
            } finally {
                (clearLogBtn as HTMLButtonElement).disabled = false;
                clearLogBtn.classList.remove('is-loading');
            }
        });
    }

    // ── Truncator mode ────────────────────────────────────────────────────────

    const truncatorMode   = document.getElementById('ts-truncator-mode') as HTMLSelectElement | null;
    const truncatorHidden = document.getElementById('ts-truncator') as HTMLInputElement | null;
    const truncatorCustom = document.getElementById('ts-truncator-custom') as HTMLInputElement | null;
    const predefined: Record<string, string> = { brackets: '[...]', ellipsis: '…', none: 'none' };

    if (truncatorMode && truncatorHidden && truncatorCustom) {
        function updateTruncator(): void {
            if (truncatorMode!.value === 'custom') {
                truncatorCustom!.hidden  = false;
                truncatorHidden!.value   = truncatorCustom!.value || truncatorHidden!.value || predefined['brackets']!;
            } else {
                truncatorCustom!.hidden  = true;
                truncatorHidden!.value   = predefined[truncatorMode!.value] ?? predefined['brackets']!;
            }
        }
        truncatorMode.addEventListener('change', updateTruncator);
        truncatorCustom.addEventListener('input', () => { truncatorHidden!.value = truncatorCustom!.value || predefined['brackets']!; });
        updateTruncator();
    }

    // ── Debounce delay visibility ─────────────────────────────────────────────

    const debounceToggle     = document.getElementById('ts-debounce') as HTMLInputElement | null;
    const debounceDelayField = document.getElementById('ts-debounce-delay-field');
    debounceToggle?.addEventListener('change', function () { if (debounceDelayField) debounceDelayField.hidden = !this.checked; });

    // ── Statistics consent integration visibility ─────────────────────────────

    const requireConsent      = document.getElementById('ts-search-statistics-require-consent') as HTMLInputElement | null;
    const consentIntegration  = document.getElementById('ts-search-statistics-consent-integration');
    requireConsent?.addEventListener('change', function () { if (consentIntegration) consentIntegration.hidden = !this.checked; });
}
