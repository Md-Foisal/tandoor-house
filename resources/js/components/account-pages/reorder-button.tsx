import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

export function ReorderButton({
    number,
    variant = 'outline',
}: {
    number: string;
    variant?: 'outline' | 'default';
}) {
    const t = useT();
    const online = useOnline();
    const [sending, setSending] = useState(false);
    const [failed, setFailed] = useState(false);

    const reorder = () => {
        setFailed(false);
        router.post(
            `/account/orders/${number}/reorder`,
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onError: () => setFailed(true),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <div className="flex flex-col items-start gap-1">
            <Button
                variant={variant}
                disabled={sending || !online}
                aria-label={t('account.orders.reorder_label', { number })}
                onClick={reorder}
            >
                <RotateCcw aria-hidden="true" />
                {sending
                    ? t('account.orders.reordering')
                    : t('account.orders.reorder')}
            </Button>
            {failed && (
                <p role="alert" className="text-sm text-chilli">
                    {t('account.orders.reorder_failed')}
                </p>
            )}
        </div>
    );
}
