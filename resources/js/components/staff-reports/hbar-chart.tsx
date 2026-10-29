import { chartFill } from './types';

export type HBarRow = {
    key: string;
    label: string;
    value: number;
    valueLabel: string;
    slot?: number;
};

/**
 * Horizontal bars: the label is real text on the left, the bar is a thin SVG
 * rule with a rounded tip, and the value sits at the tip.
 */
export function HBarChart({
    rows,
    ariaLabel,
}: {
    rows: HBarRow[];
    ariaLabel: string;
}) {
    const max = Math.max(1, ...rows.map((row) => row.value));

    return (
        <div
            role="img"
            aria-label={ariaLabel}
            className="flex min-w-0 flex-col gap-2"
        >
            {rows.map((row) => {
                const pct = (row.value / max) * 100;

                return (
                    <div
                        key={row.key}
                        aria-hidden="true"
                        className="grid grid-cols-[minmax(0,7rem)_minmax(0,1fr)] items-center gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)]"
                    >
                        <p
                            className="truncate text-sm text-ink"
                            title={row.label}
                        >
                            {row.label}
                        </p>
                        <div className="flex min-w-0 items-center gap-2">
                            <svg
                                width="100%"
                                height="16"
                                className="min-w-0 flex-1"
                                preserveAspectRatio="none"
                            >
                                <rect
                                    x="0"
                                    y="0"
                                    width={`${Math.max(pct, row.value > 0 ? 1 : 0)}%`}
                                    height="16"
                                    rx="4"
                                    className={chartFill[row.slot ?? 0]}
                                />
                            </svg>
                            <span className="w-20 shrink-0 text-right text-sm text-ink tabular-nums">
                                {row.valueLabel}
                            </span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
