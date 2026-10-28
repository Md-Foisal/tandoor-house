export type WaitingOrder = {
    number: string;
    placed_at: string;
    date_label: string;
    items: string[];
};

export type MyReview = {
    id: number;
    rating: number;
    body: string;
    author_name: string;
    author_area: string | null;
    reply: string | null;
    is_published: boolean;
    order_number: string | null;
    date_label: string | null;
};

export type ReviewsProps = {
    waiting?: WaitingOrder[];
    reviews?: MyReview[];
};
