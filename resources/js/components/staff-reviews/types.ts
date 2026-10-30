import type { OrderChannel } from '@/types';

export type ReviewTab = 'waiting' | 'published' | 'hidden';

export type HideReason = 'abusive' | 'personal_data' | 'not_about_us' | 'other';

export type ReviewRow = {
    id: number;
    rating: number;
    body: string;
    author_name: string;
    author_area: string | null;
    created_at: string | null;
    reply: string | null;
    is_published: boolean;
    hidden: {
        reason: HideReason | null;
        note: string | null;
        by: string | null;
        at: string | null;
    } | null;
    order: {
        number: string;
        channel: OrderChannel;
        placed_at: string;
        items: string[];
    } | null;
};

export type ReviewPage = {
    data: ReviewRow[];
    page: number;
    last_page: number;
    total: number;
};

export type ReviewSummary = {
    average: number | null;
    previous_average: number | null;
    change: number | null;
    count: number;
    previous_count: number;
    stars: Record<string, number>;
    themes: { word: string; count: number }[];
};

export type Counts = Record<ReviewTab, number>;
