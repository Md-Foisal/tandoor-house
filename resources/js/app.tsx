import '@fontsource/rozha-one/latin-400.css';
import '@fontsource-variable/hanken-grotesk/wght.css';
import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import PublicLayout from '@/layouts/public-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import StyleguideLayout from '@/layouts/styleguide-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Tandoor House';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('styleguide/'):
                return StyleguideLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [PublicLayout, SettingsLayout];
            default:
                return PublicLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--ember)',
    },
});

// This will set light / dark mode on load...
initializeTheme();
