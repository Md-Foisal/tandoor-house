import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ItemForm } from '@/components/staff-menu/item-form';
import type { EditProps } from '@/components/staff-menu/types';
import { StaffPageHeader } from '@/components/staff/page-header';
import { useT } from '@/lib/i18n';

export default function MenuItemEditor(props: EditProps) {
    const t = useT();
    const [revision, setRevision] = useState(0);
    const title = props.item
        ? t('staff_menu.editor.edit_title', { name: props.item.name })
        : t('staff_menu.editor.new_title');

    return (
        <>
            <Head title={title} />
            <StaffPageHeader title={title} />
            <ItemForm
                key={`${props.item?.id ?? 'new'}-${revision}`}
                props={props}
                onSaved={() => setRevision((r) => r + 1)}
            />
        </>
    );
}
