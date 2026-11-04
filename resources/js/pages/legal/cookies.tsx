import { Head, Link, usePage } from '@inertiajs/react';
import { SectionHeading } from '@/components/brand/section-heading';
import { useConsent } from '@/components/consent/use-consent';
import { Serve } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

type Item = {
    id: string;
    name: string;
    category: 'necessary' | 'preferences';
    storage: 'cookie' | 'local' | 'session';
    duration: string;
    purpose: string;
};

type Props = {
    updated: string;
    items: Item[];
};

const categories = ['necessary', 'preferences'] as const;

export default function Cookies({ updated, items }: Props) {
    const t = useT();
    const { restaurant } = usePage().props;
    const consent = useConsent();

    return (
        <>
            <Head title={t('legal.cookies.meta.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('legal.cookies.meta.description', {
                        name: restaurant.name,
                    })}
                />
            </Head>

            <header className="container-page pt-10 md:pt-16">
                <Serve className="flex flex-col gap-3">
                    <SectionHeading
                        as="h1"
                        size="xl"
                        eyebrow={t('legal.cookies.eyebrow')}
                        title={t('legal.cookies.title')}
                        description={t('legal.cookies.intro')}
                    />
                    <p className="text-sm text-ink-muted">
                        {t('legal.updated', { date: updated })}
                    </p>
                </Serve>
            </header>

            <div className="container-page flex flex-col gap-12 py-10 lg:py-14">
                <section
                    aria-labelledby="cookie-choice"
                    className="flex max-w-3xl flex-col gap-4 rounded-lg border border-line-strong bg-surface p-5 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h2 id="cookie-choice" className="font-display text-xl">
                            {t('legal.cookies.settings_title')}
                        </h2>
                        <p className="mt-1 text-sm text-ink-muted">
                            {t('legal.cookies.settings_body')}
                        </p>
                        <p role="status" className="mt-2 text-sm font-semibold">
                            {consent.preferences
                                ? t('legal.cookies.status_preferences_on')
                                : t('legal.cookies.status_preferences_off')}
                        </p>
                    </div>
                    <Button variant="outline" onClick={consent.reopen}>
                        {t('legal.cookies.change')}
                    </Button>
                </section>

                {categories.map((category) => {
                    const rows = items.filter(
                        (item) => item.category === category,
                    );

                    return (
                        <section
                            key={category}
                            aria-labelledby={`cookies-${category}`}
                            className="max-w-5xl"
                        >
                            <h2
                                id={`cookies-${category}`}
                                className="font-display text-2xl"
                            >
                                {t(`legal.cookies.categories.${category}`)}
                            </h2>
                            <p className="mt-2 max-w-prose leading-relaxed">
                                {t(`legal.cookies.category_intro.${category}`)}
                            </p>
                            <table className="mt-4 w-full text-left text-sm md:table-fixed">
                                <caption className="sr-only">
                                    {t('legal.cookies.table_caption')}:{' '}
                                    {t(`legal.cookies.categories.${category}`)}
                                </caption>
                                <thead className="sr-only md:not-sr-only">
                                    <tr className="border-b border-line-strong text-label text-ink-muted">
                                        {(
                                            [
                                                'name',
                                                'purpose',
                                                'duration',
                                                'type',
                                            ] as const
                                        ).map((column) => (
                                            <th
                                                key={column}
                                                scope="col"
                                                className={
                                                    column === 'purpose'
                                                        ? 'py-2 pr-4 md:w-[40%]'
                                                        : 'py-2 pr-4'
                                                }
                                            >
                                                {t(
                                                    `legal.cookies.columns.${column}`,
                                                )}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="block border-b border-line py-3 md:table-row md:py-0"
                                        >
                                            <th
                                                scope="row"
                                                className="block pr-4 font-mono text-[0.8125rem] font-semibold break-words md:table-cell md:py-3"
                                            >
                                                {row.name}
                                            </th>
                                            <td className="block pt-1 pr-4 md:table-cell md:py-3">
                                                {row.purpose}
                                            </td>
                                            <td className="block pt-1 pr-4 text-ink-muted md:table-cell md:py-3">
                                                <span className="font-semibold md:hidden">
                                                    {t(
                                                        'legal.cookies.columns.duration',
                                                    )}
                                                    :{' '}
                                                </span>
                                                {row.duration}
                                            </td>
                                            <td className="block pt-1 pr-4 text-ink-muted md:table-cell md:py-3">
                                                <span className="font-semibold md:hidden">
                                                    {t(
                                                        'legal.cookies.columns.type',
                                                    )}
                                                    :{' '}
                                                </span>
                                                {t(
                                                    `legal.cookies.storage.${row.storage}`,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    );
                })}

                <p className="text-sm">
                    <Link
                        href="/privacy"
                        className="inline-flex min-h-11 items-center underline underline-offset-4"
                    >
                        {t('legal.privacy.meta.title')}
                    </Link>
                </p>
            </div>
        </>
    );
}
