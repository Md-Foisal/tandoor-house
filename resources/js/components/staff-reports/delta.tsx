import { ArrowDown, ArrowUp, Minus } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { formatPercent } from './format';
import type { Metric } from './types';

export type DeltaTone = 'good' | 'bad' | 'plain';

export function deltaTone(
    metric: Pick<Metric, 'change' | 'direction'>,
): DeltaTone {
    if (
        metric.change === null ||
        metric.change === 0 ||
        metric.direction === 'none'
    ) {
        return 'plain';
    }

    const rising = metric.change > 0;

    return (metric.direction === 'up') === rising ? 'good' : 'bad';
}

/**
 * Change against the previous period: an arrow, a sign and a percentage, with
 * colour as extra. Screen readers hear the words.
 */
export function Delta({
    metric,
    className,
}: {
    metric: Pick<Metric, 'change' | 'direction'>;
    className?: string;
}) {
    const t = useT();
    const tone = deltaTone(metric);
    const change = metric.change;

    const colour = cn(
        tone === 'good' && 'text-leaf',
        tone === 'bad' && 'text-chilli',
        tone === 'plain' && 'text-ink-muted',
    );

    if (change === null) {
        return (
            <span className={cn('text-sm text-ink-muted', className)}>
                {t('staff_reports.overview.no_baseline')}
            </span>
        );
    }

    const Icon = change > 0 ? ArrowUp : change < 0 ? ArrowDown : Minus;
    const word =
        change > 0
            ? t('staff_reports.overview.up')
            : change < 0
              ? t('staff_reports.overview.down')
              : t('staff_reports.overview.same');
    const sign = change > 0 ? '+' : change < 0 ? '−' : '';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 text-sm font-semibold tabular-nums',
                colour,
                className,
            )}
        >
            <Icon aria-hidden="true" className="size-4" />
            <span aria-hidden="true">
                {sign}
                {formatPercent(Math.abs(change))}
            </span>
            <span className="sr-only">
                {change === 0
                    ? word
                    : `${word} ${formatPercent(Math.abs(change))}`}
            </span>
            <span className="font-normal text-ink-muted">
                {t('staff_reports.overview.vs_previous')}
            </span>
        </span>
    );
}
