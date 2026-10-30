import { router } from '@inertiajs/react';
import { useState } from 'react';
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

export function CancelOrder({
    number,
    paidByCard,
    total,
}: {
    number: string;
    paidByCard: boolean;
    total: number | null;
}) {
    const t = useT();
    const online = useOnline();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = () => {
        setBusy(true);
        setError(null);
        router.post(
            `/orders/${number}/cancel`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (found) => setError(found.cancel ?? null),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <Button
                type="button"
                size="lg"
                variant="outline"
                className="min-h-11 self-start"
                onClick={() => {
                    setError(null);
                    setOpen(true);
                }}
            >
                {t('tracking.cancel.button')}
            </Button>

            <Dialog open={open} onOpenChange={(next) => !busy && setOpen(next)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('tracking.cancel.title', { number })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('tracking.cancel.body')}{' '}
                            {paidByCard && total !== null
                                ? t('tracking.cancel.refund_card', {
                                      amount: formatMoney(total),
                                  })
                                : t('tracking.cancel.refund_none')}
                        </DialogDescription>
                    </DialogHeader>
                    {error && (
                        <p
                            role="alert"
                            className="text-sm font-medium text-chilli"
                        >
                            {error}
                        </p>
                    )}
                    {!online && (
                        <p role="status" className="text-sm text-ink-muted">
                            {t('tracking.cancel.offline')}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            className="min-h-11"
                            onClick={() => setOpen(false)}
                            disabled={busy}
                        >
                            {t('tracking.cancel.keep')}
                        </Button>
                        <Button
                            variant="destructive"
                            className="min-h-11"
                            onClick={submit}
                            disabled={busy || !online}
                        >
                            {busy
                                ? t('tracking.cancel.working')
                                : t('tracking.cancel.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
