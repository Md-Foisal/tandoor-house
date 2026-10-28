import { MapPin, Pencil, Star, Trash2 } from 'lucide-react';
import { Settle } from '@/components/motion/served';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { SavedAddress } from './types';

export function AddressCard({
    address,
    busy,
    onEdit,
    onDelete,
    onMakeDefault,
}: {
    address: SavedAddress;
    busy: boolean;
    onEdit: () => void;
    onDelete: () => void;
    onMakeDefault: () => void;
}) {
    const t = useT();
    const lines = [address.line1, address.line2, address.city].filter(Boolean);

    return (
        <Settle tilt={0}>
            <article
                aria-labelledby={`address-${address.id}`}
                className={cn(
                    'flex h-full flex-col gap-4 rounded-lg border bg-surface p-5 shadow-plate',
                    address.is_default ? 'border-ember' : 'border-line',
                )}
            >
                <header className="flex items-start justify-between gap-3">
                    <h2
                        id={`address-${address.id}`}
                        className="text-title font-semibold"
                    >
                        {address.label}
                    </h2>
                    {address.is_default && (
                        <Badge>
                            <Star aria-hidden="true" />
                            {t('addresses.default')}
                        </Badge>
                    )}
                </header>

                <address className="flex flex-col text-ink not-italic">
                    {lines.map((line) => (
                        <span key={line}>{line}</span>
                    ))}
                    <span className="font-semibold tracking-wide">
                        {address.postcode}
                    </span>
                </address>

                {address.instructions && (
                    <p className="rounded-md bg-clay px-3 py-2 text-sm text-ink">
                        <span className="font-semibold">
                            {t('addresses.instructions')}:{' '}
                        </span>
                        {address.instructions}
                    </p>
                )}

                <p
                    className={cn(
                        'flex items-center gap-2 text-sm font-medium',
                        address.zone ? 'text-leaf' : 'text-turmeric',
                    )}
                >
                    <MapPin className="size-4 shrink-0" aria-hidden="true" />
                    {address.zone
                        ? t('addresses.zone.inside', {
                              fee:
                                  address.zone.fee === 0
                                      ? t('addresses.zone.free')
                                      : formatMoney(address.zone.fee),
                          })
                        : t('addresses.zone.outside')}
                </p>

                <div className="mt-auto flex flex-wrap gap-2 border-t border-line pt-4">
                    <Button
                        variant="outline"
                        size="sm"
                        className="min-h-11"
                        onClick={onEdit}
                        disabled={busy}
                        aria-label={t('addresses.edit_aria', {
                            label: address.label,
                        })}
                    >
                        <Pencil aria-hidden="true" />
                        {t('addresses.edit')}
                    </Button>
                    {!address.is_default && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="min-h-11"
                            onClick={onMakeDefault}
                            disabled={busy}
                            aria-label={t('addresses.make_default_aria', {
                                label: address.label,
                            })}
                        >
                            <Star aria-hidden="true" />
                            {t('addresses.make_default')}
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        className="min-h-11 text-chilli hover:text-chilli sm:ml-auto"
                        onClick={onDelete}
                        disabled={busy}
                        aria-label={t('addresses.delete_aria', {
                            label: address.label,
                        })}
                    >
                        <Trash2 aria-hidden="true" />
                        {t('addresses.delete')}
                    </Button>
                </div>
            </article>
        </Settle>
    );
}

export function AddressCardSkeleton() {
    return (
        <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5">
            <div className="h-6 w-24 animate-pulse rounded-md bg-clay" />
            <div className="flex flex-col gap-2">
                <div className="h-4 w-48 animate-pulse rounded-md bg-clay" />
                <div className="h-4 w-32 animate-pulse rounded-md bg-clay" />
                <div className="h-4 w-20 animate-pulse rounded-md bg-clay" />
            </div>
            <div className="h-4 w-44 animate-pulse rounded-md bg-clay" />
            <div className="h-11 w-full animate-pulse rounded-md bg-clay" />
        </div>
    );
}
