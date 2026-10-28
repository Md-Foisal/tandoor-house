import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useT } from '@/lib/i18n';

type Method = 'post' | 'delete';

/**
 * Every floor action posts, then refreshes only the plan. Server rule
 * messages come back as validation errors and are shown as a toast.
 */
export function useFloorActions() {
    const t = useT();
    const [busy, setBusy] = useState(false);

    const run = (
        method: Method,
        url: string,
        data: Record<string, unknown> = {},
        onDone?: () => void,
    ) => {
        router[method](url, data as never, {
            preserveScroll: true,
            preserveState: true,
            only: ['tables', 'upcoming'],
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: () => onDone?.(),
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ?? t('staff_floor.toast.failed'),
                ),
        });
    };

    return {
        busy,
        setStatus: (
            tableId: number,
            status: string,
            partySize?: number,
            onDone?: () => void,
        ) =>
            run(
                'post',
                `/staff/floor/tables/${tableId}/status`,
                { status, party_size: partySize },
                onDone,
            ),
        transfer: (tableId: number, to: number, onDone?: () => void) =>
            run(
                'post',
                `/staff/floor/tables/${tableId}/transfer`,
                { to },
                onDone,
            ),
        merge: (tables: number[], onDone?: () => void) =>
            run('post', '/staff/floor/merge', { tables }, onDone),
        unmerge: (tableId: number, onDone?: () => void) =>
            run('delete', `/staff/floor/tables/${tableId}/merge`, {}, onDone),
        handle: (requestId: number) =>
            run('post', `/staff/floor/requests/${requestId}/handle`),
        seatBooking: (bookingId: number, tableId: number | null) =>
            run(
                'post',
                `/staff/floor/reservations/${bookingId}/seat`,
                tableId ? { table_id: tableId } : {},
            ),
    };
}

export type FloorActions = ReturnType<typeof useFloorActions>;
