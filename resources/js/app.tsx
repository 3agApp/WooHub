import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { registerFlashToastListener } from '@/components/flash-toast-listener';
import { ThemeProvider } from '@/components/theme-provider';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

registerFlashToastListener();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ThemeProvider
                attribute="class"
                defaultTheme="system"
                enableSystem
                disableTransitionOnChange
            >
                <TooltipProvider>
                    <App {...props} />
                    <Toaster richColors />
                </TooltipProvider>
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
