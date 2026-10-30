import { Deferred } from '@inertiajs/react';
import { Mail, Pencil, Phone, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import {
    InventoryShell,
    RowsSkeleton,
} from '@/components/staff-inventory/shell';
import { SupplierDialog } from '@/components/staff-inventory/supplier-dialog';
import type {
    Abilities,
    SupplierRow,
} from '@/components/staff-inventory/types';
import { Button } from '@/components/ui/button';
import { choice, useT } from '@/lib/i18n';

export default function Suppliers({
    suppliers,
    abilities,
    lowCount,
}: {
    suppliers?: SupplierRow[];
    abilities: Abilities;
    lowCount: number;
}) {
    const t = useT();
    const [editing, setEditing] = useState<SupplierRow | null>(null);
    const [creating, setCreating] = useState(false);

    return (
        <InventoryShell
            active="suppliers"
            lowCount={lowCount}
            description={t('staff_inventory.supplier.description')}
            actions={
                abilities.manage && (
                    <Button size="lg" onClick={() => setCreating(true)}>
                        <Plus /> {t('staff_inventory.supplier.add')}
                    </Button>
                )
            }
        >
            <Deferred data="suppliers" fallback={<RowsSkeleton rows={6} />}>
                {suppliers && suppliers.length === 0 ? (
                    <div className="rounded-lg border border-line bg-surface">
                        <EmptyState
                            title={t('staff_inventory.supplier.empty_title')}
                            description={t('staff_inventory.supplier.empty')}
                        />
                    </div>
                ) : (
                    <ul className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
                        {(suppliers ?? []).map((supplier) => (
                            <li
                                key={supplier.id}
                                className="grid gap-3 px-4 py-4 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_10rem_auto] md:items-center md:gap-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {supplier.name}
                                    </p>
                                    <p className="text-sm text-ink-muted">
                                        {supplier.contact_name ??
                                            t(
                                                'staff_inventory.supplier.no_contact',
                                            )}
                                    </p>
                                </div>
                                <div className="grid gap-1 text-sm">
                                    {supplier.email && (
                                        <a
                                            href={`mailto:${supplier.email}`}
                                            className="inline-flex min-h-8 items-center gap-2 break-all hover:underline"
                                        >
                                            <Mail
                                                className="size-4 shrink-0 text-ink-muted"
                                                aria-hidden="true"
                                            />
                                            {supplier.email}
                                        </a>
                                    )}
                                    {supplier.phone && (
                                        <a
                                            href={`tel:${supplier.phone.replaceAll(' ', '')}`}
                                            className="inline-flex min-h-8 items-center gap-2 hover:underline"
                                        >
                                            <Phone
                                                className="size-4 shrink-0 text-ink-muted"
                                                aria-hidden="true"
                                            />
                                            {supplier.phone}
                                        </a>
                                    )}
                                </div>
                                <p className="text-sm text-ink-muted">
                                    {choice(
                                        t('staff_inventory.supplier.lead', {
                                            count: supplier.lead_days,
                                        }),
                                        supplier.lead_days,
                                    )}
                                    <br />
                                    {t('staff_inventory.supplier.counts', {
                                        ingredients: supplier.ingredients,
                                        orders: supplier.open_orders,
                                    })}
                                </p>
                                {abilities.manage && (
                                    <Button
                                        variant="outline"
                                        className="justify-self-start md:justify-self-end"
                                        onClick={() => setEditing(supplier)}
                                    >
                                        <Pencil />{' '}
                                        {t('staff_inventory.supplier.edit')}
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Deferred>
            <SupplierDialog
                open={creating || editing !== null}
                supplier={editing}
                onClose={() => {
                    setCreating(false);
                    setEditing(null);
                }}
            />
        </InventoryShell>
    );
}
