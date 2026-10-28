import { Link, usePage } from '@inertiajs/react';
import { MapPin, Navigation, Phone } from 'lucide-react';
import { SectionHeading } from '@/components/brand/section-heading';
import { MarkerDrop, Serve } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { WeekDay } from './types';

/* A drawn street plan, not an embed: no third-party requests, no tracking. */
function MapBlock({ label, street }: { label: string; street: string }) {
    return (
        <div
            role="img"
            aria-label={label}
            className="relative aspect-[4/3] overflow-hidden rounded-lg border border-line bg-clay md:aspect-auto md:min-h-80"
        >
            <svg
                viewBox="0 0 400 300"
                preserveAspectRatio="xMidYMid slice"
                aria-hidden="true"
                className="absolute inset-0 size-full text-line-strong"
            >
                <g fill="none" stroke="currentColor" strokeLinecap="round">
                    <path d="M-10 70 L410 40" strokeWidth="14" opacity="0.35" />
                    <path d="M60 -10 L90 310" strokeWidth="10" opacity="0.35" />
                    <path
                        d="M280 -10 L250 310"
                        strokeWidth="10"
                        opacity="0.35"
                    />
                    <path
                        d="M-10 230 L410 250"
                        strokeWidth="10"
                        opacity="0.35"
                    />
                    <path
                        d="M-10 150 L410 140"
                        strokeWidth="18"
                        opacity="0.55"
                    />
                    <path d="M170 -10 L180 310" strokeWidth="5" opacity="0.3" />
                    <path d="M-10 195 L410 205" strokeWidth="4" opacity="0.3" />
                </g>
            </svg>
            <p className="absolute top-[56%] left-[18%] -rotate-1 text-label text-ink-muted">
                {street}
            </p>
            <MarkerDrop className="absolute top-[30%] left-[52%] -translate-x-1/2">
                <MapPin
                    aria-hidden="true"
                    className="size-12 fill-ember text-ember-ink drop-shadow-sm"
                    strokeWidth={1.5}
                />
            </MarkerDrop>
        </div>
    );
}

export function FindUs({
    hours,
    directionsUrl,
}: {
    hours: WeekDay[];
    directionsUrl: string;
}) {
    const t = useT();
    const { restaurant } = usePage().props;
    const address = `${restaurant.address.line1}, ${restaurant.address.city} ${restaurant.address.postcode}`;
    const tel = restaurant.phone.replace(/\s+/g, '');

    return (
        <section
            aria-labelledby="find-title"
            className="container-page grid gap-10 pt-section md:grid-cols-12 md:gap-x-8"
        >
            <Serve className="flex flex-col gap-6 md:col-span-5">
                <SectionHeading
                    id="find-title"
                    eyebrow={t('home.find.eyebrow')}
                    title={t('home.find.title')}
                />
                <address className="text-body-lg not-italic">
                    {restaurant.address.line1}
                    <br />
                    {restaurant.address.city} {restaurant.address.postcode}
                </address>

                <div>
                    <h3 className="mb-2 text-label text-ink-muted">
                        {t('home.find.hours')}
                    </h3>
                    <ul className="divide-y divide-line border-y border-line">
                        {hours.map((day) => (
                            <li
                                key={day.day}
                                aria-current={day.isToday ? 'date' : undefined}
                                className={cn(
                                    'flex justify-between gap-4 px-2 py-2.5 tabular-nums',
                                    day.isToday
                                        ? 'rounded-sm bg-turmeric-soft font-semibold text-ink'
                                        : 'text-ink-muted',
                                )}
                            >
                                <span>
                                    {day.day}
                                    {day.isToday && (
                                        <span className="ml-2 text-label text-turmeric">
                                            {t('home.find.today')}
                                        </span>
                                    )}
                                </span>
                                <span className="text-right">
                                    {day.periods.length === 0
                                        ? t('home.find.closed')
                                        : day.periods.join(', ')}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <Button asChild size="lg">
                        <a
                            href={directionsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={t('home.find.directions_label', {
                                name: restaurant.name,
                            })}
                        >
                            <Navigation aria-hidden="true" />
                            {t('home.find.directions')}
                        </a>
                    </Button>
                    <Button asChild size="lg" variant="outline">
                        <a href={`tel:${tel}`}>
                            <Phone aria-hidden="true" />
                            {restaurant.phone}
                        </a>
                    </Button>
                </div>
                <Button asChild variant="link" className="self-start">
                    <Link href="/book" prefetch>
                        {t('home.find.book')}
                    </Link>
                </Button>
            </Serve>

            <div className="md:col-span-7 md:pt-12">
                <MapBlock
                    label={t('home.find.map_label', {
                        name: restaurant.name,
                        address,
                    })}
                    street={t('home.find.street_label')}
                />
            </div>
        </section>
    );
}
