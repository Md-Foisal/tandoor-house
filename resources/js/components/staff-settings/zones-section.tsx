import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/brand/states';
import { Field } from '@/components/form/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { MoneyLocked } from './section-card';
import type { SettingsContext, Zone } from './types';
import { penceToPounds, poundsToPence } from './types';

type Data = {
    name: string;
    prefixes: string;
    fee: string;
    minimum_order: string;
    eta_minutes: string;
    is_active: boolean;
};

function ZoneDialog({
    zone,
    open,
    context,
    onClose,
}: {
    zone: Zone | null;
    open: boolean;
    context: SettingsContext;
    onClose: () => void;
}) {
    const t = useT();
    const form = useForm<Data>({
        name: '',
        prefixes: '',
        fee: '',
        minimum_order: '',
        eta_minutes: '30',
        is_active: true,
    });
    const { data, setData, errors, processing } = form;
    const editing = zone !== null;
    const moneyLocked = !context.canEditMoney;

    useEffect(() => {
        if (!open) {
            return;
        }
        form.clearErrors();
        setData({
            name: zone?.name ?? '',
            prefixes: zone?.prefixes.join(', ') ?? '',
            fee: zone ? penceToPounds(zone.fee) : '2.50',
            minimum_order: zone ? penceToPounds(zone.minimum_order) : '15',
            eta_minutes: String(zone?.eta_minutes ?? 30),
            is_active: zone?.is_active ?? true,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, zone?.id]);

    const prefixError =
        errors.prefixes ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('prefixes.'),
        )?.[1];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const fee = poundsToPence(data.fee);
        const minimum = poundsToPence(data.minimum_order);

        if (fee === null || minimum === null) {
            form.setError(
                fee === null ? 'fee' : 'minimum_order',
                t('staff_settings.errors.amount'),
            );

            return;
        }

        form.transform((d) => ({
            name: d.name,
            prefixes: d.prefixes,
            fee,
            minimum_order: minimum,
            eta_minutes: Number(d.eta_minutes),
            is_active: d.is_active,
        }));

        const options = { preserveScroll: true, onSuccess: onClose };

        if (editing) {
            form.patch(`/staff/settings/zones/${zone.id}`, options);
        } else {
            form.post('/staff/settings/zones', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? t('staff_settings.zones.edit', {
                                  name: zone.name,
                              })
                            : t('staff_settings.zones.add')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('staff_settings.zones.intro')}
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={submit} noValidate>
                    <Field
                        name="zone-name"
                        label={t('staff_settings.zones.name')}
                        error={errors.name}
                        required
                    >
                        {(p) => (
                            <Input
                                {...p}
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        name="zone-prefixes"
                        label={t('staff_settings.zones.prefixes')}
                        hint={t('staff_settings.zones.prefixes_hint')}
                        error={prefixError}
                        required
                    >
                        {(p) => (
                            <Input
                                {...p}
                                autoCapitalize="characters"
                                value={data.prefixes}
                                onChange={(e) =>
                                    setData('prefixes', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    {moneyLocked && (
                        <p className="text-sm text-ink-muted" role="note">
                            {t('staff_settings.zones.money_locked')}
                        </p>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            name="zone-fee"
                            label={t('staff_settings.zones.fee')}
                            error={errors.fee}
                            required
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    inputMode="decimal"
                                    readOnly={moneyLocked}
                                    value={data.fee}
                                    onChange={(e) =>
                                        setData('fee', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            name="zone-minimum"
                            label={t('staff_settings.zones.minimum')}
                            error={errors.minimum_order}
                            required
                        >
                            {(p) => (
                                <Input
                                    {...p}
                                    inputMode="decimal"
                                    readOnly={moneyLocked}
                                    value={data.minimum_order}
                                    onChange={(e) =>
                                        setData('minimum_order', e.target.value)
                                    }
                                />
                            )}
                        </Field>
                    </div>
                    <Field
                        name="zone-eta"
                        label={t('staff_settings.zones.eta')}
                        error={errors.eta_minutes}
                        required
                    >
                        {(p) => (
                            <Input
                                {...p}
                                type="number"
                                inputMode="numeric"
                                min={5}
                                max={180}
                                className="w-32"
                                value={data.eta_minutes}
                                onChange={(e) =>
                                    setData('eta_minutes', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <div className="flex min-h-11 items-center gap-3">
                        <Switch
                            id="zone-active"
                            checked={data.is_active}
                            onCheckedChange={(v) => setData('is_active', v)}
                        />
                        <label htmlFor="zone-active" className="font-semibold">
                            {t('staff_settings.zones.active')}
                        </label>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('staff_settings.zones.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !context.online}
                        >
                            {processing
                                ? t('staff_settings.saving')
                                : t('staff_settings.save')}
                        </Button>
                    </DialogFooter>
                    {!context.online && (
                        <p className="text-sm text-ink-muted" role="status">
                            {t('staff_settings.offline_reason')}
                        </p>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ZonesSection({
    zones,
    context,
}: {
    zones: Zone[];
    context: SettingsContext;
}) {
    const t = useT();
    const [editing, setEditing] = useState<Zone | null>(null);
    const [creating, setCreating] = useState(false);
    const [removing, setRemoving] = useState<Zone | null>(null);
    const [busy, setBusy] = useState(false);

    const remove = () => {
        if (!removing) {
            return;
        }
        setBusy(true);
        router.delete(`/staff/settings/zones/${removing.id}`, {
            preserveScroll: true,
            onSuccess: () => setRemoving(null),
            onFinish: () => setBusy(false),
        });
    };

    const status = (zone: Zone) =>
        zone.is_active ? null : (
            <Badge variant="outline">
                {t('staff_settings.zones.inactive')}
            </Badge>
        );

    const actions = (zone: Zone) => (
        <div className="flex justify-end gap-1">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t('staff_settings.zones.edit', { name: zone.name })}
                onClick={() => setEditing(zone)}
            >
                <Pencil aria-hidden="true" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t('staff_settings.zones.remove', {
                    name: zone.name,
                })}
                onClick={() => setRemoving(zone)}
            >
                <Trash2 aria-hidden="true" />
            </Button>
        </div>
    );

    return (
        <section className="grid gap-5 rounded-lg border border-line bg-surface p-4 md:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-display text-display-md">
                        {t('staff_settings.sections.zones.title')}
                    </h2>
                    <p className="mt-1 max-w-prose text-ink-muted">
                        {t('staff_settings.zones.intro')}
                    </p>
                </div>
                <Button
                    size="lg"
                    onClick={() => setCreating(true)}
                    disabled={!context.canEditMoney}
                >
                    <Plus aria-hidden="true" />
                    {t('staff_settings.zones.add')}
                </Button>
            </div>
            {!context.canEditMoney && (
                <>
                    <MoneyLocked />
                    <p className="text-sm text-ink-muted">
                        {t('staff_settings.zones.add_locked')}
                    </p>
                </>
            )}

            {zones.length === 0 ? (
                <EmptyState
                    title={t('staff_settings.zones.empty_title')}
                    description={t('staff_settings.zones.empty_body')}
                />
            ) : (
                <>
                    <table className="hidden w-full text-left md:table">
                        <thead className="text-label text-ink-muted uppercase">
                            <tr className="border-b border-line">
                                <th className="py-2">
                                    {t('staff_settings.zones.columns.zone')}
                                </th>
                                <th className="py-2">
                                    {t('staff_settings.zones.columns.codes')}
                                </th>
                                <th className="py-2 text-right">
                                    {t('staff_settings.zones.columns.fee')}
                                </th>
                                <th className="py-2 text-right">
                                    {t('staff_settings.zones.columns.minimum')}
                                </th>
                                <th className="py-2 text-right">
                                    {t('staff_settings.zones.columns.eta')}
                                </th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {zones.map((zone) => (
                                <tr key={zone.id}>
                                    <td className="py-3 font-semibold">
                                        {zone.name} {status(zone)}
                                    </td>
                                    <td className="py-3 text-sm">
                                        {zone.prefixes.join(', ')}
                                    </td>
                                    <td className="py-3 text-right tabular-nums">
                                        {formatMoney(zone.fee)}
                                    </td>
                                    <td className="py-3 text-right tabular-nums">
                                        {formatMoney(zone.minimum_order)}
                                    </td>
                                    <td className="py-3 text-right tabular-nums">
                                        {t('staff_settings.zones.minutes', {
                                            count: zone.eta_minutes,
                                        })}
                                    </td>
                                    <td className="py-3">{actions(zone)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <ul className="grid gap-3 md:hidden">
                        {zones.map((zone) => (
                            <li
                                key={zone.id}
                                className="grid gap-1 rounded-md border border-line p-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="font-semibold">
                                        {zone.name} {status(zone)}
                                    </p>
                                    {actions(zone)}
                                </div>
                                <p className="text-sm text-ink-muted">
                                    {zone.prefixes.join(', ')}
                                </p>
                                <p className="text-sm tabular-nums">
                                    {formatMoney(zone.fee)} ·{' '}
                                    {formatMoney(zone.minimum_order)}{' '}
                                    {t(
                                        'staff_settings.zones.columns.minimum',
                                    ).toLowerCase()}{' '}
                                    ·{' '}
                                    {t('staff_settings.zones.minutes', {
                                        count: zone.eta_minutes,
                                    })}
                                </p>
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <ZoneDialog
                open={creating || editing !== null}
                zone={editing}
                context={context}
                onClose={() => {
                    setCreating(false);
                    setEditing(null);
                }}
            />

            <Dialog
                open={removing !== null}
                onOpenChange={(next) => !next && setRemoving(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff_settings.zones.remove_title', {
                                name: removing?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('staff_settings.zones.remove_body')}
                            {removing && removing.orders_count > 0 && (
                                <>
                                    {' '}
                                    {choice(
                                        t('staff_settings.zones.orders', {
                                            count: removing.orders_count,
                                        }),
                                        removing.orders_count,
                                    )}
                                    .
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRemoving(null)}
                        >
                            {t('staff_settings.zones.cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={busy || !context.online}
                            onClick={remove}
                        >
                            {t('staff_settings.zones.remove_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
