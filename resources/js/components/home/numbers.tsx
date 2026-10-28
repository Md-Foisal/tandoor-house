import type { ReactNode } from 'react';
import { CountUp, Serve } from '@/components/motion/served';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import type { HomeNumbers } from './types';

function Figure({ children, label }: { children: ReactNode; label: string }) {
    return (
        <div className="flex flex-col gap-1 py-6 md:py-0 md:pl-8 md:first:pl-0">
            <dd className="font-display text-display-xl text-ink">
                {children}
            </dd>
            <dt className="text-ink-muted">{label}</dt>
        </div>
    );
}

export function Numbers({ numbers }: { numbers: HomeNumbers }) {
    const t = useT();

    return (
        <section
            aria-label={t('home.numbers.title')}
            className="container-page pt-section"
        >
            <Serve>
                <dl className="grid divide-y divide-line border-y border-line md:grid-cols-3 md:divide-x md:divide-y-0 md:py-8">
                    <Figure label={t('home.numbers.years')}>
                        <CountUp to={numbers.years} />
                    </Figure>
                    <Figure
                        label={
                            numbers.rating === null
                                ? t('home.numbers.unrated')
                                : t('home.numbers.rating', {
                                      count: numbers.reviews,
                                  })
                        }
                    >
                        {numbers.rating === null ? (
                            <span aria-hidden="true">–</span>
                        ) : (
                            <CountUp
                                to={numbers.rating}
                                format={(value) => value.toFixed(1)}
                            />
                        )}
                    </Figure>
                    <Figure label={t('home.numbers.dishes')}>
                        <CountUp to={numbers.dishes} />
                    </Figure>
                </dl>
            </Serve>
        </section>
    );
}

export function NumbersSkeleton() {
    const t = useT();

    return (
        <div
            className="container-page pt-section"
            role="status"
            aria-label={t('ui.states.loading')}
        >
            <div className="grid divide-y divide-line border-y border-line md:grid-cols-3 md:divide-x md:divide-y-0 md:py-8">
                {[0, 1, 2].map((key) => (
                    <div
                        key={key}
                        className="flex flex-col gap-2 py-6 md:py-0 md:pl-8 md:first:pl-0"
                    >
                        <Skeleton className="h-12 w-28" />
                        <Skeleton className="h-4 w-44" />
                    </div>
                ))}
            </div>
        </div>
    );
}
