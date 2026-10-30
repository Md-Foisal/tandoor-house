import type { OrderChannel, OrderStatus } from '@/types/staff';

export type TodayView = 'management' | 'front' | 'kitchen' | 'rider';

export type ShiftInfo = {
    shift: {
        starts_at: string;
        ends_at: string;
        role: string;
        notes: string | null;
    } | null;
    clocked_in_at: string | null;
    last_clocked_out_at: string | null;
};

export type OrderRow = {
    id: number;
    number: string;
    channel: OrderChannel;
    status: OrderStatus;
    table: string | null;
    name: string | null;
    total: number;
    since: string;
};

export type RequestRow = {
    id: number;
    table: string;
    type: 'call_waiter' | 'bill';
    since: string | null;
};

export type BookingRow = {
    id: number;
    name: string;
    party_size: number;
    starts_at: string;
    table: string | null;
    deposit_pending: boolean;
};

export type StockRow = {
    id: number;
    name: string;
    stock: number;
    unit: string;
    low_threshold: number;
};

export type DepositRow = {
    id: number;
    reference: string;
    name: string;
    party_size: number;
    starts_at: string;
    deposit: number;
};

export type HourPoint = {
    hour: number;
    today: number | null;
    last_week: number;
};

export type ManagementBlocks = {
    tiles: {
        sales: number;
        orders_by_channel: Record<OrderChannel, number>;
        covers_tonight: number;
        average_prep_minutes: number | null;
        late_tickets: number;
        low_stock: number;
        staff_on_shift: number;
    };
    hourly: HourPoint[];
    attention: {
        slow_orders: OrderRow[];
        table_requests: RequestRow[];
        unpaid_deposits: DepositRow[];
        low_stock: StockRow[];
        unpublished_reviews: number;
    };
};

export type FrontBlocks = {
    waiting: OrderRow[];
    ready: OrderRow[];
    table_requests: RequestRow[];
    bookings: BookingRow[];
};

export type KitchenBlocks = {
    in_kitchen: number;
    late: number;
    late_tickets: OrderRow[];
    low_stock: StockRow[];
    low_stock_count: number;
};

export type DeliveryRow = {
    id: number;
    number: string;
    name: string | null;
    postcode: string | null;
    total: number;
    cash_due: number;
    since: string;
};

export type RiderBlocks = {
    out_now: DeliveryRow[];
    to_collect: DeliveryRow[];
    cash_collected: number;
    delivered_today: number;
};

export const clock = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Europe/London',
});

export function clockTime(iso: string): string {
    return clock.format(new Date(iso));
}
