import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect } from 'react';
import { LogoMark } from '@/components/brand/logo';
import { OfflineBanner } from '@/components/brand/states';
import { useT } from '@/lib/i18n';

const KITCHEN_THEME = 'kitchen-theme';

/**
 * Full-screen shell for the kitchen display, built for a landscape tablet
 * on the pass. It is dark by default (glare and grease) unless a cook has
 * chosen light on this device.
 */
export default function KitchenLayout({ children }: { children: ReactNode }) {
    const t = useT();

    useEffect(() => {
        const root = document.documentElement;
        const hadDark = root.classList.contains('dark');
        let light = false;

        try {
            light = localStorage.getItem(KITCHEN_THEME) === 'light';
        } catch {
            light = false;
        }

        root.classList.toggle('dark', !light);

        return () => {
            root.classList.toggle('dark', hadDark);
        };
    }, []);

    return (
        <div className="flex min-h-dvh flex-col bg-paper text-ink">
            <OfflineBanner />
            <header className="flex h-14 shrink-0 items-center gap-3 border-b border-line px-3">
                <Link
                    href="/staff"
                    className="grid size-11 place-items-center rounded-md text-ink-muted outline-none hover:bg-clay hover:text-ink focus-visible:outline-2 focus-visible:outline-ember"
                    aria-label={t('staff.areas.today')}
                >
                    <ArrowLeft className="size-5" aria-hidden="true" />
                </Link>
                <LogoMark className="size-7" />
                <span className="font-display text-lg">
                    {t('staff.areas.kitchen')}
                </span>
            </header>
            <main id="main" className="flex min-h-0 flex-1 flex-col">
                {children}
            </main>
        </div>
    );
}

export function setKitchenTheme(theme: 'light' | 'dark'): void {
    try {
        localStorage.setItem(KITCHEN_THEME, theme);
    } catch {
        // Private browsing: the choice lasts for this visit only.
    }

    document.documentElement.classList.toggle('dark', theme === 'dark');
}
