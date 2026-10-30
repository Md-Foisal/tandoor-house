import type { OrderChannel, OrderStatus } from '@/types';

export type PaymentMethod =
    | 'card'
    | 'apple_pay'
    | 'google_pay'
    | 'pay_at_venue'
    | 'cash';
export type PaymentStatus =
    | 'pending'
    | 'authorised'
    | 'paid'
    | 'failed'
    | 'partially_refunded'
    | 'refunded';

export type CardItem = {
    quantity: number;
    name: string;
    variant: string | null;
    modifiers: string[];
    notes: string | null;
};

export type OrderCardData = {
    number: string;
    channel: OrderChannel;
    status: OrderStatus;
    table: string | null;
    postcode: string | null;
    pickup_at: string | null;
    placed_at: string;
    first_name: string;
    items: CardItem[];
    notes: string | null;
    has_allergy: boolean;
    age_check: boolean;
    total: number;
    payment_method: PaymentMethod;
    payment_status: PaymentStatus;
    rider: string | null;
    next: OrderStatus | null;
    can_reject: boolean;
    can_cancel: boolean;
};

export type OrderLineData = {
    id: number;
    name: string;
    variant: string | null;
    modifiers: { name: string; price: number }[];
    notes: string | null;
    quantity: number;
    unit_price: number;
    total: number;
};

export type OrderEventData = {
    from: OrderStatus | null;
    to: OrderStatus;
    by: string | null;
    at: string;
};

export type OrderPaymentData = {
    id: number;
    method: PaymentMethod;
    status: PaymentStatus;
    amount: number;
    refunded: number;
    reference: string | null;
    at: string | null;
};

export type OrderTotals = {
    subtotal: number;
    discount: number;
    delivery_fee: number;
    service_charge: number;
    tip: number;
    vat_total: number;
    total: number;
};

export type OrderDetailData = OrderCardData & {
    contact: { name: string; email: string; phone: string };
    address: {
        line1?: string;
        line2?: string;
        city?: string;
        postcode?: string;
    } | null;
    instructions: string | null;
    maps_url: string | null;
    rider_id: number | null;
    cash_collected: number | null;
    rejection_reason: string | null;
    lines: OrderLineData[];
    totals: OrderTotals;
    events: OrderEventData[];
    payments: OrderPaymentData[];
    paid: number;
    refunded: number;
};

export type OrderAbilities = {
    edit: boolean;
    refund: boolean;
    refund_limit: number | null;
    assign_rider: boolean;
};

export type BoardColumnKey = 'new' | 'kitchen' | 'ready' | 'out' | 'done';

export const boardColumns: { key: BoardColumnKey; statuses: OrderStatus[] }[] =
    [
        { key: 'new', statuses: ['placed'] },
        { key: 'kitchen', statuses: ['accepted', 'cooking'] },
        { key: 'ready', statuses: ['ready'] },
        {
            key: 'out',
            statuses: ['out_for_delivery', 'served', 'collected', 'delivered'],
        },
        { key: 'done', statuses: ['completed', 'rejected', 'cancelled'] },
    ];

export function columnFor(status: OrderStatus): BoardColumnKey {
    return (
        boardColumns.find((column) => column.statuses.includes(status))?.key ??
        'done'
    );
}

export const isLive = (status: OrderStatus): boolean =>
    !['completed', 'rejected', 'cancelled'].includes(status);
