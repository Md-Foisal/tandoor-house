import { Head } from '@inertiajs/react';
import { StaffPageHeader } from '@/components/staff/page-header';
import { useT } from '@/lib/i18n';

export default function Today() {
    const t = useT();

    return (
        <>
            <Head title={t('staff.areas.today')} />
            <StaffPageHeader title={t('staff.areas.today')} />
        </>
    );
}
