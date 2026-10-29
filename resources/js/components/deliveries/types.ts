export type DeliveryTab = 'ready' | 'road' | 'done';

export type DeliveryCardData = {
    number: string;
    status: 'ready' | 'out_for_delivery' | 'delivered' | 'completed';
    first_name: string;
    phone: string | null;
    address: string;
    postcode: string | null;
    instructions: string | null;
    items: number;
    bags: number;
    total: number;
    cash_due: boolean;
    cash_collected: number | null;
    age_check: boolean;
    ready_at: string | null;
    rider: { id: number; name: string } | null;
    last_failure: string | null;
};

export type Rider = { id: number; name: string };

export type CashSummary = {
    total: number;
    by_rider: { name: string; total: number }[];
};

export function mapsUrl(
    kind: 'google' | 'apple',
    card: DeliveryCardData,
): string {
    const destination = [card.address, card.postcode]
        .filter(Boolean)
        .join(', ');

    return kind === 'google'
        ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destination)}`
        : `https://maps.apple.com/?daddr=${encodeURIComponent(destination)}`;
}
