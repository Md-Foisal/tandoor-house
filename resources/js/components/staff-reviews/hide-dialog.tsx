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
import type { HideReason, ReviewRow } from './types';

export function HideDialog({
    review,
    reasons,
    busy,
    error,
    onClose,
    onConfirm,
}: {
    review: ReviewRow | null;
    reasons: HideReason[];
    busy: boolean;
    error: string | null;
    onClose: () => void;
    onConfirm: (reason: HideReason, note: string) => void;
}) {
    const t = useT();
    const [reason, setReason] = useState<HideReason | null>(null);
    const [note, setNote] = useState('');

    useEffect(() => {
        setReason(null);
        setNote('');
    }, [review?.id]);

    return (
        <Dialog
            open={review !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_reviews.hide_dialog.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_reviews.hide_dialog.body')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (reason) {
                            onConfirm(reason, note.trim());
                        }
                    }}
                >
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-label text-ink-muted uppercase">
                            {t('staff_reviews.hide_dialog.reason')}
                        </legend>
                        {reasons.map((key) => (
                            <button
                                key={key}
                                type="button"
                                aria-pressed={reason === key}
                                onClick={() => setReason(key)}
                                className={cn(
                                    'min-h-11 rounded-md border px-3 py-2 text-left text-sm transition-colors',
                                    reason === key
                                        ? 'border-ember bg-ember-soft text-ink'
                                        : 'border-line-strong bg-surface hover:bg-clay',
                                )}
                            >
                                {t(`staff_reviews.reasons.${key}`)}
                            </button>
                        ))}
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="hide-note">
                            {t('staff_reviews.hide_dialog.note')}
                        </Label>
                        <Textarea
                            id="hide-note"
                            value={note}
                            maxLength={200}
                            placeholder={t(
                                'staff_reviews.hide_dialog.note_placeholder',
                            )}
                            onChange={(event) => setNote(event.target.value)}
                        />
                        {error && (
                            <p role="alert" className="text-sm text-chilli">
                                {error}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('staff_reviews.hide_dialog.back')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={busy || reason === null}
                        >
                            {t('staff_reviews.hide_dialog.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
