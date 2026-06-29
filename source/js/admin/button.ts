export function setButtonLoading(btn: HTMLButtonElement | HTMLElement, loading: boolean): void {
    (btn as HTMLButtonElement).disabled = loading;
    btn.classList.toggle('is-loading', loading);
}
