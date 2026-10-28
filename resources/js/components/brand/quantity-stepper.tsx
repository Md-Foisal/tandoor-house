import { Minus, Plus } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    itemName: string;
    disabled?: boolean;
    className?: string;
};

export function QuantityStepper({
    value,
    onChange,
    min = 1,
    max = 20,
    itemName,
    disabled = false,
    className,
}: Props) {
    const t = useT();
    const button =
        'grid size-11 place-items-center rounded-full text-ink outline-none transition-colors duration-150 hover:bg-clay focus-visible:outline-2 focus-visible:outline-ember disabled:opacity-40 disabled:hover:bg-transparent';

    return (
        <div
            className={cn(
                'inline-flex items-center rounded-full border-[1.5px] border-line-strong bg-surface',
                className,
            )}
        >
            <button
                type="button"
                className={button}
                onClick={() => onChange(value - 1)}
                disabled={disabled || value <= min}
                aria-label={t('ui.decrease', { item: itemName })}
            >
                <Minus className="size-4" aria-hidden="true" />
            </button>
            <output
                className="w-8 text-center text-base font-semibold tabular-nums"
                aria-live="polite"
                aria-label={t('ui.quantity')}
            >
                {value}
            </output>
            <button
                type="button"
                className={button}
                onClick={() => onChange(value + 1)}
                disabled={disabled || value >= max}
                aria-label={t('ui.increase', { item: itemName })}
            >
                <Plus className="size-4" aria-hidden="true" />
            </button>
        </div>
    );
}
