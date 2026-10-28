import { router } from '@inertiajs/react';
import { BellRing, Check, Receipt } from 'lucide-react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import type { OpenRequests } from './types';

type Kind = 'call_waiter' | 'bill';

/**
 * Call a waiter / ask for the bill. One open request of each kind per table:
 * once asked, the button shows "On its way" with the time and stays disabled.
 */
export function HelpButtons({
    token,
    requests,
    className,
}: {
    token: string;
    requests: OpenRequests;
    className?: string;
}) {
    const t = useT();
    const online = useOnline();
    const [pending, setPending] = useState<Kind | null>(null);

    const ask = (type: Kind) => {
        setPending(type);
        router.post(
            `/t/${token}/requests`,
            { type },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setPending(null),
            },
        );
    };

    const items: { type: Kind; icon: typeof Receipt; label: string }[] = [
        {
            type: 'call_waiter',
            icon: BellRing,
            label: t('table.help.call_waiter'),
        },
        { type: 'bill', icon: Receipt, label: t('table.help.bill') },
    ];

    return (
        <div className={className}>
            <div className="grid grid-cols-2 gap-2">
                {items.map(({ type, icon: Icon, label }) => {
                    const askedAt = requests[type];

                    return (
                        <Button
                            key={type}
                            type="button"
                            variant="outline"
                            className="h-auto min-h-11 flex-col gap-0 py-1.5"
                            disabled={
                                askedAt !== null || pending !== null || !online
                            }
                            onClick={() => ask(type)}
                        >
                            <span className="inline-flex items-center gap-2">
                                {askedAt ? (
                                    <Check aria-hidden="true" />
                                ) : (
                                    <Icon aria-hidden="true" />
                                )}
                                {askedAt ? t('table.help.on_its_way') : label}
                            </span>
                            {askedAt && (
                                <span className="text-xs font-normal text-ink-muted">
                                    {label}
                                    {' · '}
                                    {t('table.help.asked_at', {
                                        time: askedAt,
                                    })}
                                </span>
                            )}
                        </Button>
                    );
                })}
            </div>
            {!online && (
                <p className="mt-2 text-sm text-ink-muted">
                    {t('table.help.offline')}
                </p>
            )}
        </div>
    );
}
