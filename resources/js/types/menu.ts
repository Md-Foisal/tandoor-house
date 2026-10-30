import type { AllergenCode } from '@/components/brand/allergen-icon';
import type { MenuImage } from '@/lib/media';

export type Variant = {
    id: number;
    name: string;
    price: number;
    kcal: number | null;
};

export type Modifier = {
    id: number;
    name: string;
    price: number;
    allergens: AllergenCode[];
};

export type ModifierGroup = {
    id: number;
    name: string;
    min: number;
    max: number;
    modifiers: Modifier[];
};

export type MenuItem = {
    id: number;
    category_id: number;
    slug: string;
    name: string;
    description: string;
    /** Lowest price in pence: the cheapest variant when there are variants. */
    price: number;
    kcal: number;
    spice_level: number;
    is_vegetarian: boolean;
    is_vegan: boolean;
    is_available: boolean;
    is_special: boolean;
    is_hot: boolean;
    is_alcoholic: boolean;
    allergens: AllergenCode[];
    /** Allergens that only some options add, e.g. milk in the chai. */
    option_allergens?: AllergenCode[];
    /** Media manifest key, or the converted upload when staff added a photo. */
    image: MenuImage;
    has_options: boolean;
    variants?: Variant[];
    modifier_groups?: ModifierGroup[];
};

export type Category = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    items: MenuItem[];
};
