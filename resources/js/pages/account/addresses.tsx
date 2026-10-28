import { Deferred, Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import { AccountShell } from '@/components/account/account-nav';
import {
    AddressCard,
    AddressCardSkeleton,
} from '@/components/account-addresses/address-card';
import { AddressFormDialog } from '@/components/account-addresses/address-form';
import { DeleteAddressDialog } from '@/components/account-addresses/delete-dialog';
import type {
    AddressesProps,
    SavedAddress,
} from '@/components/account-addresses/types';
import { Course } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { update } from '@/routes/account/addresses';

function AddressesSkeleton() {
    const t = useT();

    return (
        <div
            role="status"
            aria-label={t('addresses.loading')}
            className="grid gap-4 md:grid-cols-2"
        >
            <AddressCardSkeleton />
            <AddressCardSkeleton />
        </div>
    );
}

function AddressList({
    addresses,
    max,
}: {
    addresses: SavedAddress[];
    max: number;
}) {
    const t = useT();
    const [editing, setEditing] = useState<SavedAddress | null>(null);
    const [adding, setAdding] = useState(false);
    const [removing, setRemoving] = useState<SavedAddress | null>(null);
    const [busy, setBusy] = useState(false);
    const full = addresses.length >= max;

    const makeDefault = (address: SavedAddress) =>
        router.put(
            update(address.id).url,
            {
                label: address.label,
                line1: address.line1,
                line2: address.line2,
                city: address.city,
                postcode: address.postcode,
                instructions: address.instructions,
                is_default: true,
            },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-ink-muted" aria-live="polite">
                    {t('addresses.count', {
                        count: addresses.length,
                        max,
                    })}
                </p>
                <Button
                    className="min-h-12 sm:min-h-11"
                    onClick={() => setAdding(true)}
                    disabled={full}
                    aria-label={t('addresses.add_aria')}
                >
                    <Plus aria-hidden="true" />
                    {t('addresses.add')}
                </Button>
            </div>
            {full && (
                <p className="text-sm text-ink-muted">
                    {t('addresses.limit_reached')}
                </p>
            )}

            {addresses.length === 0 ? (
                <EmptyState
                    title={t('addresses.empty.title')}
                    description={t('addresses.empty.body')}
                    action={
                        <Button
                            className="min-h-12"
                            onClick={() => setAdding(true)}
                        >
                            <Plus aria-hidden="true" />
                            {t('addresses.add')}
                        </Button>
                    }
                />
            ) : (
                <Course className="grid gap-4 md:grid-cols-2">
                    {addresses.map((address) => (
                        <AddressCard
                            key={address.id}
                            address={address}
                            busy={busy}
                            onEdit={() => setEditing(address)}
                            onDelete={() => setRemoving(address)}
                            onMakeDefault={() => makeDefault(address)}
                        />
                    ))}
                </Course>
            )}

            <AddressFormDialog
                open={adding || editing !== null}
                address={editing}
                first={addresses.length === 0}
                onOpenChange={(open) => {
                    if (!open) {
                        setAdding(false);
                        setEditing(null);
                    }
                }}
            />
            <DeleteAddressDialog
                address={removing}
                onClose={() => setRemoving(null)}
            />
        </>
    );
}

export default function Addresses({ max, addresses }: AddressesProps) {
    const t = useT();

    return (
        <>
            <Head title={t('addresses.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('addresses.meta_description')}
                />
            </Head>
            <AccountShell title={t('addresses.title')}>
                <p className="max-w-[64ch] text-ink-muted">
                    {t('addresses.intro')}
                </p>
                <Deferred data="addresses" fallback={<AddressesSkeleton />}>
                    {addresses && (
                        <AddressList addresses={addresses} max={max} />
                    )}
                </Deferred>
            </AccountShell>
        </>
    );
}
