export type ToastType = 'success' | 'error' | 'info';

export interface Toast {
    show(message: string, type: ToastType): void;
    hide(): void;
}

export function createToast(container: HTMLElement): Toast {
    let timer: ReturnType<typeof setTimeout> | undefined;

    return {
        show(message, type) {
            clearTimeout(timer);
            container.textContent = message;
            container.className   = `ts-toast ts-toast--${type}`;
            container.hidden      = false;
            container.setAttribute('role', type === 'error' ? 'alert' : 'status');

            if (type !== 'error') {
                timer = setTimeout(() => { container.hidden = true; }, 4200);
            }
        },
        hide() {
            clearTimeout(timer);
            container.hidden = true;
        },
    };
}
