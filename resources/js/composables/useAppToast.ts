import ToastEventBus from 'primevue/toasteventbus';

type ToastSeverity = 'success' | 'error' | 'info' | 'warn';

type ToastOptions = {
    summary?: string;
    life?: number;
};

const defaultSummaries: Record<ToastSeverity, string> = {
    success: 'Success',
    error: 'Error',
    info: 'Info',
    warn: 'Notice',
};

export function showAppToast(message: string, severity: ToastSeverity = 'success', options: ToastOptions = {}) {
    const detail = String(message ?? '').trim();
    if (!detail) return;

    ToastEventBus.emit('add', {
        severity,
        summary: options.summary ?? defaultSummaries[severity],
        detail,
        life: options.life ?? 2200,
    });
}
