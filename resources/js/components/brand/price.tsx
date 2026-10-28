import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

export function Price({
    pence,
    className,
    compact = true,
}: {
    pence: number;
    className?: string;
    compact?: boolean;
}) {
    return (
        <span className={cn('font-semibold tabular-nums', className)}>
            {formatMoney(pence, { compact })}
        </span>
    );
}
