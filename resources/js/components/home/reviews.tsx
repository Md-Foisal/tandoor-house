import { Star } from 'lucide-react';
import { SectionHeading } from '@/components/brand/section-heading';
import { EmptyState } from '@/components/brand/states';
import { Course, Settle } from '@/components/motion/served';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { HomeReview } from './types';

/* Small alternating tilts, like notes pinned in a guest book. */
const tilts = [-1.5, 1, -0.5, 1.5, -1, 0.5];
const offsets = [
    '',
    'md:mt-8',
    'lg:mt-3',
    'md:mt-4 lg:mt-12',
    'lg:mt-6',
    'md:mt-8 lg:mt-0',
];

function Rating({ value }: { value: number }) {
    const t = useT();

    return (
        <p className="flex items-center gap-0.5">
            <span className="sr-only">
                {t('home.reviews.rating', { rating: value })}
            </span>
            {[1, 2, 3, 4, 5].map((star) => (
                <Star
                    key={star}
                    aria-hidden="true"
                    className={cn(
                        'size-4',
                        star <= value
                            ? 'fill-turmeric text-turmeric'
                            : 'text-line-strong',
                    )}
                />
            ))}
        </p>
    );
}

export function Reviews({ reviews }: { reviews: HomeReview[] }) {
    const t = useT();

    return (
        <section
            aria-labelledby="reviews-title"
            className="container-page pt-section"
        >
            <SectionHeading
                id="reviews-title"
                eyebrow={t('home.reviews.eyebrow')}
                title={t('home.reviews.title')}
            />
            {reviews.length === 0 ? (
                <EmptyState
                    title={t('home.reviews.empty_title')}
                    description={t('home.reviews.empty_body')}
                />
            ) : (
                <Course className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {reviews.map((review, index) => (
                        <Settle
                            key={review.id}
                            tilt={tilts[index % tilts.length]}
                            className={offsets[index % offsets.length]}
                        >
                            <figure className="flex h-full flex-col gap-3 rounded-lg border border-line bg-surface p-5 shadow-plate">
                                <Rating value={review.rating} />
                                <blockquote className="text-ink">
                                    {review.body}
                                </blockquote>
                                <figcaption className="mt-auto text-sm text-ink-muted">
                                    {review.author_area
                                        ? t('home.reviews.from', {
                                              name: review.author_name,
                                              area: review.author_area,
                                          })
                                        : review.author_name}
                                </figcaption>
                            </figure>
                        </Settle>
                    ))}
                </Course>
            )}
        </section>
    );
}

export function ReviewsSkeleton() {
    const t = useT();

    return (
        <div
            className="container-page pt-section"
            role="status"
            aria-label={t('home.reviews.loading')}
        >
            <div className="flex flex-col gap-3">
                <Skeleton className="h-4 w-28" />
                <Skeleton className="h-10 w-72 max-w-full" />
            </div>
            <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {[0, 1, 2].map((key) => (
                    <div
                        key={key}
                        className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
                    >
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-2/3" />
                        <Skeleton className="mt-2 h-3 w-32" />
                    </div>
                ))}
            </div>
        </div>
    );
}
