import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArchFrame } from '@/components/brand/arch-frame';
import { Logo } from '@/components/brand/logo';
import { Picture } from '@/components/brand/picture';
import { useT } from '@/lib/i18n';

export type AuthLayoutProps = {
    children?: ReactNode;
    /** Translation key for the page heading. */
    title?: string;
    /** Translation key for the line under the heading. */
    description?: string;
    photo?: 'naan' | 'hands';
};

/**
 * Single column on paper; from 1024px a documentary photo in a tandoor arch
 * sits beside the form.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
    photo = 'naan',
}: AuthLayoutProps) {
    const t = useT();
    const { name } = usePage().props;

    return (
        <div className="min-h-svh bg-paper text-ink lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside className="sticky top-0 hidden h-svh flex-col items-center justify-center gap-8 bg-clay p-10 lg:flex">
                <ArchFrame className="h-[min(34rem,calc(100svh-14rem))] shadow-plate">
                    <Picture
                        mediaKey={`story/${photo}`}
                        alt={t(`auth_pages.layout.photo_alt.${photo}`)}
                        sizes="(min-width: 1024px) 40vw, 0px"
                        priority
                    />
                </ArchFrame>
                <p className="max-w-[18rem] text-center font-display text-display-md text-ink">
                    {t(`auth_pages.layout.line.${photo}`)}
                </p>
            </aside>

            <main
                id="main"
                className="flex min-h-svh flex-col px-4 pt-5 pb-10 sm:px-8 lg:px-14"
            >
                <Link
                    href="/"
                    aria-label={t('auth_pages.layout.home', { name })}
                    className="-ml-2 inline-flex min-h-11 w-fit items-center rounded-md px-2 outline-none focus-visible:outline-2 focus-visible:outline-ember"
                >
                    <Logo name={name} />
                </Link>

                <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center gap-8 py-8">
                    <header className="space-y-3">
                        {title && (
                            <h1 className="font-display text-display-lg text-ink">
                                {t(title)}
                            </h1>
                        )}
                        {description && (
                            <p className="text-body-lg text-ink-muted">
                                {t(description)}
                            </p>
                        )}
                    </header>
                    {children}
                </div>
            </main>
        </div>
    );
}
