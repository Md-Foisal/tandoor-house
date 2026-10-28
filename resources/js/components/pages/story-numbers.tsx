import type { ReactNode } from 'react';
import { CountUp, Serve } from '@/components/motion/served';
import { useT } from '@/lib/i18n';
import type { StoryNumbers } from './types';

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

export function StoryFigures({ numbers }: { numbers: StoryNumbers }) {
    const t = useT();

    return (
        <section
            aria-label={t('pages.story.numbers.label')}
            className="container-page pt-section"
        >
            <Serve>
                <dl className="grid divide-y divide-line border-y border-line md:grid-cols-4 md:divide-x md:divide-y-0 md:py-8">
                    <Figure label={t('pages.story.numbers.years')}>
                        <CountUp to={numbers.years} />
                    </Figure>
                    <Figure label={t('pages.story.numbers.oven')}>
                        <CountUp to={1} />
                    </Figure>
                    <Figure label={t('pages.story.numbers.daal')}>
                        <CountUp to={numbers.daal_hours} />
                    </Figure>
                    <Figure label={t('pages.story.numbers.dishes')}>
                        <CountUp to={numbers.dishes} />
                    </Figure>
                </dl>
            </Serve>
        </section>
    );
}
