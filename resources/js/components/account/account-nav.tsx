import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Section navigation shared by every account page: a horizontally
 * scrolling row on phones, a vertical list from 1024px.
 */
export function AccountNav() {
    const t = useT();
    const { url } = usePage();
    const path = url.split('?')[0];

    const links = [
        { href: '/account', label: t('nav.account_overview') },
        { href: '/account/orders', label: t('nav.account_orders') },
        { href: '/account/addresses', label: t('nav.account_addresses') },
        { href: '/account/reviews', label: t('nav.account_reviews') },
        { href: '/settings/profile', label: t('nav.account_settings') },
    ];

    return (
        <nav aria-label={t('nav.account')} className="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:overflow-visible lg:px-0">
            <ul className="flex gap-1 lg:flex-col">
                {links.map((link) => {
                    const current = link.href === '/account' ? path === '/account' : path?.startsWith(link.href);

                    return (
                        <li key={link.href} className="shrink-0">
                            <Link
                                href={link.href}
                                prefetch
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'flex h-11 items-center rounded-full px-4 font-semibold whitespace-nowrap text-ink-muted outline-none hover:text-ink focus-visible:outline-2 focus-visible:outline-ember lg:rounded-md',
                                    current && 'bg-ink text-paper hover:text-paper',
                                )}
                            >
                                {link.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

/**
 * Page frame for account screens: the section nav beside (or above) the content.
 */
export function AccountShell({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="container-page grid gap-6 py-8 md:py-12 lg:grid-cols-[14rem_1fr] lg:gap-12">
            <div className="flex flex-col gap-4">
                <h1 className="font-display text-display-lg lg:hidden">{title}</h1>
                <AccountNav />
            </div>
            <div className="flex min-w-0 flex-col gap-6">
                <h1 className="hidden font-display text-display-lg lg:block">{title}</h1>
                {children}
            </div>
        </div>
    );
}
