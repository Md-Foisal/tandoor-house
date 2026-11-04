import { Link2, Check } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { forwardRef } from 'react';
import { Elapsed } from '@/components/staff/elapsed';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { duration, ease } from '@/lib/motion';
import { cn } from '@/lib/utils';
import { RequestBadge, StatusLabel, isOccupied, statusMeta } from './status';
import type { FloorTable } from './types';
import { formatClock } from './use-now';

/** Spoken after the visible text, so the button's name stays the same as what is shown. */
export function TableDescription({ table }: { table: FloorTable }) {
    const t = useT();

    return (
        <span id={`table-${table.id}-seats`} className="sr-only">
            {t('staff_floor.tile.seats', { count: table.group_seats })}
        </span>
    );
}

/** The pulse that says a request just arrived. State-change motion only. */
export function RequestBadges({
    table,
    fresh,
}: {
    table: FloorTable;
    fresh: Set<number>;
}) {
    const reduced = useReducedMotion();
    const kinds = Array.from(new Set(table.requests.map((r) => r.type)));

    return (
        <>
            {kinds.map((type) => {
                const isFresh = table.requests.some(
                    (r) => r.type === type && fresh.has(r.id),
                );

                return (
                    <motion.span
                        key={type}
                        initial={isFresh ? { opacity: 0, scale: 0.8 } : false}
                        animate={
                            isFresh && !reduced
                                ? { opacity: 1, scale: [0.8, 1.2, 1] }
                                : { opacity: 1, scale: 1 }
                        }
                        transition={{
                            duration: reduced ? duration.fast : duration.slow,
                            ease: ease.out,
                        }}
                    >
                        <RequestBadge type={type} fresh={isFresh} />
                    </motion.span>
                );
            })}
        </>
    );
}

type TileProps = {
    table: FloorTable;
    fresh: Set<number>;
    selecting: boolean;
    selected: boolean;
    selectable: boolean;
    tabIndex: number;
    onActivate: () => void;
    onFocus: () => void;
};

export const TableTile = forwardRef<HTMLButtonElement, TileProps>(
    function TableTile(
        {
            table,
            fresh,
            selecting,
            selected,
            selectable,
            tabIndex,
            onActivate,
            onFocus,
        },
        ref,
    ) {
        const t = useT();
        const meta = statusMeta[table.status];
        const joined = table.merged_into_id !== null;
        const roomy = table.height >= 2;
        const radius =
            table.shape === 'round'
                ? 'rounded-3xl'
                : table.shape === 'rect'
                  ? 'rounded-md'
                  : 'rounded-lg';

        return (
            <>
                <TableDescription table={table} />
                <button
                    ref={ref}
                    type="button"
                    data-table-id={table.id}
                    tabIndex={tabIndex}
                    aria-describedby={`table-${table.id}-seats`}
                    aria-pressed={selecting ? selected : undefined}
                    aria-disabled={selecting && !selectable ? true : undefined}
                    onClick={onActivate}
                    onFocus={onFocus}
                    className={cn(
                        'relative flex h-full w-full flex-col items-start justify-start gap-0.5 overflow-hidden border-2 p-1.5 text-left text-xs text-ink outline-hidden transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember focus-visible:outline-solid',
                        meta.tile,
                        radius,
                        joined && 'border-dashed',
                        selected && 'ring-4 ring-ember ring-offset-0',
                        selecting && !selectable && 'opacity-45',
                    )}
                >
                    <span className="flex w-full items-center justify-between gap-1">
                        <span className="text-base leading-none font-bold">
                            {table.label}
                        </span>
                        <span className="text-xs text-ink-muted tabular-nums">
                            {t('staff_floor.tile.seats_short', {
                                count: table.group_seats,
                            })}
                        </span>
                    </span>
                    <StatusLabel
                        status={table.status}
                        className="text-xs leading-tight"
                    />
                    {joined && (
                        <span className="inline-flex items-center gap-1 text-ink-muted">
                            <Link2 className="size-3.5" aria-hidden />
                            {table.merged_into_label}
                        </span>
                    )}
                    {!joined && table.merged_labels.length > 0 && (
                        <span className="inline-flex items-center gap-1 text-ink-muted">
                            <Link2 className="size-3.5" aria-hidden />+
                            {table.merged_labels.join(' +')}
                        </span>
                    )}
                    {roomy && table.party && (
                        <span className="w-full truncate font-semibold">
                            {table.party.name ?? t('staff_floor.tile.walk_in')}
                            {table.party.size ? ` · ${table.party.size}` : ''}
                        </span>
                    )}
                    {roomy && !isOccupied(table) && table.booking && (
                        <span className="w-full truncate font-semibold">
                            {t('staff_floor.tile.booking_at', {
                                name: table.booking.name,
                                time: formatClock(table.booking.starts_at),
                            })}
                        </span>
                    )}
                    {roomy && isOccupied(table) && (
                        <span className="flex w-full items-center gap-1.5 tabular-nums">
                            <Elapsed
                                since={table.seated_at}
                                warnAfter={75}
                                lateAfter={105}
                            />
                            {table.open_total > 0 && (
                                <span className="text-ink-muted">
                                    {formatMoney(table.open_total)}
                                </span>
                            )}
                        </span>
                    )}
                    {table.requests.length > 0 && (
                        <span className="absolute right-1 bottom-1 flex flex-wrap justify-end gap-1">
                            <RequestBadges table={table} fresh={fresh} />
                        </span>
                    )}
                    {selecting && (
                        <span
                            aria-hidden
                            className={cn(
                                'absolute top-1 right-1 grid size-5 place-items-center rounded-sm border-2 border-line-strong bg-surface',
                                selected &&
                                    'border-ember bg-ember text-ember-ink',
                            )}
                        >
                            {selected && <Check className="size-3.5" />}
                        </span>
                    )}
                </button>
            </>
        );
    },
);
