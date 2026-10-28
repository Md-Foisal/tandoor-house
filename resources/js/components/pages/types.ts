import type { AllergenCode } from '@/components/brand/allergen-icon';

export type StoryNumbers = {
    years: number;
    opened: number;
    dishes: number;
    daal_hours: number;
};

export type WeekDay = {
    day: string;
    isToday: boolean;
    periods: string[];
};

export type MapLinks = {
    google: string;
    apple: string;
    address: string;
};

export type MatrixOption = {
    name: string;
    allergens: AllergenCode[];
};

export type MatrixDish = {
    slug: string;
    name: string;
    allergens: AllergenCode[];
    options: MatrixOption[];
};

export type MatrixGroup = {
    id: number;
    name: string;
    dishes: MatrixDish[];
};

export type StoryProps = { numbers: StoryNumbers };

export type VisitProps = {
    hours: WeekDay[];
    bankHolidays: string[];
    maps: MapLinks;
    maxParty: number;
};

export type AllergensProps = { groups: MatrixGroup[] };
