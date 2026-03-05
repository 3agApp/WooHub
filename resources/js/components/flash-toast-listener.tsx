import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import type { FlashData, FlashToast, ToastType } from '@/types';

declare global {
    interface Window {
        __flashToastCleanup?: () => void;
    }
}

function showToast(data: FlashToast): void {
    const toastFn: Record<ToastType, typeof toast.success> = {
        success: toast.success,
        error: toast.error,
        warning: toast.warning,
        info: toast.info,
    };

    toastFn[data.type](data.message, {
        description: data.description,
    });
}

function isToastType(value: unknown): value is ToastType {
    return (
        value === 'success' ||
        value === 'error' ||
        value === 'warning' ||
        value === 'info'
    );
}

function isFlashToast(value: unknown): value is FlashToast {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    if (!isToastType(record.type) || typeof record.message !== 'string') {
        return false;
    }

    if (
        record.description !== undefined &&
        record.description !== null &&
        typeof record.description !== 'string'
    ) {
        return false;
    }

    return true;
}

export function registerFlashToastListener(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.__flashToastCleanup?.();

    window.__flashToastCleanup = router.on('flash', (event) => {
        const flash = event.detail.flash as FlashData;

        if (isFlashToast(flash.toast)) {
            showToast(flash.toast);
        }
    });
}
