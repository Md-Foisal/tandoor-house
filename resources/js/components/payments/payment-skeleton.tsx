import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';

/** True while an Inertia visit or partial reload is in flight. */
export function useReloading(): boolean {
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const offStart = router.on('start', () => setBusy(true));
        const offFinish = router.on('finish', () => setBusy(false));

        return () => {
            offStart();
            offFinish();
        };
    }, []);

    return busy;
}

/** Shaped like the payment form: a few fields and the pay button. */
export function PaymentSkeleton() {
    const t = useT();

    return (
        <div className="grid gap-4" role="status">
            <span className="sr-only">{t('payments.panel.loading')}</span>
            <div className="grid gap-4" aria-hidden="true">
                <Skeleton className="h-12 w-full" />
                <div className="grid grid-cols-2 gap-4">
                    <Skeleton className="h-12" />
                    <Skeleton className="h-12" />
                </div>
                <Skeleton className="h-12 w-full" />
            </div>
        </div>
    );
}
