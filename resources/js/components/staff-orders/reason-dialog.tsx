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

const common = {
    rejected: ['busy', 'sold_out', 'closing', 'address'],
    cancelled: ['guest', 'mistake', 'sold_out', 'duplicate'],
} as const;

export function ReasonDialog({
    mode,
    number,
    busy,
    error,
    onClose,
    onConfirm,
}: {
    mode: 'rejected' | 'cancelled' | null;
    number: string;
    busy: boolean;
    error: string | null;
    onClose: () => void;
    onConfirm: (reason: string) => void;
}) {
    const t = useT();
    const [text, setText] = useState('');
    const kind = mode === 'rejected' ? 'reject' : 'cancel';

    useEffect(() => {
        if (mode === null) {
            setText('');
        }
    }, [mode]);

    return (
        <Dialog
            open={mode !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(`staff_orders.reason.${kind}_title`, { number })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(`staff_orders.reason.${kind}_body`)}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (text.trim() !== '') {
                            onConfirm(text.trim());
                        }
                    }}
                >
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-label text-ink-muted uppercase">
                            {t('staff_orders.reason.common')}
                        </legend>
                        {common[mode ?? 'rejected'].map((key) => {
                            const line = t(
                                `staff_orders.reason.${kind}.${key}`,
                            );

                            return (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={text === line}
                                    onClick={() => setText(line)}
                                    className={cn(
                                        'min-h-11 rounded-md border px-3 py-2 text-left text-sm transition-colors',
                                        text === line
                                            ? 'border-ember bg-ember-soft text-ink'
                                            : 'border-line-strong bg-surface hover:bg-clay',
                                    )}
                                >
                                    {line}
                                </button>
                            );
                        })}
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="order-reason">
                            {t('staff_orders.reason.free')}
                        </Label>
                        <Textarea
                            id="order-reason"
                            value={text}
                            maxLength={200}
                            placeholder={t('staff_orders.reason.placeholder')}
                            aria-invalid={error !== null}
                            aria-describedby={
                                error ? 'order-reason-error' : undefined
                            }
                            onChange={(event) => setText(event.target.value)}
                        />
                        {error && (
                            <p
                                id="order-reason-error"
                                role="alert"
                                className="text-sm text-chilli"
                            >
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
                            {t('staff_orders.reason.back')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={busy || text.trim() === ''}
                        >
                            {t(`staff_orders.reason.confirm_${kind}`)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
