import { useState } from 'react';
import { chartBg, chartFill } from './types';
import { formatAxis, niceMax } from './format';
import { useWidth } from './use-width';

export type Bar = { key: string; label: string; values: number[] };

export type Band = { from: number; to: number; label: string };

const MARGIN = { top: 24, right: 8, bottom: 28, left: 44 };
const MAX_BAR = 24;
const GAP = 2;

function topRounded(x: number, y: number, w: number, h: number, r: number) {
    const radius = Math.min(r, w / 2, h);

    return `M${x},${y + h}V${y + radius}Q${x},${y} ${x + radius},${y}H${x + w - radius}Q${x + w},${y} ${x + w},${y + radius}V${y + h}Z`;
}

/**
 * Columns grown from one baseline, one or more stacked series, thin marks
 * with a 2px surface gap between segments. The hover tooltip is a bonus: the
 * table beside every chart carries the same numbers.
 */
export function BarChart({
    bars,
    series,
    ariaLabel,
    formatValue,
    bands = [],
    height = 220,
    labelPeak = false,
}: {
    bars: Bar[];
    series: { key: string; label: string }[];
    ariaLabel: string;
    formatValue: (pence: number) => string;
    bands?: Band[];
    height?: number;
    labelPeak?: boolean;
}) {
    const [ref, width] = useWidth<HTMLDivElement>();
    const [active, setActive] = useState<number | null>(null);

    const plotW = Math.max(width - MARGIN.left - MARGIN.right, 40);
    const plotH = height - MARGIN.top - MARGIN.bottom;
    const band = plotW / Math.max(bars.length, 1);
    const barW = Math.max(2, Math.min(MAX_BAR, band - 4));
    const totals = bars.map((bar) => bar.values.reduce((a, b) => a + b, 0));
    const max = niceMax(Math.max(0, ...totals));
    const scale = (value: number) => (value / max) * plotH;
    const base = MARGIN.top + plotH;
    const widest = Math.max(1, ...bars.map((bar) => bar.label.length));
    const every = Math.max(1, Math.ceil((widest * 6.5 + 10) / band));
    const peak = totals.indexOf(Math.max(...totals));
    const activeBar = active === null ? null : bars[active];
    const tipX =
        active === null
            ? 0
            : Math.min(
                  Math.max(MARGIN.left + (active + 0.5) * band, 70),
                  width - 70,
              );

    return (
        <div ref={ref} className="relative w-full">
            {series.length > 1 && (
                <ul className="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-muted">
                    {series.map((s, index) => (
                        <li key={s.key} className="flex items-center gap-2">
                            <span
                                aria-hidden="true"
                                className={`size-3 rounded-[3px] ${chartBg[index]}`}
                            />
                            {s.label}
                        </li>
                    ))}
                </ul>
            )}
            <svg
                role="img"
                aria-label={ariaLabel}
                width={width}
                height={height}
                viewBox={`0 0 ${width} ${height}`}
                className="block touch-pan-y"
                onPointerLeave={() => setActive(null)}
            >
                {bands.map((b) => (
                    <g key={b.label} aria-hidden="true">
                        <rect
                            x={MARGIN.left + b.from * band}
                            y={MARGIN.top - 6}
                            width={(b.to - b.from + 1) * band}
                            height={plotH + 6}
                            className="fill-clay opacity-60"
                        />
                        <text
                            x={MARGIN.left + ((b.from + b.to + 1) / 2) * band}
                            y={MARGIN.top - 10}
                            textAnchor="middle"
                            className="fill-ink-muted text-[11px] font-semibold"
                        >
                            {b.label}
                        </text>
                    </g>
                ))}

                <g aria-hidden="true">
                    {[0, 0.5, 1].map((fraction) => {
                        const y = base - fraction * plotH;

                        return (
                            <g key={fraction}>
                                <line
                                    x1={MARGIN.left}
                                    x2={width - MARGIN.right}
                                    y1={y}
                                    y2={y}
                                    className="stroke-line"
                                    strokeWidth={1}
                                />
                                <text
                                    x={MARGIN.left - 6}
                                    y={y + 4}
                                    textAnchor="end"
                                    className="fill-ink-muted text-[11px] tabular-nums"
                                >
                                    {formatAxis(max * fraction)}
                                </text>
                            </g>
                        );
                    })}

                    {bars.map((bar, index) => {
                        const cx = MARGIN.left + (index + 0.5) * band;
                        const x = cx - barW / 2;
                        const topSeries = bar.values.reduce(
                            (found, value, i) => (value > 0 ? i : found),
                            -1,
                        );
                        let below = 0;

                        return (
                            <g
                                key={bar.key}
                                className={
                                    active === null || active === index
                                        ? undefined
                                        : 'opacity-60'
                                }
                            >
                                {bar.values.map((value, i) => {
                                    if (value <= 0) {
                                        return null;
                                    }

                                    const top = base - scale(below + value);
                                    const full = scale(value);
                                    const h = Math.max(
                                        below > 0 ? full - GAP : full,
                                        1,
                                    );
                                    below += value;
                                    return i === topSeries ? (
                                        <path
                                            key={i}
                                            className={chartFill[i]}
                                            d={topRounded(x, top, barW, h, 4)}
                                        />
                                    ) : (
                                        <rect
                                            key={i}
                                            className={chartFill[i]}
                                            x={x}
                                            y={top}
                                            width={barW}
                                            height={h}
                                        />
                                    );
                                })}
                                {index % every === 0 && (
                                    <text
                                        x={cx}
                                        y={base + 16}
                                        textAnchor="middle"
                                        className="fill-ink-muted text-[11px]"
                                    >
                                        {bar.label}
                                    </text>
                                )}
                            </g>
                        );
                    })}

                    {labelPeak && totals[peak] > 0 && (
                        <text
                            x={Math.min(
                                Math.max(
                                    MARGIN.left + (peak + 0.5) * band,
                                    MARGIN.left + 24,
                                ),
                                width - 28,
                            )}
                            y={base - scale(totals[peak]) - 6}
                            textAnchor="middle"
                            className="fill-ink text-[11px] font-semibold tabular-nums"
                        >
                            {formatValue(totals[peak])}
                        </text>
                    )}

                    {bars.map((bar, index) => (
                        <rect
                            key={bar.key}
                            x={MARGIN.left + index * band}
                            y={MARGIN.top - 6}
                            width={band}
                            height={plotH + 6}
                            fill="transparent"
                            onPointerEnter={() => setActive(index)}
                            onPointerDown={() => setActive(index)}
                        />
                    ))}
                </g>
            </svg>

            {activeBar && (
                <div
                    aria-hidden="true"
                    style={{ left: tipX }}
                    className="pointer-events-none absolute top-8 z-10 -translate-x-1/2 rounded-md border border-line bg-surface px-3 py-2 text-sm shadow-[var(--shadow-plate-value)]"
                >
                    <p className="font-semibold whitespace-nowrap">
                        {activeBar.label}
                    </p>
                    {activeBar.values.map((value, i) => (
                        <p
                            key={series[i]?.key ?? i}
                            className="flex items-center gap-2 whitespace-nowrap text-ink-muted tabular-nums"
                        >
                            <span
                                className={`size-2.5 rounded-[2px] ${chartBg[i]}`}
                            />
                            {series.length > 1 && `${series[i]?.label}: `}
                            {formatValue(value)}
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}
