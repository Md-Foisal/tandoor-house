import '@fontsource/rozha-one/latin-400.css';
import '@fontsource-variable/hanken-grotesk/wght.css';
import { createInertiaApp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ConsentBanner } from '@/components/consent/consent-banner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import '@/lib/realtime';
import PublicLayout from '@/layouts/public-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import KitchenLayout from '@/layouts/kitchen-layout';
import StaffLayout from '@/layouts/staff-layout';
import StyleguideLayout from '@/layouts/styleguide-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Tandoor House';

// The banner reads translations from page props, so it must render inside a
// layout (within Inertia's context) rather than around the app.
function WithConsent({ children }: { children: ReactNode }) {
    return (
        <>
            {children}
            <ConsentBanner />
        </>
    );
}

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('staff/kitchen'):
                return [WithConsent, KitchenLayout];
            case name.startsWith('staff/'):
                return [WithConsent, StaffLayout];
            case name.startsWith('styleguide/'):
                return [WithConsent, StyleguideLayout];
            case name.startsWith('auth/'):
                return [WithConsent, AuthLayout];
            case name.startsWith('settings/'):
                return [WithConsent, PublicLayout, SettingsLayout];
            default:
                return [WithConsent, PublicLayout];
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
