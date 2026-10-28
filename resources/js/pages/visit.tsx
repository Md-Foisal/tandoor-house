import { Head, Link, usePage } from '@inertiajs/react';
import {
    Bike,
    Bus,
    Car,
    Check,
    HelpCircle,
    Mail,
    Phone,
    TrainFront,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { OpenStatus } from '@/components/brand/open-status';
import { SectionHeading } from '@/components/brand/section-heading';
import { Serve } from '@/components/motion/served';
import { HoursList } from '@/components/pages/hours-list';
import { MapBlock } from '@/components/pages/map-block';
import type { VisitProps } from '@/components/pages/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

const ways: {
    key: 'tube' | 'bus' | 'bike' | 'car';
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
}[] = [
    { key: 'tube', icon: TrainFront },
    { key: 'bus', icon: Bus },
    { key: 'bike', icon: Bike },
    { key: 'car', icon: Car },
];

/* `ask` marks what we are not certain of; the page says so rather than guess. */
const access = [
    { key: 'step_free', ask: false },
    { key: 'toilet', ask: true },
    { key: 'high_chairs', ask: true },
    { key: 'wheelchair', ask: true },
    { key: 'hearing', ask: true },
    { key: 'assistance', ask: false },
] as const;

export default function Visit({
    hours,
    bankHolidays,
    maps,
    maxParty,
}: VisitProps) {
    const t = useT();
    const { restaurant, openStatus } = usePage().props;
    const tel = restaurant.phone.replace(/\s+/g, '');

    return (
        <>
            <Head title={t('pages.visit.meta.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('pages.visit.meta.description')}
                />
            </Head>

            <header className="container-page pt-10 md:pt-16">
                <Serve className="flex flex-col gap-6">
                    <SectionHeading
                        as="h1"
                        size="xl"
                        eyebrow={t('pages.visit.eyebrow')}
                        title={t('pages.visit.title')}
                        description={t('pages.visit.intro')}
                    />
                </Serve>
            </header>

            <section
                aria-labelledby="visit-where"
                className="container-page grid gap-10 pt-section md:grid-cols-12 md:gap-x-8"
            >
                <Serve className="flex flex-col gap-6 md:col-span-5">
                    <h2
                        id="visit-where"
                        className="font-display text-display-md"
                    >
                        {t('pages.visit.address.title')}
                    </h2>
                    <address className="text-body-lg not-italic">
                        {restaurant.name}
                        <br />
                        {restaurant.address.line1}
                        <br />
                        {restaurant.address.city} {restaurant.address.postcode}
                    </address>
                    <div>
                        <p className="mb-1 text-label text-ink-muted">
                            {t('pages.visit.status')}
                        </p>
                        <OpenStatus status={openStatus} className="text-base" />
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row md:flex-col lg:flex-row">
                        <Button asChild size="lg">
                            <a href={`tel:${tel}`}>
                                <Phone aria-hidden="true" />
                                {restaurant.phone}
                            </a>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <a href={`mailto:${restaurant.email}`}>
                                <Mail aria-hidden="true" />
                                {restaurant.email}
                            </a>
                        </Button>
                    </div>
                </Serve>

                <Serve className="flex flex-col gap-4 md:col-span-7">
                    <MapBlock address={maps.address} name={restaurant.name} />
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button asChild variant="outline" className="min-h-11">
                            <a
                                href={maps.google}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t('pages.visit.map.google')}
                                <span className="sr-only">
                                    {' '}
                                    ({t('pages.visit.map.opens_new')})
                                </span>
                            </a>
                        </Button>
                        <Button asChild variant="outline" className="min-h-11">
                            <a
                                href={maps.apple}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t('pages.visit.map.apple')}
                                <span className="sr-only">
                                    {' '}
                                    ({t('pages.visit.map.opens_new')})
                                </span>
                            </a>
                        </Button>
                    </div>
                    <p className="text-sm text-ink-muted">
                        {t('pages.visit.map.note')}
                    </p>
                </Serve>
            </section>

            <section
                aria-labelledby="visit-hours"
                className="container-page grid gap-10 pt-section md:grid-cols-12 md:gap-x-8"
            >
                <Serve className="flex flex-col gap-4 md:col-span-6 md:col-start-1">
                    <h2
                        id="visit-hours"
                        className="font-display text-display-md"
                    >
                        {t('pages.visit.hours.title')}
                    </h2>
                    <HoursList hours={hours} />
                    <p className="text-sm text-ink-muted">
                        {t('pages.visit.hours.note')}
                    </p>
                </Serve>
                <Serve className="flex flex-col gap-3 md:col-span-5 md:col-start-8 md:pt-12">
                    <h3 className="text-title">
                        {t('pages.visit.hours.bank_title')}
                    </h3>
                    <p className="text-ink-muted">
                        {t('pages.visit.hours.bank_note')}
                    </p>
                    {bankHolidays.length > 0 && (
                        <p className="font-semibold text-ink">
                            {t('pages.visit.hours.bank_upcoming', {
                                dates: bankHolidays.join(', '),
                            })}
                        </p>
                    )}
                </Serve>
            </section>

            <section
                aria-labelledby="visit-travel"
                className="container-page flex flex-col gap-8 pt-section"
            >
                <Serve>
                    <h2
                        id="visit-travel"
                        className="font-display text-display-md"
                    >
                        {t('pages.visit.getting_here.title')}
                    </h2>
                </Serve>
                <dl className="grid gap-x-10 gap-y-6 md:grid-cols-2">
                    {ways.map(({ key, icon: Icon }) => (
                        <Serve key={key} className="flex gap-4">
                            <Icon
                                aria-hidden
                                className="mt-1 size-6 shrink-0 text-ember"
                            />
                            <div>
                                <dt className="text-title">
                                    {t(`pages.visit.getting_here.${key}.title`)}
                                </dt>
                                <dd className="max-w-[52ch] text-ink-muted">
                                    {t(`pages.visit.getting_here.${key}.body`)}
                                </dd>
                            </div>
                        </Serve>
                    ))}
                </dl>
            </section>

            <section
                aria-labelledby="visit-access"
                className="container-page grid gap-8 pt-section md:grid-cols-12 md:gap-x-8"
            >
                <Serve className="flex flex-col gap-3 md:col-span-4">
                    <h2
                        id="visit-access"
                        className="font-display text-display-md"
                    >
                        {t('pages.visit.access.title')}
                    </h2>
                    <p className="text-ink-muted">
                        {t('pages.visit.access.intro')}
                    </p>
                </Serve>
                <ul className="divide-y divide-line border-y border-line md:col-span-8">
                    {access.map(({ key, ask }) => {
                        return (
                            <li
                                key={key}
                                className="grid gap-1 py-4 sm:grid-cols-[1fr_auto] sm:gap-x-6"
                            >
                                <div>
                                    <p className="font-semibold">
                                        {t(
                                            `pages.visit.access.items.${key}.label`,
                                        )}
                                    </p>
                                    <p className="text-ink-muted">
                                        {t(
                                            `pages.visit.access.items.${key}.note`,
                                        )}
                                    </p>
                                </div>
                                <Badge
                                    variant={ask ? 'turmeric' : 'leaf'}
                                    className="self-start"
                                >
                                    {ask ? (
                                        <HelpCircle aria-hidden="true" />
                                    ) : (
                                        <Check aria-hidden="true" />
                                    )}
                                    {ask
                                        ? t('pages.visit.access.ask_us')
                                        : t('pages.visit.access.yes')}
                                </Badge>
                            </li>
                        );
                    })}
                </ul>
            </section>

            <section
                aria-labelledby="visit-groups"
                className="container-page pt-section"
            >
                <Serve className="flex flex-col gap-6 rounded-lg bg-clay p-6 md:flex-row md:items-center md:justify-between md:p-10">
                    <div className="flex flex-col gap-2">
                        <h2
                            id="visit-groups"
                            className="font-display text-display-md"
                        >
                            {t('pages.visit.groups.title')}
                        </h2>
                        <p className="max-w-[56ch] text-ink-muted">
                            {t('pages.visit.groups.body', { max: maxParty })}
                        </p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button asChild size="lg">
                            <Link href="/book" prefetch>
                                {t('pages.visit.groups.book')}
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <a
                                href={`mailto:${restaurant.email}?subject=${encodeURIComponent(t('pages.visit.groups.contact'))}`}
                            >
                                {t('pages.visit.groups.contact')}
                            </a>
                        </Button>
                    </div>
                </Serve>
            </section>
        </>
    );
}
