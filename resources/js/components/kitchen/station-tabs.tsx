import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { StationTab } from './types';

export function StationTabs({
    stations,
    active,
    onSelect,
}: {
    stations: StationTab[];
    active: string;
    onSelect: (station: string) => void;
}) {
    const t = useT();

    return (
        <div
            role="tablist"
            aria-label={t('staff_kitchen.stations.label')}
            className="flex min-w-0 gap-1 overflow-x-auto"
        >
            {stations.map((station) => {
                const selected = station.value === active;

                return (
                    <button
                        key={station.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => onSelect(station.value)}
                        className={cn(
                            'flex h-12 shrink-0 items-center gap-2 rounded-md px-4 text-[0.9375rem] font-semibold outline-none focus-visible:outline-2 focus-visible:outline-ember',
                            selected
                                ? 'bg-ember text-ember-ink'
                                : 'text-ink-muted hover:bg-clay hover:text-ink',
                        )}
                    >
                        {station.label}
                        <span
                            className={cn(
                                'grid min-w-6 place-items-center rounded-full px-1.5 text-sm tabular-nums',
                                selected
                                    ? 'bg-ember-ink/20'
                                    : 'bg-clay text-ink',
                            )}
                        >
                            {station.count}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
