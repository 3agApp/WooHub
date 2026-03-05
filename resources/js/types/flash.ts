export type ToastType = 'success' | 'error' | 'warning' | 'info';

export type FlashToast = {
    type: ToastType;
    message: string;
    description?: string | null;
};

export type FlashData = {
    toast?: FlashToast;
};
