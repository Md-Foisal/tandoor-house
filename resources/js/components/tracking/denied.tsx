import { Head, Link, usePage } from '@inertiajs/react';
import { EmberLine } from '@/components/brand/ember-line';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

export function OrderDenied({ number }: { number: string }) {
    const t = useT();
    const { restaurant } = usePage().props;

    return (
        <>
            <Head title={t('tracking.page_title', { number })} />
            <div className="container-page flex max-w-xl flex-col gap-4 pt-10 pb-16 md:pt-16">
                <h1 className="font-display text-display-lg text-ink">
                    {t('tracking.denied.title')}
                </h1>
                <EmberLine />
                <p className="text-ink-muted">{t('tracking.denied.body')}</p>
                <div className="flex flex-wrap gap-3 pt-2">
                    <Button asChild size="lg">
                        <Link href="/login">
                            {t('tracking.denied.sign_in')}
                        </Link>
                    </Button>
                    <Button asChild size="lg" variant="outline">
                        <Link href="/menu">{t('tracking.denied.menu')}</Link>
                    </Button>
                </div>
                {restaurant?.phone && (
                    <a
                        href={`tel:${restaurant.phone.replace(/\s+/g, '')}`}
                        className="inline-flex min-h-11 items-center font-semibold text-ember underline underline-offset-4"
                    >
                        {t('tracking.denied.call', { phone: restaurant.phone })}
                    </a>
                )}
            </div>
        </>
    );
}
