import { router } from '@inertiajs/react';
import { ChevronUp, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AllergenList } from '@/components/brand/allergen-list';
import { QuantityStepper } from '@/components/brand/quantity-stepper';
import { EmptyState, ErrorState, useOnline } from '@/components/brand/states';
import { StickyActionBar } from '@/components/brand/sticky-action-bar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { OpenRequests, TableBasket } from './types';
import { HelpButtons } from './help-buttons';

export function OrderBar({
    token,
    basket,
    serviceRate,
    requests,
}: {
    token: string;
    basket: TableBasket;
    serviceRate: number;
    requests: OpenRequests;
}) {
    const t = useT();
    const online = useOnline();
    const [open, setOpen] = useState(false);
    const [sending, setSending] = useState(false);
    const [failed, setFailed] = useState(false);
    const [ageConfirmed, setAgeConfirmed] = useState(false);
    const [ageError, setAgeError] = useState<string | null>(null);
    const [optimistic, setOptimistic] = useState<Record<string, number>>({});

    const empty = basket.lines.length === 0;
    const needsAge = basket.has_alcohol && !ageConfirmed;
    const blocked =
        empty || !online || basket.has_unavailable || sending || needsAge;

    const setQuantity = (key: string, quantity: number) => {
        setFailed(false);
        setOptimistic((current) => ({ ...current, [key]: quantity }));
        router.patch(
            `/basket/lines/${key}`,
            { quantity },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setFailed(true),
                onFinish: () =>
                    setOptimistic((current) =>
                        Object.fromEntries(
                            Object.entries(current).filter(([k]) => k !== key),
                        ),
                    ),
            },
        );
    };

    const remove = (key: string) =>
        router.delete(`/basket/lines/${key}`, {
            preserveScroll: true,
            preserveState: true,
            onError: () => setFailed(true),
        });

    const send = () => {
        setSending(true);
        setFailed(false);
        setAgeError(null);
        router.post(
            `/t/${token}/order`,
            { age_confirmed: basket.has_alcohol && ageConfirmed },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (errors) => {
                    setFailed(true);
                    setAgeError(errors.age_confirmed ?? null);
                    toast.error(
                        errors.basket ??
                            errors.age_confirmed ??
                            t('table.sheet.failed'),
                    );
                },
                onFinish: () => setSending(false),
            },
        );
    };

    const barLabel = t('table.bar.label', {
        count: basket.count,
        total: formatMoney(basket.subtotal),
    });

    const blockReason = !online
        ? t('table.sheet.send_offline')
        : basket.has_unavailable
          ? t('table.sheet.sold_out')
          : null;

    return (
        <>
            <StickyActionBar className="flex flex-col gap-2 md:fixed md:inset-x-0 md:bottom-0 md:border-t md:bg-paper/95 md:px-4 md:py-3">
                <div className="container-page mx-auto flex w-full max-w-3xl flex-col gap-2 px-0">
                    <HelpButtons token={token} requests={requests} />
                    <Button
                        type="button"
                        size="lg"
                        className="w-full justify-between"
                        onClick={() => setOpen(true)}
                        aria-haspopup="dialog"
                    >
                        <span aria-live="polite">
                            {empty ? t('table.sheet.title') : barLabel}
                        </span>
                        <ChevronUp aria-hidden="true" />
                    </Button>
                </div>
            </StickyActionBar>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="bottom"
                    className="max-h-[90dvh] rounded-t-lg"
                >
                    <SheetHeader>
                        <SheetTitle>{t('table.sheet.title')}</SheetTitle>
                        <SheetDescription>
                            {t('table.sheet.description')}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex-1 overflow-y-auto px-4">
                        {failed && (
                            <ErrorState
                                className="mb-3"
                                onRetry={() => router.reload()}
                            />
                        )}
                        {empty ? (
                            <EmptyState
                                title={t('table.sheet.empty_title')}
                                description={t('table.sheet.empty_body')}
                            />
                        ) : (
                            <ul className="divide-y divide-line border-y border-line">
                                {basket.lines.map((line) => {
                                    const quantity =
                                        optimistic[line.key] ?? line.quantity;

                                    return (
                                        <li
                                            key={line.key}
                                            className="flex flex-col gap-1.5 py-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="font-semibold text-ink">
                                                    {line.name}
                                                    {line.variant && (
                                                        <span className="font-normal text-ink-muted">
                                                            {' · '}
                                                            {line.variant}
                                                        </span>
                                                    )}
                                                </p>
                                                <span className="font-semibold text-ink tabular-nums">
                                                    {formatMoney(
                                                        line.unit_price *
                                                            quantity,
                                                        { compact: false },
                                                    )}
                                                </span>
                                            </div>
                                            {line.modifiers.length > 0 && (
                                                <p className="text-sm text-ink-muted">
                                                    {line.modifiers
                                                        .map((m) => m.name)
                                                        .join(', ')}
                                                </p>
                                            )}
                                            <AllergenList
                                                allergens={line.allergens}
                                                compact
                                            />
                                            {!line.is_available && (
                                                <p
                                                    role="alert"
                                                    className="text-sm font-medium text-chilli"
                                                >
                                                    {t('ui.sold_out')}
                                                </p>
                                            )}
                                            <div className="flex items-center justify-between gap-3">
                                                <QuantityStepper
                                                    value={quantity}
                                                    onChange={(value) =>
                                                        setQuantity(
                                                            line.key,
                                                            value,
                                                        )
                                                    }
                                                    itemName={line.name}
                                                    disabled={
                                                        !online ||
                                                        !line.is_available
                                                    }
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    disabled={!online}
                                                    onClick={() =>
                                                        remove(line.key)
                                                    }
                                                    aria-label={t(
                                                        'table.sheet.remove',
                                                        { item: line.name },
                                                    )}
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                    {t('basket.remove')}
                                                </Button>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        {!empty && (
                            <dl
                                className="mt-4 flex flex-col gap-1.5 text-ink"
                                aria-live="polite"
                            >
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">
                                        {t('table.sheet.subtotal')}
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatMoney(basket.subtotal)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">
                                        {t('table.sheet.service', {
                                            rate: serviceRate / 100,
                                        })}
                                        <span className="block text-sm">
                                            {t('table.sheet.service_note')}
                                        </span>
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatMoney(basket.service_charge)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4 border-t border-line pt-2 text-title font-semibold">
                                    <dt>{t('table.sheet.total')}</dt>
                                    <dd className="tabular-nums">
                                        {formatMoney(basket.total)}
                                    </dd>
                                </div>
                            </dl>
                        )}
                    </div>
                    <div className="flex flex-col gap-2 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                        {basket.has_alcohol && !empty && (
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="table-age-confirmed"
                                    checked={ageConfirmed}
                                    onCheckedChange={(checked) =>
                                        setAgeConfirmed(checked === true)
                                    }
                                    aria-invalid={ageError ? true : undefined}
                                    aria-describedby={
                                        ageError || needsAge
                                            ? 'table-age-hint'
                                            : undefined
                                    }
                                    className="mt-0.5"
                                />
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="table-age-confirmed"
                                        className="font-normal"
                                    >
                                        {t('table.sheet.age_confirmed')}
                                    </Label>
                                    {(ageError || needsAge) && (
                                        <p
                                            id="table-age-hint"
                                            role={
                                                ageError ? 'alert' : undefined
                                            }
                                            className="text-sm font-medium text-chilli"
                                        >
                                            {ageError ??
                                                t('table.sheet.age_required')}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                        {blockReason && (
                            <p
                                role="alert"
                                className="text-sm font-medium text-chilli"
                            >
                                {blockReason}
                            </p>
                        )}
                        <Button
                            type="button"
                            size="lg"
                            className="w-full"
                            disabled={blocked}
                            onClick={send}
                        >
                            {sending
                                ? t('table.sheet.sending')
                                : t('table.sheet.send')}
                        </Button>
                        {!empty && (
                            <p className="text-center text-sm text-ink-muted">
                                {choice(
                                    t('basket.items', { count: basket.count }),
                                    basket.count,
                                )}
                            </p>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
