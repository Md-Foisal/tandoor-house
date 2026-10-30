import { chartBg, chartFill } from './types';

export type ShareItem = {
    key: string;
    label: string;
    value: number;
    share: number;
    valueLabel: string;
};

/**
 * One bar split by share (never more than five parts), a 2px gap between
 * parts, and a key that names each part with its value so colour is never
 * the only clue.
 */
export function ShareBar({
    items,
    ariaLabel,
}: {
    items: ShareItem[];
    ariaLabel: string;
}) {
    const shown = items.filter((item) => item.share > 0);
    let offset = 0;

    return (
        <div className="flex flex-col gap-3">
            <svg
                role="img"
                aria-label={ariaLabel}
                width="100%"
                height="20"
                className="block"
            >
                {shown.map((item) => {
                    const slot = items.indexOf(item);
                    const x = offset;
                    offset += item.share;

                    return (
                        <rect
                            key={item.key}
                            x={`${x}%`}
                            y="0"
                            style={{ width: `calc(${item.share}% - 2px)` }}
                            height="20"
                            rx="4"
                            className={chartFill[slot % 5]}
                        />
                    );
                })}
            </svg>
            <ul className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                {items.map((item, slot) => (
                    <li key={item.key} className="flex items-center gap-2">
                        <span
                            aria-hidden="true"
                            className={`size-3 shrink-0 rounded-[3px] ${chartBg[slot % 5]}`}
                        />
                        <span className="min-w-0 flex-1 truncate text-ink">
                            {item.label}
                        </span>
                        <span className="text-ink-muted tabular-nums">
                            {item.valueLabel} · {item.share.toFixed(1)}%
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
