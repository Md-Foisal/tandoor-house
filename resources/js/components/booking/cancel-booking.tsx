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
import type { CancelTerms } from './types';

export function CancelBooking({
    reference,
    summary,
    terms,
}: {
    reference: string;
    summary: { party: string; date: string; time: string };
    terms: CancelTerms;
}) {
    const t = useT();
    const online = useOnline();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const amount = formatMoney(terms.deposit_paid, { compact: true });
    const hasDeposit = terms.deposit_paid > 0;
    const rule = terms.free
        ? hasDeposit
            ? t('booking.cancel.free_deposit', {
                  deadline: terms.deadline_label,
                  amount,
              })
            : t('booking.cancel.free', { deadline: terms.deadline_label })
        : hasDeposit
          ? t('booking.cancel.late_deposit', { hours: terms.hours, amount })
          : t('booking.cancel.late', { hours: terms.hours });

    const submit = () => {
        setBusy(true);
        setError(null);
        router.post(
            `/book/${reference}/cancel`,
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
                variant="outline"
                className="min-h-11"
                onClick={() => {
                    setError(null);
                    setOpen(true);
                }}
            >
                {t('booking.cancel.button')}
            </Button>

            <Dialog open={open} onOpenChange={(next) => !busy && setOpen(next)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('booking.cancel.title')}</DialogTitle>
                        <DialogDescription>
                            {t('booking.cancel.body', summary)}
                        </DialogDescription>
                    </DialogHeader>
                    <p className="text-ink">{rule}</p>
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
                            {t('booking.cancel.offline')}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            className="min-h-11"
                            onClick={() => setOpen(false)}
                            disabled={busy}
                        >
                            {t('booking.cancel.keep')}
                        </Button>
                        <Button
                            variant="destructive"
                            className="min-h-11"
                            onClick={submit}
                            disabled={busy || !online}
                        >
                            {busy
                                ? t('booking.cancel.working')
                                : t('booking.cancel.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
