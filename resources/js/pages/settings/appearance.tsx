import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { useT } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const t = useT();

    return (
        <>
            <Head title={t('settings_pages.appearance.head')} />

            <section aria-labelledby="appearance-heading" className="space-y-6">
                <Heading
                    id="appearance-heading"
                    variant="small"
                    title={t('settings_pages.appearance.title')}
                    description={t('settings_pages.appearance.description')}
                />
                <AppearanceTabs label={t('ui.theme.label')} />
            </section>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
