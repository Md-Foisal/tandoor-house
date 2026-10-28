import type { OrderChannel } from '@/types';

export type KitchenLine = {
    id: number;
    quantity: number;
    name: string;
    variant: string | null;
    modifiers: string[];
    notes: string | null;
    allergy: boolean;
    station: string;
    done: boolean;
};

export type KitchenTicket = {
    id: number;
    number: string;
    status: 'accepted' | 'cooking';
    channel: OrderChannel;
    table: string | null;
    pickup_time: string | null;
    accepted_at: string | null;
    warn_after: number;
    late_after: number;
    notes: string | null;
    lines: KitchenLine[];
};

export type StationTab = { value: string; label: string; count: number };

export type AllDayRow = {
    menu_item_id: number | null;
    name: string;
    quantity: number;
    available: boolean;
};

export type RecentLine = {
    id: number;
    number: string;
    name: string;
    quantity: number;
    bumped_at: string | null;
};
