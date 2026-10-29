import type { AllergenCode } from '@/components/brand/allergen-icon';

export type Period = 'brunch' | 'lunch' | 'dinner';

export type StationOption = { value: string; label: string };

export type CategoryRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    service_periods: Period[];
    position: number;
    items_count: number;
};

export type ItemRow = {
    id: number;
    category_id: number;
    slug: string;
    name: string;
    price: number;
    has_variants: boolean;
    kcal: number;
    is_vegetarian: boolean;
    is_vegan: boolean;
    allergen_count: number;
    station: string;
    is_available: boolean;
    is_special: boolean;
    image: string;
    photo_url: string | null;
};

export type Filters = {
    q: string;
    category: number | null;
    availability: 'available' | 'sold_out' | null;
    diet: 'vegetarian' | 'vegan' | null;
    station: string | null;
};

export type MenuIndexProps = {
    filters: Filters;
    categories: CategoryRow[];
    items: ItemRow[];
    total: number;
    stations: StationOption[];
    periods: Period[];
};

export type VariantData = {
    id: number | null;
    name: string;
    price: number;
    kcal: number | null;
};

export type ModifierData = {
    id: number | null;
    name: string;
    price: number;
    allergens: AllergenCode[];
};

export type GroupData = {
    id: number | null;
    name: string;
    min: number;
    max: number;
    modifiers: ModifierData[];
};

export type EditorItem = {
    id: number;
    name: string;
    slug: string;
    description: string;
    category_id: number;
    price: number;
    vat_rate: number;
    kcal: number;
    spice_level: number;
    is_vegetarian: boolean;
    is_vegan: boolean;
    allergens: AllergenCode[];
    station: string;
    prep_minutes: number;
    is_hot: boolean;
    is_alcoholic: boolean;
    is_special: boolean;
    is_available: boolean;
    service_periods: Period[];
    image: string;
    photo_url: string | null;
    variants: VariantData[];
    modifier_groups: GroupData[];
};

export type HistoryRow = {
    id: number;
    variant: string | null;
    old: number;
    new: number;
    by: string | null;
    at: string;
};

export type EditProps = {
    item: EditorItem | null;
    history: HistoryRow[];
    categories: { id: number; name: string }[];
    stations: StationOption[];
    periods: Period[];
    default_category: number | null;
    default_vat: number;
    can_change_vat: boolean;
};
