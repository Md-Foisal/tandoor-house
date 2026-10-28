import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { SectionHeading } from '@/components/brand/section-heading';
import { Serve } from '@/components/motion/served';
import { AllergenAbout } from '@/components/pages/allergen-about';
import { AllergenMatrix } from '@/components/pages/allergen-matrix';
import type { AllergensProps } from '@/components/pages/types';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

const statements = ['tell', 'shared', 'oils', 'changes'] as const;

export default function Allergens({ groups }: AllergensProps) {
    const t = useT();
    const { restaurant } = usePage().props;

    return (
        <>
            <Head title={t('pages.allergens.meta.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('pages.allergens.meta.description')}
                />
            </Head>

            <header className="container-page pt-10 md:pt-16">
                <Serve>
                    <SectionHeading
                        as="h1"
                        size="xl"
                        eyebrow={t('pages.allergens.eyebrow')}
                        title={t('pages.allergens.title')}
                        description={t('pages.allergens.intro')}
                    />
                </Serve>
            </header>

            <section
                aria-labelledby="allergen-statement"
                className="container-page flex flex-col gap-8 pt-section"
            >
                <h2
                    id="allergen-statement"
                    className="font-display text-display-md"
                >
                    {t('pages.allergens.statement.title')}
                </h2>
                <dl className="grid gap-x-10 gap-y-6 md:grid-cols-2">
                    {statements.map((key) => (
                        <div key={key} className="border-l-2 border-ember pl-4">
                            <dt className="text-title">
                                {t(`pages.allergens.statement.${key}.title`)}
                            </dt>
                            <dd className="mt-1 max-w-[56ch] text-ink-muted">
                                {t(`pages.allergens.statement.${key}.body`)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            <section
                aria-labelledby="allergen-about"
                className="container-page flex flex-col gap-8 pt-section"
            >
                <h2
                    id="allergen-about"
                    className="font-display text-display-md"
                >
                    {t('pages.allergens.about.title')}
                </h2>
                <AllergenAbout />
            </section>

            <section
                aria-labelledby="allergen-matrix"
                className="container-page flex flex-col gap-6 pt-section"
            >
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="flex flex-col gap-2">
                        <h2
                            id="allergen-matrix"
                            className="font-display text-display-md"
                        >
                            {t('pages.allergens.matrix.title')}
                        </h2>
                        <p className="max-w-[56ch] text-ink-muted">
                            {t('pages.allergens.matrix.description')}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        data-print-hide
                        className="self-start"
                        onClick={() => window.print()}
                    >
                        <Printer aria-hidden="true" />
                        {t('pages.allergens.matrix.print')}
                    </Button>
                </div>
                <AllergenMatrix groups={groups} />
                <p className="text-sm text-ink-muted">
                    {t('pages.allergens.matrix.updated')}
                </p>
            </section>

            <section
                aria-labelledby="allergen-help"
                className="container-page pt-section"
            >
                <div className="rounded-lg bg-clay p-6 md:p-10">
                    <h2
                        id="allergen-help"
                        className="font-display text-display-md"
                    >
                        {t('pages.allergens.help.title')}
                    </h2>
                    <p className="mt-2 max-w-[56ch] text-ink-muted">
                        {t('pages.allergens.help.body', {
                            phone: restaurant.phone,
                        })}
                    </p>
                </div>
            </section>
        </>
    );
}
