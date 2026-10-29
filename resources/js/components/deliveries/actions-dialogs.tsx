import { router } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { DeliveryCardData } from './types';

const quick = ['no_answer', 'unreachable', 'not_found', 'refused'] as const;

function post(
    url: string,
    data: Record<string, unknown>,
    handlers: {
        onBusy: (busy: boolean) => void;
        onError: (message: string | null) => void;
        onDone: () => void;
    },
) {
    handlers.onBusy(true);
    handlers.onError(null);
    router.post(url, data as Record<string, string | number>, {
        preserveScroll: true,
        preserveState: true,
        only: ['deliveries', 'cash'],
        onError: (errors) =>
            handlers.onError(Object.values(errors)[0] ?? 'error'),
        onSuccess: handlers.onDone,
        onFinish: () => handlers.onBusy(false),
    });
}

export function DeliverDialog({
    card,
    open,
    onClose,
}: {
    card: DeliveryCardData;
    open: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const [cash, setCash] = useState((card.total / 100).toFixed(2));
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const pence = Math.round(Number(cash) * 100);
    const valid = !card.cash_due || (cash.trim() !== '' && pence >= 0);

    useEffect(() => {
        if (open) {
            setCash((card.total / 100).toFixed(2));
            setError(null);
        }
    }, [open, card.total]);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_deliveries.deliver.title', {
                            name: card.first_name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {card.cash_due
                            ? t('staff_deliveries.deliver.cash_hint')
                            : t('staff_deliveries.deliver.card_body')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(
                            `/staff/deliveries/${card.number}/deliver`,
                            card.cash_due ? { cash_collected: pence } : {},
                            {
                                onBusy: setBusy,
                                onError: setError,
                                onDone: onClose,
                            },
                        );
                    }}
                >
                    {card.cash_due && (
                        <div className="grid gap-2">
                            <Label htmlFor="cash-collected">
                                {t('staff_deliveries.deliver.cash_label')}
                            </Label>
                            <Input
                                id="cash-collected"
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step="0.01"
                                required
                                value={cash}
                                aria-invalid={error !== null}
                                className="h-14 text-2xl font-semibold tabular-nums"
                                onChange={(event) =>
                                    setCash(event.target.value)
                                }
                            />
                            {error && (
                                <p role="alert" className="text-sm text-chilli">
                                    {error}
                                </p>
                            )}
                        </div>
                    )}
                    {!card.cash_due && error && (
                        <p role="alert" className="text-sm text-chilli">
                            {error}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('staff_deliveries.deliver.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            className="min-h-14"
                            disabled={busy || !valid}
                        >
                            {t('staff_deliveries.deliver.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function FailDialog({
    card,
    open,
    onClose,
}: {
    card: DeliveryCardData;
    open: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            setText('');
            setError(null);
        }
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_deliveries.fail.title', {
                            number: card.number,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_deliveries.fail.body')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(
                            `/staff/deliveries/${card.number}/fail`,
                            { reason: text.trim() },
                            {
                                onBusy: setBusy,
                                onError: setError,
                                onDone: onClose,
                            },
                        );
                    }}
                >
                    <div className="grid gap-2">
                        {quick.map((key) => {
                            const line = t(
                                `staff_deliveries.fail.quick.${key}`,
                            );

                            return (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={text === line}
                                    onClick={() => setText(line)}
                                    className={cn(
                                        'min-h-12 rounded-md border px-3 py-2 text-left text-sm transition-colors',
                                        text === line
                                            ? 'border-ember bg-ember-soft text-ink'
                                            : 'border-line-strong bg-surface hover:bg-clay',
                                    )}
                                >
                                    {line}
                                </button>
                            );
                        })}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="fail-reason">
                            {t('staff_deliveries.fail.reason_label')}
                        </Label>
                        <Textarea
                            id="fail-reason"
                            value={text}
                            maxLength={240}
                            placeholder={t(
                                'staff_deliveries.fail.reason_placeholder',
                            )}
                            aria-invalid={error !== null}
                            onChange={(event) => setText(event.target.value)}
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
                            {t('staff_deliveries.fail.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={busy || text.trim().length < 3}
                        >
                            {t('staff_deliveries.fail.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
