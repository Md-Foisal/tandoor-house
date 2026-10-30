import { Link } from '@inertiajs/react';
import {
    Check,
    EyeOff,
    MessageSquareReply,
    Pencil,
    Trash2,
} from 'lucide-react';
import { ChannelBadge } from '@/components/staff/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { day, dayTime } from './format';
import { Rating } from './rating';
import type { ReviewRow, ReviewTab } from './types';

export function ReviewCard({
    review,
    tab,
    selected,
    canAct,
    busy,
    onSelect,
    onPublish,
    onHide,
    onReply,
    onRemoveReply,
}: {
    review: ReviewRow;
    tab: ReviewTab;
    selected: boolean;
    canAct: boolean;
    busy: boolean;
    onSelect: (checked: boolean) => void;
    onPublish: () => void;
    onHide: () => void;
    onReply: () => void;
    onRemoveReply: () => void;
}) {
    const t = useT();
    const who = review.author_area
        ? t('staff_reviews.card.from', {
              name: review.author_name,
              area: review.author_area,
          })
        : review.author_name;

    return (
        <li>
            <article
                aria-labelledby={`review-${review.id}-who`}
                className={cn(
                    'grid gap-3 rounded-lg border bg-surface p-4',
                    selected ? 'border-ember' : 'border-line',
                )}
            >
                <header className="flex items-start gap-3">
                    {tab === 'waiting' && (
                        <Checkbox
                            checked={selected}
                            disabled={!canAct}
                            aria-label={t('staff_reviews.actions.select', {
                                name: review.author_name,
                            })}
                            onCheckedChange={(value) =>
                                onSelect(value === true)
                            }
                            className="mt-1 size-6"
                        />
                    )}
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <Rating value={review.rating} />
                            <span
                                id={`review-${review.id}-who`}
                                className="font-semibold"
                            >
                                {who}
                            </span>
                        </div>
                        <p className="text-sm text-ink-muted">
                            {t('staff_reviews.card.reviewed', {
                                when: day(review.created_at),
                            })}
                        </p>
                    </div>
                    {tab === 'hidden' && (
                        <Badge variant="destructive">
                            {t('staff_reviews.tabs.hidden')}
                        </Badge>
                    )}
                </header>

                <p className="max-w-prose text-pretty whitespace-pre-line">
                    {review.body}
                </p>

                {review.hidden && (
                    <p className="rounded-md bg-clay px-3 py-2 text-sm">
                        {t('staff_reviews.card.hidden_because', {
                            reason: review.hidden.reason
                                ? t(
                                      `staff_reviews.reasons.${review.hidden.reason}`,
                                  )
                                : t('staff_reviews.reasons.other'),
                        })}
                        {review.hidden.by &&
                            ` ${t('staff_reviews.card.hidden_by', { name: review.hidden.by })}`}
                        {review.hidden.note && `. ${review.hidden.note}`}
                    </p>
                )}

                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-3 text-sm">
                    {review.order ? (
                        <>
                            <Link
                                href={`/staff/orders/${review.order.number}`}
                                aria-label={t('staff_reviews.card.order_link', {
                                    number: review.order.number,
                                })}
                                className="inline-flex min-h-11 items-center font-bold underline underline-offset-4"
                            >
                                {t('staff_reviews.card.order', {
                                    number: review.order.number,
                                })}
                            </Link>
                            <ChannelBadge channel={review.order.channel} />
                            <span className="text-ink-muted">
                                {t('staff_reviews.card.placed', {
                                    when: dayTime(review.order.placed_at),
                                })}
                            </span>
                            <span className="text-ink-muted">
                                {review.order.items.join(', ')}
                            </span>
                        </>
                    ) : (
                        <span className="text-ink-muted">
                            {t('staff_reviews.card.no_order')}
                        </span>
                    )}
                </div>

                {review.reply && (
                    <div className="rounded-md border-l-4 border-ember bg-paper px-3 py-2">
                        <p className="text-label text-ink-muted">
                            {t('staff_reviews.card.reply_label')}
                        </p>
                        <p className="whitespace-pre-line">{review.reply}</p>
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    {tab !== 'published' && (
                        <Button
                            size="lg"
                            disabled={!canAct || busy}
                            onClick={onPublish}
                        >
                            <Check aria-hidden="true" />
                            {t(
                                tab === 'hidden'
                                    ? 'staff_reviews.actions.publish_anyway'
                                    : 'staff_reviews.actions.publish',
                            )}
                        </Button>
                    )}
                    {tab !== 'hidden' && (
                        <Button
                            size="lg"
                            variant="outline"
                            disabled={!canAct || busy}
                            onClick={onHide}
                        >
                            <EyeOff aria-hidden="true" />
                            {t('staff_reviews.actions.hide')}
                        </Button>
                    )}
                    <Button
                        size="lg"
                        variant="outline"
                        disabled={!canAct || busy}
                        onClick={onReply}
                    >
                        {review.reply ? (
                            <Pencil aria-hidden="true" />
                        ) : (
                            <MessageSquareReply aria-hidden="true" />
                        )}
                        {t(
                            review.reply
                                ? 'staff_reviews.actions.edit_reply'
                                : 'staff_reviews.actions.reply',
                        )}
                    </Button>
                    {review.reply && (
                        <Button
                            size="lg"
                            variant="ghost"
                            disabled={!canAct || busy}
                            onClick={onRemoveReply}
                        >
                            <Trash2 aria-hidden="true" />
                            {t('staff_reviews.actions.remove_reply')}
                        </Button>
                    )}
                </div>
            </article>
        </li>
    );
}
