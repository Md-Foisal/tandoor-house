import type { AllergenCode } from '@/components/brand/allergen-icon';
import { toPence, toPounds } from './pounds';
import type { EditorItem, Period } from './types';

export type VariantForm = {
    key: string;
    id: number | null;
    name: string;
    price: string;
    kcal: string;
};
export type ModifierForm = {
    key: string;
    id: number | null;
    name: string;
    price: string;
    allergens: AllergenCode[];
};
export type GroupForm = {
    key: string;
    id: number | null;
    name: string;
    min: number;
    max: number;
    modifiers: ModifierForm[];
};

export type ItemFormData = {
    name: string;
    slug: string;
    description: string;
    category_id: string;
    price: string;
    vat_rate: number;
    kcal: string;
    spice_level: number;
    is_vegetarian: boolean;
    is_vegan: boolean;
    allergens: AllergenCode[];
    allergens_checked: boolean;
    station: string;
    prep_minutes: string;
    is_hot: boolean;
    is_alcoholic: boolean;
    is_special: boolean;
    is_available: boolean;
    service_periods: Period[];
    photo: File | null;
    remove_photo: boolean;
    variants: VariantForm[];
    modifier_groups: GroupForm[];
};

let counter = 0;
export const newKey = () => `k${++counter}`;

export const blankVariant = (): VariantForm => ({
    key: newKey(),
    id: null,
    name: '',
    price: '',
    kcal: '',
});
export const blankModifier = (): ModifierForm => ({
    key: newKey(),
    id: null,
    name: '',
    price: '0.00',
    allergens: [],
});
export const blankGroup = (): GroupForm => ({
    key: newKey(),
    id: null,
    name: '',
    min: 0,
    max: 1,
    modifiers: [blankModifier()],
});

export function initialData(
    item: EditorItem | null,
    defaults: { vat: number; category: number | null; firstStation: string },
): ItemFormData {
    return {
        name: item?.name ?? '',
        slug: item?.slug ?? '',
        description: item?.description ?? '',
        category_id: String(item?.category_id ?? defaults.category ?? ''),
        price: item ? toPounds(item.price) : '',
        vat_rate: item?.vat_rate ?? defaults.vat,
        kcal: String(item?.kcal ?? ''),
        spice_level: item?.spice_level ?? 0,
        is_vegetarian: item?.is_vegetarian ?? false,
        is_vegan: item?.is_vegan ?? false,
        allergens: item?.allergens ?? [],
        allergens_checked: false,
        station: item?.station ?? defaults.firstStation,
        prep_minutes: String(item?.prep_minutes ?? 12),
        is_hot: item?.is_hot ?? true,
        is_alcoholic: item?.is_alcoholic ?? false,
        is_special: item?.is_special ?? false,
        is_available: item?.is_available ?? true,
        service_periods: item?.service_periods ?? [],
        photo: null,
        remove_photo: false,
        variants: (item?.variants ?? []).map((v) => ({
            key: newKey(),
            id: v.id,
            name: v.name,
            price: toPounds(v.price),
            kcal: v.kcal === null ? '' : String(v.kcal),
        })),
        modifier_groups: (item?.modifier_groups ?? []).map((g) => ({
            key: newKey(),
            id: g.id,
            name: g.name,
            min: g.min,
            max: g.max,
            modifiers: g.modifiers.map((m) => ({
                key: newKey(),
                id: m.id,
                name: m.name,
                price: toPounds(m.price),
                allergens: m.allergens,
            })),
        })),
    };
}

const num = (value: string): number | string =>
    value.trim() === '' ? '' : Number(value);

/** What the server receives: money in pence, numbers as numbers, no UI keys. */
export function toPayload(data: ItemFormData) {
    return {
        name: data.name,
        slug: data.slug === '' ? null : data.slug,
        description: data.description,
        category_id: data.category_id,
        price: toPence(data.price) ?? data.price,
        vat_rate: data.vat_rate,
        kcal: num(data.kcal),
        spice_level: data.spice_level,
        is_vegetarian: data.is_vegan || data.is_vegetarian,
        is_vegan: data.is_vegan,
        allergens: data.allergens,
        allergens_checked: data.allergens_checked,
        station: data.station,
        prep_minutes: num(data.prep_minutes),
        is_hot: data.is_hot,
        is_alcoholic: data.is_alcoholic,
        is_special: data.is_special,
        is_available: data.is_available,
        service_periods: data.service_periods,
        photo: data.photo,
        remove_photo: data.remove_photo,
        variants: data.variants.map((v) => ({
            id: v.id,
            name: v.name,
            price: toPence(v.price) ?? v.price,
            kcal: v.kcal === '' ? null : Number(v.kcal),
        })),
        modifier_groups: data.modifier_groups.map((g) => ({
            id: g.id,
            name: g.name,
            min: g.min,
            max: g.max,
            modifiers: g.modifiers.map((m) => ({
                id: m.id,
                name: m.name,
                price: toPence(m.price) ?? m.price,
                allergens: m.allergens,
            })),
        })),
    };
}
