import type { AllergenCode } from '@/components/brand/allergen-icon';
import { allergenCodes } from '@/components/brand/allergen-icon';
import type { Category, MenuItem } from '@/types';

export const MAX_SPICE = 3;

export type MenuFilters = {
    query: string;
    vegetarian: boolean;
    vegan: boolean;
    /** Highest spice level wanted; null means any. */
    spice: number | null;
    exclude: AllergenCode[];
    /** Whole pounds; null means no limit. */
    minPrice: number | null;
    maxPrice: number | null;
};

export const emptyFilters: MenuFilters = {
    query: '',
    vegetarian: false,
    vegan: false,
    spice: null,
    exclude: [],
    minPrice: null,
    maxPrice: null,
};

export const MENU_QUERY_STORAGE_KEY = 'th.menu.query';

function wholeNumber(value: string | null): number | null {
    if (value === null || value.trim() === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) && parsed >= 0 ? Math.floor(parsed) : null;
}

export function parseFilters(search: string): MenuFilters {
    const params = new URLSearchParams(search);
    const spice = wholeNumber(params.get('spice'));
    const exclude = (params.get('ex') ?? '')
        .split(',')
        .filter((code): code is AllergenCode =>
            (allergenCodes as readonly string[]).includes(code),
        );

    return {
        query: params.get('q') ?? '',
        vegetarian: params.get('veg') === '1',
        vegan: params.get('vegan') === '1',
        spice: spice !== null && spice < MAX_SPICE ? spice : null,
        exclude: [...new Set(exclude)],
        minPrice: wholeNumber(params.get('min')),
        maxPrice: wholeNumber(params.get('max')),
    };
}

export function serialiseFilters(filters: MenuFilters): string {
    const params = new URLSearchParams();

    if (filters.query.trim() !== '') {
        params.set('q', filters.query.trim());
    }

    if (filters.vegetarian) {
        params.set('veg', '1');
    }

    if (filters.vegan) {
        params.set('vegan', '1');
    }

    if (filters.spice !== null) {
        params.set('spice', String(filters.spice));
    }

    if (filters.exclude.length > 0) {
        params.set('ex', filters.exclude.join(','));
    }

    if (filters.minPrice !== null) {
        params.set('min', String(filters.minPrice));
    }

    if (filters.maxPrice !== null) {
        params.set('max', String(filters.maxPrice));
    }

    const text = params.toString();

    return text === '' ? '' : `?${text}`;
}

/** Filters that narrow the menu, not counting the search box. */
export function activeFilterCount(filters: MenuFilters): number {
    return (
        Number(filters.vegetarian) +
        Number(filters.vegan) +
        Number(filters.spice !== null) +
        filters.exclude.length +
        Number(filters.minPrice !== null) +
        Number(filters.maxPrice !== null)
    );
}

export function hasAnyFilter(filters: MenuFilters): boolean {
    return activeFilterCount(filters) > 0 || filters.query.trim() !== '';
}

export function matchesItem(item: MenuItem, filters: MenuFilters): boolean {
    const query = filters.query.trim().toLowerCase();

    if (
        query !== '' &&
        !item.name.toLowerCase().includes(query) &&
        !item.description.toLowerCase().includes(query)
    ) {
        return false;
    }

    if (filters.vegan && !item.is_vegan) {
        return false;
    }

    if (filters.vegetarian && !item.is_vegetarian) {
        return false;
    }

    if (filters.spice !== null && item.spice_level > filters.spice) {
        return false;
    }

    if (item.allergens.some((code) => filters.exclude.includes(code))) {
        return false;
    }

    if (filters.minPrice !== null && item.price < filters.minPrice * 100) {
        return false;
    }

    if (filters.maxPrice !== null && item.price > filters.maxPrice * 100) {
        return false;
    }

    return true;
}

export function filterCategories(
    categories: Category[],
    filters: MenuFilters,
): Category[] {
    return categories
        .map((category) => ({
            ...category,
            items: category.items.filter((item) => matchesItem(item, filters)),
        }))
        .filter((category) => category.items.length > 0);
}
