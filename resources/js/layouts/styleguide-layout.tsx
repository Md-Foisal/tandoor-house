import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Logo } from '@/components/brand/logo';
import { ThemeToggle } from '@/components/brand/theme-toggle';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export default function StyleguideLayout({
    children,
}: {
    children: ReactNode;
}) {
    const t = useT();
    const { name } = usePage().props;
    const { url } = usePage();

    const links = [
        { href: '/styleguide', label: t('styleguide.nav.components') },
        { href: '/styleguide/motion', label: t('styleguide.nav.motion') },
    ];

    return (
        <div className="min-h-dvh bg-paper">
            <header className="sticky top-0 z-30 border-b border-line bg-paper/95 backdrop-blur-sm">
                <div className="container-page flex h-16 items-center justify-between gap-4">
                    <Link
                        href="/"
                        className="rounded-sm outline-hidden focus-visible:outline-2 focus-visible:outline-ember"
                    >
                        <Logo name={name} />
                    </Link>
                    <ThemeToggle />
                </div>
                <nav
                    className="container-page flex gap-1 pb-2"
                    aria-label={t('styleguide.title')}
                >
                    {links.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            aria-current={
                                url === link.href ? 'page' : undefined
                            }
                            className={cn(
                                'rounded-full px-4 py-2.5 text-sm font-semibold text-ink-muted outline-hidden hover:text-ink focus-visible:outline-2 focus-visible:outline-ember',
                                url === link.href &&
                                    'bg-ink text-paper hover:text-paper',
                            )}
                        >
                            {link.label}
                        </Link>
                    ))}
                </nav>
            </header>
            <main id="main" className="container-page py-10 md:py-14">
                {children}
            </main>
        </div>
    );
}
