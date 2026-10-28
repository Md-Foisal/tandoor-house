import { Deferred, Head, Link } from '@inertiajs/react';
import { AccountShell } from '@/components/account/account-nav';
import { ReviewNote } from '@/components/account-reviews/review-note';
import { ReviewsSkeleton } from '@/components/account-reviews/skeleton';
import type { ReviewsProps } from '@/components/account-reviews/types';
import { WaitingCard } from '@/components/account-reviews/waiting-card';
import { EmptyState } from '@/components/brand/states';
import { Course } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { choice, useT } from '@/lib/i18n';
import { index as menu } from '@/routes/menu';

/* Small alternating tilts, like notes pinned in a guest book. */
const tilts = [-1.5, 1, -0.5, 1.5, -1, 0.5];

export default function Reviews({ waiting, reviews }: ReviewsProps) {
    const t = useT();

    return (
        <>
            <Head title={t('reviews.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('reviews.meta_description')}
                />
            </Head>
            <AccountShell title={t('reviews.title')}>
                <p className="max-w-[64ch] text-ink-muted">
                    {t('reviews.intro')}
                </p>
                <Deferred
                    data={['waiting', 'reviews']}
                    fallback={<ReviewsSkeleton />}
                >
                    <section
                        aria-labelledby="waiting-title"
                        className="flex flex-col gap-4"
                    >
                        <div className="flex flex-wrap items-baseline gap-x-3">
                            <h2
                                id="waiting-title"
                                className="font-display text-display-md"
                            >
                                {t('reviews.waiting.title')}
                            </h2>
                            {waiting && waiting.length > 0 && (
                                <p className="text-ink-muted">
                                    {choice(
                                        t('reviews.waiting.count', {
                                            count: waiting.length,
                                        }),
                                        waiting.length,
                                    )}
                                </p>
                            )}
                        </div>
                        {waiting && waiting.length === 0 ? (
                            <EmptyState
                                title={t('reviews.waiting.empty_title')}
                                description={t('reviews.waiting.empty_body')}
                                action={
                                    <Button asChild variant="outline">
                                        <Link href={menu()}>
                                            {t('reviews.waiting.empty_action')}
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <div className="flex flex-col gap-5">
                                {waiting?.map((order) => (
                                    <WaitingCard
                                        key={order.number}
                                        order={order}
                                    />
                                ))}
                            </div>
                        )}
                    </section>

                    <section
                        aria-labelledby="mine-title"
                        className="flex flex-col gap-4"
                    >
                        <h2
                            id="mine-title"
                            className="font-display text-display-md"
                        >
                            {t('reviews.mine.title')}
                        </h2>
                        {reviews && reviews.length === 0 ? (
                            <EmptyState
                                title={t('reviews.mine.empty_title')}
                                description={t('reviews.mine.empty_body')}
                            />
                        ) : (
                            <Course className="grid gap-5 md:grid-cols-2">
                                {reviews?.map((review, index) => (
                                    <ReviewNote
                                        key={review.id}
                                        review={review}
                                        tilt={tilts[index % tilts.length]}
                                    />
                                ))}
                            </Course>
                        )}
                    </section>
                </Deferred>
            </AccountShell>
        </>
    );
}
