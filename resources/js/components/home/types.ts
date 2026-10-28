import type { MenuItem } from '@/types';

export type RecentOrder = {
    number: string;
    placed_at: string;
    total: number;
    lines: { name: string; quantity: number }[];
};

export type HomeReview = {
    id: number;
    author_name: string;
    author_area: string | null;
    rating: number;
    body: string;
};

export type HomeNumbers = {
    years: number;
    rating: number | null;
    reviews: number;
    dishes: number;
};

export type WeekDay = {
    day: string;
    isToday: boolean;
    periods: string[];
};

export type HomeProps = {
    specials: MenuItem[];
    recentOrders: RecentOrder[] | null;
    hours: WeekDay[];
    directionsUrl: string;
    reviews?: HomeReview[];
    numbers?: HomeNumbers;
};
