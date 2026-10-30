import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { OrderCardData } from './types';
import { ReasonDialog } from './reason-dialog';

type Props = {
    order: Pick<OrderCardData, 'number' | 'next' | 'can_reject' | 'can_cancel'>;
    /** Partial-reload keys; the detail page reloads everything. */
    only?: string[];
    className?: string;
};

/**
 * Next-step button plus reject / cancel with a reason. Used on the board
 * cards and on the order page, so both behave the same.
 */
export function OrderActions({ order, only, className }: Props) {
    const t = useT();
    const online = useOnline();
    const [busy, setBusy] = useState(false);
    const [dialog, setDialog] = useState<'rejected' | 'cancelled' | null>(null);
    const [error, setError] = useState<string | null>(null);

    const send = (status: string, reason?: string) => {
        setBusy(true);
        setError(null);
        router.post(
            `/staff/orders/${order.number}/status`,
            { status, reason },
            {
                preserveScroll: true,
                preserveState: true,
                only,
                onError: (errors) => {
                    setError(errors.reason ?? errors.status ?? null);
                },
                onSuccess: () => setDialog(null),
                onFinish: () => setBusy(false),
            },
        );
    };

    if (!order.next && !order.can_reject && !order.can_cancel) {
        return null;
    }

    const reason = !online ? t('staff_orders.offline_reason') : undefined;

    return (
        <div className={cn('flex flex-wrap items-center gap-2', className)}>
            {order.next && (
                <Button
                    size="lg"
                    className="flex-[2_1_9rem]"
                    disabled={busy || !online}
                    title={reason}
                    onClick={() => send(order.next as string)}
                >
                    {t(`staff_orders.action.${order.next}`)}
                </Button>
            )}
            {order.can_reject && (
                <Button
                    variant="outline"
                    disabled={busy || !online}
                    title={reason}
                    onClick={() => setDialog('rejected')}
                >
                    {t('staff_orders.action.reject')}
                </Button>
            )}
            {order.can_cancel && !order.can_reject && (
                <Button
                    variant="outline"
                    disabled={busy || !online}
                    title={reason}
                    onClick={() => setDialog('cancelled')}
                >
                    {t('staff_orders.action.cancel')}
                </Button>
            )}
            {reason && (
                <p className="w-full text-sm text-ink-muted">{reason}</p>
            )}
            <ReasonDialog
                mode={dialog}
                number={order.number}
                busy={busy}
                error={error}
                onClose={() => {
                    setDialog(null);
                    setError(null);
                }}
                onConfirm={(text) => dialog && send(dialog, text)}
            />
        </div>
    );
}
