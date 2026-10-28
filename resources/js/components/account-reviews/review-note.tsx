import { Settle } from '@/components/motion/served';
import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';
import { FlameScore } from './flame-rating';
import type { MyReview } from './types';

export function ReviewNote({
    review,
    tilt,
}: {
    review: MyReview;
    tilt: number;
}) {
    const t = useT();

    return (
        <Settle tilt={tilt}>
            <figure className="flex h-full flex-col gap-3 rounded-lg border border-line bg-surface p-5 shadow-plate">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <FlameScore value={review.rating} />
                    <Badge variant={review.is_published ? 'leaf' : 'turmeric'}>
                        {review.is_published
                            ? t('reviews.mine.published')
                            : t('reviews.mine.pending')}
                    </Badge>
                </div>
                <blockquote className="whitespace-pre-line text-ink">
                    {review.body}
                </blockquote>
                <figcaption className="text-sm text-ink-muted">
                    {[
                        review.author_area
                            ? `${review.author_name}, ${review.author_area}`
                            : review.author_name,
                        review.order_number
                            ? t('reviews.mine.for_order', {
                                  number: review.order_number,
                              })
                            : null,
                        review.date_label,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </figcaption>
                {review.reply && (
                    <div className="mt-1 rounded-md border-l-4 border-ember bg-clay px-4 py-3">
                        <p className="text-sm font-semibold">
                            {t('reviews.mine.reply_from')}
                        </p>
                        <p className="text-ink">{review.reply}</p>
                    </div>
                )}
            </figure>
        </Settle>
    );
}
