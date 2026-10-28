import type { AllergenCode } from '@/components/brand/allergen-icon';
import type { Category } from '@/types';

export type TableInfo = { token: string; label: string; area: string };

export type TableBasketLine = {
    key: string;
    name: string;
    variant: string | null;
    modifiers: { name: string; price: number }[];
    notes: string | null;
    quantity: number;
    unit_price: number;
    total: number;
    allergens: AllergenCode[];
    is_available: boolean;
};

export type TableBasket = {
    lines: TableBasketLine[];
    count: number;
    subtotal: number;
    service_charge: number;
    total: number;
    has_unavailable: boolean;
};

export type SentOrder = {
    number: string;
    status: string;
    placed_label: string;
    item_count: number;
    total: number;
    summary: string;
};

export type OpenRequests = { call_waiter: string | null; bill: string | null };

export type TableShowProps = {
    table: TableInfo;
    categories?: Category[];
    basket: TableBasket;
    service_rate: number;
    orders: SentOrder[];
    requests: OpenRequests;
};

export type BillOrder = {
    number: string;
    status: string;
    placed_label: string;
    item_count: number;
    total: number;
    lines: {
        id: number;
        name: string;
        detail: string | null;
        quantity: number;
        line_total: number;
    }[];
};

export type BillUnit = {
    key: string;
    name: string;
    detail: string | null;
    price: number;
    number: number;
    count: number;
};

export type BillProps = {
    table: TableInfo;
    orders: BillOrder[];
    units: BillUnit[];
    totals: {
        subtotal: number;
        service_charge: number;
        service_included: boolean;
        vat_total: number;
        total: number;
    };
    service_rate: number;
    options: {
        service: boolean;
        mode: 'none' | 'even' | 'items';
        people: number;
        guests: number;
        claims: Record<string, number>;
    };
    split: { shares: number[]; unclaimed: number } | null;
    requests: OpenRequests;
};
