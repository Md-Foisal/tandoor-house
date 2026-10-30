import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { AccountShell } from '@/components/account/account-nav';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useT } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const t = useT();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const items = [
        { title: t('settings_pages.nav.profile'), href: edit() },
        { title: t('settings_pages.nav.security'), href: editSecurity() },
        { title: t('settings_pages.nav.appearance'), href: editAppearance() },
    ];

    return (
        <AccountShell title={t('settings_pages.shell.title')}>
            <nav
                aria-label={t('settings_pages.shell.nav_label')}
                className="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0"
            >
                <ul className="flex gap-2 border-b border-line">
                    {items.map((item) => {
                        const current = isCurrentOrParentUrl(item.href);

                        return (
                            <li key={toUrl(item.href)} className="shrink-0">
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={current ? 'page' : undefined}
                                    className={cn(
                                        '-mb-px flex h-11 items-center border-b-2 border-transparent px-3 font-semibold whitespace-nowrap text-ink-muted outline-hidden hover:text-ink focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ember focus-visible:outline-solid',
                                        current &&
                                            'border-ember text-ink hover:text-ink',
                                    )}
                                >
                                    {item.title}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <div className="flex max-w-xl flex-col gap-12">{children}</div>
        </AccountShell>
    );
}
