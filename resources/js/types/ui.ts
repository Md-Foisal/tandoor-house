import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};

export type RestaurantInfo = {
    name: string;
    phone: string;
    email: string;
    address: { line1: string; city: string; postcode: string };
    company: { legal_name: string; vat_number: string; company_number: string };
};

export type OpeningDay = {
    day: string;
    isToday: boolean;
    periods: string[];
};

export type FooterData = {
    hours: OpeningDay[];
    /** 0–5, or a string such as "Awaiting inspection" before a first visit. */
    hygieneRating: number | string;
};

export type TranslationTree = {
    [key: string]: string | TranslationTree;
};
