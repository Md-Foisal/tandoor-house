import type { OrderStatus } from '@/types';

export type TableStatus =
    | 'free'
    | 'reserved'
    | 'seated'
    | 'bill_requested'
    | 'needs_cleaning';

export type FloorRequest = {
    id: number;
    type: 'call_waiter' | 'bill';
    created_at: string | null;
};

export type FloorOrder = {
    id: number;
    number: string;
    status: OrderStatus;
    total: number;
    placed_at: string;
};

export type FloorTable = {
    id: number;
    label: string;
    seats: number;
    group_seats: number;
    area: string;
    x: number;
    y: number;
    width: number;
    height: number;
    shape: 'square' | 'round' | 'rect';
    status: TableStatus;
    qr_token: string;
    merged_into_id: number | null;
    merged_into_label: string | null;
    merged_labels: string[];
    party: {
        kind: 'booking' | 'walk_in';
        name: string | null;
        size: number | null;
        reference: string | null;
    } | null;
    booking: {
        id: number;
        name: string;
        party_size: number;
        starts_at: string;
    } | null;
    seated_at: string | null;
    open_total: number;
    open_orders: number;
    requests: FloorRequest[];
    orders: FloorOrder[];
};

export type FloorBooking = {
    id: number;
    reference: string;
    name: string;
    party_size: number;
    starts_at: string;
    table_id: number | null;
    table_label: string | null;
    occasion: string | null;
    deposit_pending: boolean;
};

export type FloorProps = {
    tables?: FloorTable[];
    upcoming?: FloorBooking[];
};
