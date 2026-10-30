import { router } from '@inertiajs/react';
import { Minus, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useOnline } from '@/components/brand/states';
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
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { OrderDetailData } from './types';

export function EditDialog({
    order,
    open,
    onOpenChange,
}: {
    order: OrderDetailData;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const t = useT();
    const online = useOnline();
    const [quantities, setQuantities] = useState<Record<number, number>>({});
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Read through a ref so a poll refreshing the order does not wipe edits.
    const linesRef = useRef(order.lines);
    linesRef.current = order.lines;

    useEffect(() => {
        if (open) {
            setQuantities(
                Object.fromEntries(
                    linesRef.current.map((line) => [line.id, line.quantity]),
                ),
            );
            setNote('');
            setError(null);
        }
    }, [open]);

    const subtotal = order.lines.reduce(
        (sum, line) =>
            sum + line.unit_price * (quantities[line.id] ?? line.quantity),
        0,
    );
    const kept = order.lines.some(
        (line) => (quantities[line.id] ?? line.quantity) > 0,
    );

    const save = () => {
        setBusy(true);
        setError(null);
        router.patch(
            `/staff/orders/${order.number}/lines`,
            {
                lines: order.lines.map((line) => ({
                    id: line.id,
                    quantity: quantities[line.id] ?? line.quantity,
                })),
                note,
            },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (errors) =>
                    setError(
                        errors.order ??
                            errors.lines ??
                            Object.values(errors)[0] ??
                            null,
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_orders.edit.title', { number: order.number })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_orders.edit.body')}
                    </DialogDescription>
                </DialogHeader>
                <ul className="grid gap-2">
                    {order.lines.map((line) => {
                        const quantity = quantities[line.id] ?? line.quantity;

                        return (
                            <li
                                key={line.id}
                                className={cn(
                                    'flex items-center justify-between gap-3 rounded-md border border-line p-3',
                                    quantity === 0 && 'bg-clay opacity-70',
                                )}
                            >
                                <div className="min-w-0">
                                    <p
                                        className={cn(
                                            'font-semibold',
                                            quantity === 0 && 'line-through',
                                        )}
                                    >
                                        {line.name}
                                    </p>
                                    <p className="text-sm text-ink-muted">
                                        {quantity === 0
                                            ? t('staff_orders.edit.removed')
                                            : formatMoney(
                                                  line.unit_price * quantity,
                                              )}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label={t(
                                            'staff_orders.edit.remove',
                                            { item: line.name },
                                        )}
                                        onClick={() =>
                                            setQuantities({
                                                ...quantities,
                                                [line.id]: 0,
                                            })
                                        }
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label={t(
                                            'staff_orders.edit.quantity',
                                            { item: line.name },
                                        )}
                                        disabled={quantity <= 0}
                                        onClick={() =>
                                            setQuantities({
                                                ...quantities,
                                                [line.id]: quantity - 1,
                                            })
                                        }
                                    >
                                        <Minus aria-hidden="true" />
                                    </Button>
                                    <span
                                        className="w-8 text-center font-semibold tabular-nums"
                                        aria-live="polite"
                                    >
                                        {quantity}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label={t(
                                            'staff_orders.edit.quantity',
                                            { item: line.name },
                                        )}
                                        disabled={quantity >= 20}
                                        onClick={() =>
                                            setQuantities({
                                                ...quantities,
                                                [line.id]: quantity + 1,
                                            })
                                        }
                                    >
                                        <Plus aria-hidden="true" />
                                    </Button>
                                </div>
                            </li>
                        );
                    })}
                </ul>
                <div className="grid gap-2">
                    <Label htmlFor="edit-note">
                        {t('staff_orders.edit.note')}
                    </Label>
                    <Input
                        id="edit-note"
                        value={note}
                        maxLength={200}
                        placeholder={t('staff_orders.edit.note_placeholder')}
                        onChange={(event) => setNote(event.target.value)}
                    />
                </div>
                <p
                    className="flex items-baseline justify-between text-sm"
                    aria-live="polite"
                >
                    <span className="text-ink-muted">
                        {t('staff_orders.totals.subtotal')} ·{' '}
                        {t('staff_orders.edit.was', {
                            amount: formatMoney(order.totals.subtotal),
                        })}
                    </span>
                    <strong className="text-title tabular-nums">
                        {formatMoney(subtotal)}
                    </strong>
                </p>
                {error && (
                    <p role="alert" className="text-sm text-chilli">
                        {error}
                    </p>
                )}
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('staff_orders.edit.cancel')}
                    </Button>
                    <Button onClick={save} disabled={busy || !online || !kept}>
                        {t('staff_orders.edit.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
