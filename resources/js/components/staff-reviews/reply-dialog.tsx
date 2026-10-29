import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ReviewRow } from './types';

export function ReplyDialog({
    review,
    max,
    busy,
    error,
    onClose,
    onSave,
}: {
    review: ReviewRow | null;
    max: number;
    busy: boolean;
    error: string | null;
    onClose: () => void;
    onSave: (text: string) => void;
}) {
    const t = useT();
    const [text, setText] = useState('');
    const editing = review?.reply != null;

    useEffect(() => {
        setText(review?.reply ?? '');
    }, [review?.id, review?.reply]);

    const trimmed = text.trim();
    const over = text.length > max;

    return (
        <Dialog
            open={review !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            editing
                                ? 'staff_reviews.reply_dialog.edit_title'
                                : 'staff_reviews.reply_dialog.title',
                            { name: review?.author_name ?? '' },
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_reviews.reply_dialog.body')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (trimmed !== '' && !over) {
                            onSave(trimmed);
                        }
                    }}
                >
                    {review && !review.is_published && (
                        <p className="rounded-md bg-clay px-3 py-2 text-sm">
                            {t('staff_reviews.reply_dialog.hidden_note')}
                        </p>
                    )}
                    <Label htmlFor="review-reply">
                        {t('staff_reviews.reply_dialog.label')}
                    </Label>
                    <Textarea
                        id="review-reply"
                        value={text}
                        rows={5}
                        placeholder={t(
                            'staff_reviews.reply_dialog.placeholder',
                        )}
                        aria-invalid={over || error !== null}
                        aria-describedby="review-reply-count review-reply-error"
                        onChange={(event) => setText(event.target.value)}
                    />
                    <p
                        id="review-reply-count"
                        className={cn(
                            'text-sm tabular-nums',
                            over ? 'font-bold text-chilli' : 'text-ink-muted',
                        )}
                    >
                        {t('staff_reviews.reply_dialog.counter', {
                            used: text.length,
                            max,
                        })}
                    </p>
                    <p
                        id="review-reply-error"
                        role="alert"
                        className="text-sm text-chilli empty:hidden"
                    >
                        {error}
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('staff_reviews.reply_dialog.back')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={busy || trimmed === '' || over}
                        >
                            {t(
                                editing
                                    ? 'staff_reviews.reply_dialog.save_edit'
                                    : 'staff_reviews.reply_dialog.save',
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function RemoveReplyDialog({
    open,
    busy,
    onClose,
    onConfirm,
}: {
    open: boolean;
    busy: boolean;
    onClose: () => void;
    onConfirm: () => void;
}) {
    const t = useT();

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_reviews.remove_dialog.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_reviews.remove_dialog.body')}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('staff_reviews.remove_dialog.back')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={busy}
                        onClick={onConfirm}
                    >
                        {t('staff_reviews.remove_dialog.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
