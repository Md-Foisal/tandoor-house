import { Trash2 } from 'lucide-react';
import { AllergenList } from '@/components/brand/allergen-list';
import { ArchFrame } from '@/components/brand/arch-frame';
import { Picture } from '@/components/brand/picture';
import { Price } from '@/components/brand/price';
import { QuantityStepper } from '@/components/brand/quantity-stepper';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BasketLineData } from './types';

type Props = {
    line: BasketLineData;
    quantity: number;
    onQuantity: (quantity: number) => void;
    onRemove: () => void;
    disabled?: boolean;
};

export function BasketLine({
    line,
    quantity,
    onQuantity,
    onRemove,
    disabled = false,
}: Props) {
    const t = useT();
    const soldOut = !line.is_available;

    return (
        <article
            className={cn('flex gap-4 py-5', soldOut && 'opacity-90')}
            aria-labelledby={`line-${line.key}`}
        >
            <ArchFrame ratio="portrait" className="h-24 w-[4.5rem] shrink-0">
                <Picture mediaKey={line.image} alt="" sizes="72px" />
            </ArchFrame>
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                <div className="flex items-start justify-between gap-3">
                    <h3
                        id={`line-${line.key}`}
                        className="text-title font-semibold text-ink"
                    >
                        {line.name}
                        {line.variant && (
                            <span className="font-normal text-ink-muted">
                                {' · '}
                                {line.variant}
                            </span>
                        )}
                    </h3>
                    <Price
                        pence={line.unit_price * quantity}
                        className="shrink-0 text-ink"
                        compact={false}
                    />
                </div>
                {line.modifiers.length > 0 && (
                    <p className="text-sm text-ink-muted">
                        {t('basket.with', {
                            modifiers: line.modifiers
                                .map((modifier) =>
                                    modifier.price > 0
                                        ? `${modifier.name} (+${formatMoney(modifier.price)})`
                                        : modifier.name,
                                )
                                .join(', '),
                        })}
                    </p>
                )}
                {line.notes && (
                    <p className="text-sm text-ink-muted italic">
                        {t('basket.notes', { notes: line.notes })}
                    </p>
                )}
                <AllergenList allergens={line.allergens} compact />
                <p className="text-sm text-ink-muted tabular-nums">
                    {t('basket.each', {
                        price: formatMoney(line.unit_price),
                    })}
                </p>
                {soldOut && (
                    <p
                        role="alert"
                        className="rounded-md bg-chilli-soft px-3 py-2 text-sm font-medium text-chilli"
                    >
                        {t('basket.sold_out_line')}
                    </p>
                )}
                <div className="mt-1 flex items-center justify-between gap-3">
                    <QuantityStepper
                        value={quantity}
                        onChange={onQuantity}
                        itemName={line.name}
                        disabled={disabled || soldOut}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={onRemove}
                        disabled={disabled}
                        aria-label={t('basket.remove_item', {
                            item: line.name,
                        })}
                    >
                        <Trash2 aria-hidden="true" />
                        {t('basket.remove')}
                    </Button>
                </div>
            </div>
        </article>
    );
}

export function BasketLineSkeleton() {
    return (
        <div className="flex gap-4 py-5" aria-hidden="true">
            <Skeleton className="h-24 w-[4.5rem] shrink-0 rounded-arch" />
            <div className="flex flex-1 flex-col gap-2">
                <div className="flex justify-between gap-3">
                    <Skeleton className="h-6 w-1/2" />
                    <Skeleton className="h-6 w-14" />
                </div>
                <Skeleton className="h-4 w-3/4" />
                <Skeleton className="h-4 w-1/2" />
                <div className="mt-1 flex justify-between">
                    <Skeleton className="h-11 w-32 rounded-full" />
                    <Skeleton className="h-11 w-24" />
                </div>
            </div>
        </div>
    );
}
