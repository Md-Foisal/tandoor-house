import { router } from '@inertiajs/react';
import { Banknote, CreditCard } from 'lucide-react';
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
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Tender = 'cash' | 'card';

export function TakePaymentDialog({
    number,
    amount,
    open,
    onOpenChange,
}: {
    number: string;
    amount: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const t = useT();
    const online = useOnline();
    const [tender, setTender] = useState<Tender>('cash');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setTender('cash');
            setError(null);
        }
    }, [open]);

    const submit = () => {
        setBusy(true);
        setError(null);
        router.post(
            `/staff/orders/${number}/payment`,
            { method: tender },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (found) => setError(found.method ?? null),
                onFinish: () => setBusy(false),
            },
        );
    };

    const options: { key: Tender; icon: typeof Banknote }[] = [
        { key: 'cash', icon: Banknote },
        { key: 'card', icon: CreditCard },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('staff_orders.take_payment.title', { number })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_orders.take_payment.body', {
                            amount: formatMoney(amount),
                        })}
                    </DialogDescription>
                </DialogHeader>
                <div
                    role="radiogroup"
                    aria-label={t('staff_orders.take_payment.button')}
                    className="grid gap-2 sm:grid-cols-2"
                >
                    {options.map(({ key, icon: Icon }) => (
                        <button
                            key={key}
                            type="button"
                            role="radio"
                            aria-checked={tender === key}
                            onClick={() => setTender(key)}
                            className={cn(
                                'flex min-h-16 items-center gap-3 rounded-md border px-3 py-2 text-left',
                                tender === key
                                    ? 'border-ink bg-ink text-paper'
                                    : 'border-line-strong bg-surface hover:bg-clay',
                            )}
                        >
                            <Icon aria-hidden="true" className="size-5" />
                            <span>
                                <span className="block font-semibold">
                                    {t(`staff_orders.take_payment.${key}`)}
                                </span>
                                <span className="block text-sm opacity-80">
                                    {t(`staff_orders.take_payment.${key}_hint`)}
                                </span>
                            </span>
                        </button>
                    ))}
                </div>
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
                    <Button
                        size="lg"
                        onClick={submit}
                        disabled={busy || !online}
                        title={
                            !online
                                ? t('staff_orders.offline_reason')
                                : undefined
                        }
                    >
                        {t('staff_orders.take_payment.confirm', {
                            amount: formatMoney(amount),
                        })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
