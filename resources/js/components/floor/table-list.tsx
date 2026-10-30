import { Link2, Users } from 'lucide-react';
import { Elapsed } from '@/components/staff/elapsed';
import { useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { StatusLabel, isOccupied, statusMeta } from './status';
import { RequestBadges, TableDescription } from './table-tile';
import type { FloorTable } from './types';
import { formatClock } from './use-now';

/** Phones get the plan as a list, grouped by area, with the same actions. */
export function TableList({
    areas,
    fresh,
    selecting,
    selectedIds,
    isSelectable,
    onActivate,
}: {
    areas: { name: string; tables: FloorTable[] }[];
    fresh: Set<number>;
    selecting: boolean;
    selectedIds: Set<number>;
    isSelectable: (table: FloorTable) => boolean;
    onActivate: (table: FloorTable) => void;
}) {
    const t = useT();

    return (
        <div className="flex flex-col gap-6">
            {areas.map((area) => (
                <section key={area.name} aria-labelledby={`area-${area.name}`}>
                    <h2
                        id={`area-${area.name}`}
                        className="sticky top-0 z-10 bg-paper py-2 font-display text-display-md"
                    >
                        {area.name}
                    </h2>
                    <ul className="flex flex-col gap-2">
                        {area.tables.map((table) => (
                            <li key={table.id}>
                                <TableDescription table={table} />
                                <button
                                    type="button"
                                    onClick={() => onActivate(table)}
                                    aria-describedby={`table-${table.id}-seats`}
                                    aria-pressed={
                                        selecting
                                            ? selectedIds.has(table.id)
                                            : undefined
                                    }
                                    className={cn(
                                        'flex min-h-16 w-full items-center gap-3 rounded-lg border-2 p-3 text-left outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember focus-visible:outline-solid',
                                        statusMeta[table.status].tile,
                                        table.merged_into_id !== null &&
                                            'border-dashed',
                                        selectedIds.has(table.id) &&
                                            'ring-4 ring-ember',
                                        selecting &&
                                            !isSelectable(table) &&
                                            'opacity-45',
                                    )}
                                >
                                    <span className="flex min-w-12 flex-col">
                                        <span className="text-xl leading-none font-bold">
                                            {table.label}
                                        </span>
                                        <span className="mt-1 inline-flex items-center gap-1 text-xs text-ink-muted">
                                            <Users
                                                className="size-3.5"
                                                aria-hidden
                                            />
                                            {table.group_seats}
                                        </span>
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
                                        <StatusLabel status={table.status} />
                                        {table.merged_into_label ? (
                                            <span className="inline-flex items-center gap-1 text-ink-muted">
                                                <Link2
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                {t(
                                                    'staff_floor.tile.joined_to',
                                                    {
                                                        lead: table.merged_into_label,
                                                    },
                                                )}
                                            </span>
                                        ) : null}
                                        {table.party && (
                                            <span className="truncate font-semibold">
                                                {table.party.name ??
                                                    t(
                                                        'staff_floor.tile.walk_in',
                                                    )}
                                                {table.party.size
                                                    ? ` · ${table.party.size}`
                                                    : ''}
                                            </span>
                                        )}
                                        {!isOccupied(table) &&
                                            table.booking && (
                                                <span className="truncate font-semibold">
                                                    {t(
                                                        'staff_floor.tile.booking_at',
                                                        {
                                                            name: table.booking
                                                                .name,
                                                            time: formatClock(
                                                                table.booking
                                                                    .starts_at,
                                                            ),
                                                        },
                                                    )}
                                                </span>
                                            )}
                                    </span>
                                    <span className="flex shrink-0 flex-col items-end gap-1 tabular-nums">
                                        {isOccupied(table) && (
                                            <Elapsed
                                                since={table.seated_at}
                                                warnAfter={75}
                                                lateAfter={105}
                                            />
                                        )}
                                        {table.open_total > 0 && (
                                            <span className="text-sm font-semibold">
                                                {formatMoney(table.open_total)}
                                            </span>
                                        )}
                                        <span className="flex flex-wrap justify-end gap-1">
                                            <RequestBadges
                                                table={table}
                                                fresh={fresh}
                                            />
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
