import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { OrderDetailData } from './types';

type Kind = 'full' | 'lines' | 'amount';

export function RefundDialog({
    order,
    limit,
    open,
    onOpenChange,
}: {
    order: OrderDetailData;
    limit: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const t = useT();
    const online = useOnline();
    const [kind, setKind] = useState<Kind>('full');
    const [quantities, setQuantities] = useState<Record<number, number>>({});
    const [pounds, setPounds] = useState('');
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setKind('full');
            setQuantities({});
            setPounds('');
            setReason('');
            setErrors({});
        }
    }, [open]);

    const left = order.paid - order.refunded;
    const allowed =
        limit === null
            ? left
            : Math.min(left, Math.max(0, limit - order.refunded));
    const amount =
        kind === 'full'
            ? left
            : kind === 'amount'
              ? Math.round((Number.parseFloat(pounds) || 0) * 100)
              : order.lines.reduce(
                    (sum, line) =>
                        sum + line.unit_price * (quantities[line.id] ?? 0),
                    0,
                );
    const tooMuch = amount > allowed;
    const ready = amount > 0 && !tooMuch && reason.trim().length >= 3;

    const submit = () => {
        setBusy(true);
        setErrors({});
        router.post(
            `/staff/orders/${order.number}/refund`,
            {
                kind,
                reason,
                ...(kind === 'amount' ? { amount } : {}),
                ...(kind === 'lines' ? { lines: quantities } : {}),
            },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (found) => setErrors(found),
                onFinish: () => setBusy(false),
            },
        );
    };

    const tabs: { key: Kind; label: string }[] = [
        { key: 'full', label: t('staff_orders.refund.full') },
        { key: 'lines', label: t('staff_orders.refund.by_lines') },
        { key: 'amount', label: t('staff_orders.refund.by_amount') },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_orders.refund.title', {
                            number: order.number,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_orders.refund.body')}
                    </DialogDescription>
                </DialogHeader>
                <p className="text-sm">
                    {t('staff_orders.refund.left', {
                        amount: formatMoney(left),
                    })}
                    {limit !== null && (
                        <span className="text-ink-muted">
                            {' '}
                            {t('staff_orders.refund.limit', {
                                limit: formatMoney(limit),
                            })}
                        </span>
                    )}
                </p>
                <div role="radiogroup" className="grid grid-cols-3 gap-2">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            type="button"
                            role="radio"
                            aria-checked={kind === tab.key}
                            onClick={() => setKind(tab.key)}
                            className={cn(
                                'min-h-11 rounded-md border px-2 text-sm font-semibold',
                                kind === tab.key
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line-strong bg-surface hover:bg-clay',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
                {kind === 'lines' && (
                    <ul className="grid gap-2">
                        {order.lines.map((line) => (
                            <li
                                key={line.id}
                                className="flex items-center justify-between gap-3 rounded-md border border-line p-3"
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold">{line.name}</p>
                                    <p className="text-sm text-ink-muted">
                                        {formatMoney(line.unit_price)} ×{' '}
                                        {line.quantity}
                                    </p>
                                </div>
                                <Input
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={line.quantity}
                                    className="w-20"
                                    aria-label={t(
                                        'staff_orders.refund.refund_quantity',
                                        {
                                            item: line.name,
                                        },
                                    )}
                                    value={quantities[line.id] ?? 0}
                                    onChange={(event) =>
                                        setQuantities({
                                            ...quantities,
                                            [line.id]: Math.min(
                                                line.quantity,
                                                Math.max(
                                                    0,
                                                    Number.parseInt(
                                                        event.target.value,
                                                        10,
                                                    ) || 0,
                                                ),
                                            ),
                                        })
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                )}
                {kind === 'amount' && (
                    <div className="grid gap-2">
                        <Label htmlFor="refund-amount">
                            {t('staff_orders.refund.amount')}
                        </Label>
                        <Input
                            id="refund-amount"
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.01"
                            value={pounds}
                            aria-invalid={tooMuch || Boolean(errors.amount)}
                            onChange={(event) => setPounds(event.target.value)}
                        />
                    </div>
                )}
                <div className="grid gap-2">
                    <Label htmlFor="refund-reason">
                        {t('staff_orders.refund.reason')}
                    </Label>
                    <Textarea
                        id="refund-reason"
                        value={reason}
                        maxLength={200}
                        placeholder={t(
                            'staff_orders.refund.reason_placeholder',
                        )}
                        aria-invalid={Boolean(errors.reason)}
                        onChange={(event) => setReason(event.target.value)}
                    />
                </div>
                {(tooMuch ||
                    errors.amount ||
                    errors.lines ||
                    errors.reason) && (
                    <p role="alert" className="text-sm text-chilli">
                        {errors.amount ??
                            errors.lines ??
                            errors.reason ??
                            t('staff_orders.refund.too_much', {
                                max: formatMoney(allowed),
                            })}
                    </p>
                )}
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('staff_orders.edit.cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={busy || !online || !ready}
                    >
                        {t('staff_orders.refund.confirm', {
                            amount: formatMoney(amount),
                        })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
