import { useMemo, useRef, useState } from 'react';
import { TableTile } from './table-tile';
import type { FloorTable } from './types';

const MIN_COLS = 8;
const MIN_ROWS = 5;

type Direction = 'ArrowLeft' | 'ArrowRight' | 'ArrowUp' | 'ArrowDown';

/**
 * One area of the plan on its own grid, cropped to where the tables are so
 * they stay large. Tiles are positioned in percentages, so the plan scales
 * with its container; below a minimum cell size it scrolls sideways.
 */
export function FloorMap({
    tables,
    fresh,
    selecting,
    selectedIds,
    isSelectable,
    onActivate,
    label,
}: {
    tables: FloorTable[];
    fresh: Set<number>;
    selecting: boolean;
    selectedIds: Set<number>;
    isSelectable: (table: FloorTable) => boolean;
    onActivate: (table: FloorTable) => void;
    label: string;
}) {
    const refs = useRef(new Map<number, HTMLButtonElement>());
    const [focusId, setFocusId] = useState<number | null>(null);

    const bounds = useMemo(() => {
        const minX = Math.min(...tables.map((t) => t.x));
        const minY = Math.min(...tables.map((t) => t.y));
        const maxX = Math.max(...tables.map((t) => t.x + t.width));
        const maxY = Math.max(...tables.map((t) => t.y + t.height));

        return {
            minX,
            minY,
            cols: Math.max(maxX - minX, MIN_COLS),
            rows: Math.max(maxY - minY, MIN_ROWS),
        };
    }, [tables]);

    const tabbable =
        tables.find((table) => table.id === focusId)?.id ?? tables[0]?.id;

    const move = (key: Direction) => {
        const current = tables.find((table) => table.id === focusId);

        if (!current) {
            return;
        }

        const cx = current.x + current.width / 2;
        const cy = current.y + current.height / 2;
        let best: { table: FloorTable; score: number } | null = null;

        for (const candidate of tables) {
            if (candidate.id === current.id) {
                continue;
            }

            const dx = candidate.x + candidate.width / 2 - cx;
            const dy = candidate.y + candidate.height / 2 - cy;
            const [along, across] =
                key === 'ArrowLeft'
                    ? [-dx, Math.abs(dy)]
                    : key === 'ArrowRight'
                      ? [dx, Math.abs(dy)]
                      : key === 'ArrowUp'
                        ? [-dy, Math.abs(dx)]
                        : [dy, Math.abs(dx)];

            if (along <= 0) {
                continue;
            }

            const score = along + across * 2;

            if (!best || score < best.score) {
                best = { table: candidate, score };
            }
        }

        if (best) {
            refs.current.get(best.table.id)?.focus();
        }
    };

    return (
        <div className="overflow-x-auto">
            <div
                role="group"
                aria-label={label}
                onKeyDown={(event) => {
                    if (
                        [
                            'ArrowLeft',
                            'ArrowRight',
                            'ArrowUp',
                            'ArrowDown',
                        ].includes(event.key)
                    ) {
                        event.preventDefault();
                        move(event.key as Direction);
                    }
                }}
                className="relative mx-auto w-full"
                style={{
                    aspectRatio: `${bounds.cols} / ${bounds.rows}`,
                    minWidth: `${bounds.cols * 3.25}rem`,
                    maxWidth: `${bounds.cols * 6}rem`,
                }}
            >
                {tables.map((table) => (
                    <div
                        key={table.id}
                        className="absolute p-0.5"
                        style={{
                            left: `${((table.x - bounds.minX) / bounds.cols) * 100}%`,
                            top: `${((table.y - bounds.minY) / bounds.rows) * 100}%`,
                            width: `${(table.width / bounds.cols) * 100}%`,
                            height: `${(table.height / bounds.rows) * 100}%`,
                        }}
                    >
                        <TableTile
                            ref={(node) => {
                                if (node) {
                                    refs.current.set(table.id, node);
                                } else {
                                    refs.current.delete(table.id);
                                }
                            }}
                            table={table}
                            fresh={fresh}
                            selecting={selecting}
                            selected={selectedIds.has(table.id)}
                            selectable={isSelectable(table)}
                            tabIndex={table.id === tabbable ? 0 : -1}
                            onFocus={() => setFocusId(table.id)}
                            onActivate={() => onActivate(table)}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
