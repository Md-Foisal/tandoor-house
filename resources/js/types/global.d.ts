import type { Auth } from '@/types/auth';
import type { OpenStatusData } from '@/components/brand/open-status';
import type { StaffArea } from '@/types/staff';
import type { FooterData, RestaurantInfo, TranslationTree } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            restaurant: RestaurantInfo;
            translations: TranslationTree;
            footer: FooterData;
            basketCount: number;
            openStatus: OpenStatusData;
            staffAreas: StaffArea[];
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
