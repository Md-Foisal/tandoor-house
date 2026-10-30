import type { AllergenCode } from '@/components/brand/allergen-icon';
import type { MenuImage } from '@/lib/media';
import type { MenuItem } from '@/types';

export type BasketChannel = 'delivery' | 'pickup' | 'dine_in';

export type BasketLineData = {
    key: string;
    menu_item_id: number;
    item_variant_id: number | null;
    modifier_ids: number[];
    slug: string;
    name: string;
    variant: string | null;
    modifiers: { name: string; price: number }[];
    notes: string | null;
    quantity: number;
    unit_price: number;
    total: number;
    allergens: AllergenCode[];
    image: MenuImage;
    is_available: boolean;
    is_alcoholic: boolean;
};

export type BasketSummary = {
    lines: BasketLineData[];
    count: number;
    channel: BasketChannel;
    promo_code: string | null;
    promo_invalid: boolean;
    has_unavailable: boolean;
    has_alcohol: boolean;
    subtotal: number;
    discount: number;
    delivery_fee: number;
    service_charge: number;
    vat_total: number;
    total: number;
};

export type BasketPageProps = {
    basket: BasketSummary;
    channels: { value: BasketChannel; available: boolean }[];
    table: string | null;
    service_rate: number;
    delivery: { from_fee: number | null; minimum_order: number | null };
    specials?: MenuItem[];
};
