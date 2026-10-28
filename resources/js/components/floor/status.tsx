import {
    BellRing,
    CalendarClock,
    CircleCheck,
    Receipt,
    SprayCan,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { FloorTable, TableStatus } from './types';

type Meta = {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    tile: string;
    text: string;
};

/** Colour, icon and text always travel together. */
export const statusMeta: Record<TableStatus, Meta> = {
    free: {
        icon: CircleCheck,
        tile: 'border-leaf/60 bg-leaf-soft',
        text: 'text-leaf',
    },
    reserved: {
        icon: CalendarClock,
        tile: 'border-line-strong bg-clay',
        text: 'text-ink',
    },
    seated: {
        icon: Users,
        tile: 'border-ember/60 bg-ember-soft',
        text: 'text-ember',
    },
    bill_requested: {
        icon: Receipt,
        tile: 'border-turmeric bg-turmeric-soft',
        text: 'text-turmeric',
    },
    needs_cleaning: {
        icon: SprayCan,
        tile: 'border-chilli/60 bg-chilli-soft',
        text: 'text-chilli',
    },
};

export const statusOrder: TableStatus[] = [
    'free',
    'reserved',
    'seated',
    'bill_requested',
    'needs_cleaning',
];

export function StatusLabel({
    status,
    className,
}: {
    status: TableStatus;
    className?: string;
}) {
    const t = useT();
    const { icon: Icon, text } = statusMeta[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 font-semibold',
                text,
                className,
            )}
        >
            <Icon className="size-4 shrink-0" aria-hidden />
            {t(`staff_floor.status.${status}`)}
        </span>
    );
}

export function RequestBadge({
    type,
    fresh,
}: {
    type: 'call_waiter' | 'bill';
    fresh?: boolean;
}) {
    const t = useT();
    const Icon = type === 'bill' ? Receipt : BellRing;

    return (
        <span
            data-fresh={fresh ? 'true' : undefined}
            className="inline-flex items-center gap-1 rounded-sm bg-ink px-1.5 py-0.5 text-xs font-semibold text-paper"
        >
            <Icon className="size-3.5" aria-hidden />
            {t(`staff_floor.request.${type}`)}
        </span>
    );
}

export function isOccupied(table: FloorTable): boolean {
    return table.status === 'seated' || table.status === 'bill_requested';
}
