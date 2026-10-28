import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Vegetarian and vegan markers. Shape and letter carry the meaning as well
 * as colour, so they read for colour-blind guests too.
 */
export function DietMarks({
    vegetarian,
    vegan,
    className,
}: {
    vegetarian: boolean;
    vegan: boolean;
    className?: string;
}) {
    const t = useT();

    if (!vegetarian && !vegan) {
        return null;
    }

    const label = vegan ? t('ui.vegan') : t('ui.vegetarian');
    const short = vegan ? t('ui.vegan_short') : t('ui.vegetarian_short');

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1 rounded-sm bg-leaf-soft px-1.5 text-xs font-bold text-leaf',
                className,
            )}
            title={label}
        >
            <svg viewBox="0 0 12 12" aria-hidden="true" className="size-3">
                <path
                    d="M2 10C2 5.5 5 2 10 2c0 5-3.5 8-8 8Zm0 0 4-4"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                />
            </svg>
            <span aria-hidden="true">{short}</span>
            <span className="sr-only">{label}</span>
        </span>
    );
}

function Chilli({ filled }: { filled: boolean }) {
    return (
        <svg
            viewBox="0 0 12 16"
            aria-hidden="true"
            className={cn(
                'h-4 w-3',
                filled ? 'text-chilli' : 'text-line-strong/50',
            )}
        >
            <path
                d="M7.4 2.3C7.2 1.4 7.6.8 8.4.6M6.6 3.4c1.9-.3 3.3.7 3.3 2.6 0 4.4-3.4 8.6-7.8 9.4-.9.2-1.3-.7-.6-1.2 3-2.2 3.9-5 3.6-8.2-.1-1.3.5-2.4 1.5-2.6Z"
                fill={filled ? 'currentColor' : 'none'}
                stroke="currentColor"
                strokeWidth="1.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

export function SpiceMeter({
    level,
    className,
    showLabel = false,
}: {
    level: number;
    className?: string;
    showLabel?: boolean;
}) {
    const t = useT();

    if (level === 0 && !showLabel) {
        return null;
    }

    const label = t(`ui.spice.${level}`);

    return (
        <span
            className={cn('inline-flex items-center gap-1', className)}
            title={label}
        >
            <span className="inline-flex" aria-hidden="true">
                {[1, 2, 3].map((step) => (
                    <Chilli key={step} filled={step <= level} />
                ))}
            </span>
            <span className={showLabel ? 'text-sm text-ink-muted' : 'sr-only'}>
                {label}
            </span>
        </span>
    );
}
