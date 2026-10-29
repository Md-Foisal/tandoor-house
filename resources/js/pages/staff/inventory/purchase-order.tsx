import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { QuantityField } from '@/components/staff-inventory/quantity-field';
import { InventoryShell, useWhen } from '@/components/staff-inventory/shell';
import {
    poStatusTone,
    type Abilities,
    type IngredientOption,
    type PurchaseOrderLineRow,
    type PurchaseOrderStatus,
    type SupplierRow,
} from '@/components/staff-inventory/types';
import { formatQuantity } from '@/components/staff-inventory/units';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';

type Order = {
    id: number;
    reference: string;
    status: PurchaseOrderStatus;
    total: number;
    sent_at: string | null;
    received_at: string | null;
    created_at: string | null;
    supplier: SupplierRow;
    lines: PurchaseOrderLineRow[];
};

type Draft = {
    ingredient_id: number;
    quantity: number | null;
    initial: number;
    unit_cost: number;
};

export default function PurchaseOrderPage(props: PageProps) {
    return <PurchaseOrder key={props.order.id} {...props} />;
}

type PageProps = Parameters<typeof PurchaseOrder>[0];

function PurchaseOrder({
    order,
    abilities,
    lowCount,
    ingredients,
    errors,
}: {
    order: Order;
    abilities: Abilities;
    lowCount: number;
    ingredients: IngredientOption[];
    errors: Record<string, string>;
}) {
    const t = useT();
    const when = useWhen();
    const online = useOnline();
    const editable = order.status === 'draft' && abilities.manage;
    const original = useMemo(
        () =>
            order.lines.map((line) => ({
                ingredient_id: line.ingredient_id,
                quantity: line.quantity,
            })),
        [order.lines],
    );
    const [lines, setLines] = useState<Draft[]>(() =>
        order.lines.map((line) => ({
            ingredient_id: line.ingredient_id,
            quantity: line.quantity,
            initial: line.quantity,
            unit_cost: line.unit_cost,
        })),
    );
    const [busy, setBusy] = useState(false);
    const [confirm, setConfirm] = useState<'receive' | 'cancel' | null>(null);
    const [adding, setAdding] = useState('');
    const byId = new Map(
        ingredients.map((ingredient) => [ingredient.id, ingredient]),
    );
    const used = new Set(lines.map((line) => line.ingredient_id));

    const lineTotal = (line: Draft) =>
        Math.round(((line.quantity ?? 0) * line.unit_cost) / 1000);
    const total = lines.reduce((sum, line) => sum + lineTotal(line), 0);
    const dirty =
        lines.length !== original.length ||
        lines.some(
            (line, index) =>
                line.ingredient_id !== original[index]?.ingredient_id ||
                line.quantity !== original[index]?.quantity,
        );
    const complete = lines.every(
        (line) => line.quantity !== null && line.quantity > 0,
    );

    const act = (
        path: string,
        method: 'post' | 'put',
        data: Parameters<typeof router.post>[1] = {},
    ) => {
        router[method](
            `/staff/inventory/purchase-orders/${order.id}/${path}`,
            data,
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setConfirm(null);
                },
            },
        );
    };

    const save = () =>
        act('lines', 'put', {
            lines: lines.map(({ ingredient_id, quantity }) => ({
                ingredient_id,
                quantity,
            })),
        });

    const stateError = errors.status;
    const disabledReason = !online ? t('staff_inventory.offline') : null;

    return (
        <InventoryShell
            active="purchase_orders"
            lowCount={lowCount}
            title={order.reference}
            description={t('staff_inventory.po.detail_description', {
                supplier: order.supplier.name,
            })}
            actions={
                <>
                    <Button variant="ghost" asChild>
                        <Link href="/staff/inventory/purchase-orders">
                            <ArrowLeft /> {t('staff_inventory.po.back')}
                        </Link>
                    </Button>
                    {order.status !== 'cancelled' && (
                        <Button variant="outline" asChild>
                            <a
                                href={`/staff/inventory/purchase-orders/${order.id}/pdf`}
                                download
                            >
                                <Download /> {t('staff_inventory.po.pdf')}
                            </a>
                        </Button>
                    )}
                </>
            }
        >
            {stateError && (
                <p
                    role="alert"
                    className="rounded-lg border border-chilli/40 bg-chilli-soft p-3 text-sm"
                >
                    {stateError}
                </p>
            )}

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
                <section
                    aria-labelledby="po-lines"
                    className="overflow-hidden rounded-lg border border-line bg-surface"
                >
                    <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                        <h2 id="po-lines" className="font-display text-xl">
                            {t('staff_inventory.po.lines_title')}
                        </h2>
                        <Badge variant={poStatusTone[order.status]}>
                            {t(`staff_inventory.po.status.${order.status}`)}
                        </Badge>
                    </div>
                    {lines.length === 0 ? (
                        <p className="p-4 text-sm text-ink-muted">
                            {t('staff_inventory.po.no_lines')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-line">
                            {lines.map((line) => {
                                const ingredient = byId.get(line.ingredient_id);
                                const unit = ingredient?.unit ?? 'g';

                                return (
                                    <li
                                        key={line.ingredient_id}
                                        className="grid gap-2 px-4 py-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,15rem)_6rem_auto] md:items-center md:gap-4"
                                    >
                                        <div className="min-w-0">
                                            <p className="font-semibold">
                                                {ingredient?.name}
                                            </p>
                                            {ingredient && (
                                                <p className="text-sm text-ink-muted">
                                                    {t(
                                                        'staff_inventory.po.stock_par',
                                                        {
                                                            stock: formatQuantity(
                                                                ingredient.stock,
                                                                unit,
                                                            ),
                                                            par: formatQuantity(
                                                                ingredient.par_level,
                                                                unit,
                                                            ),
                                                        },
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        {editable ? (
                                            <QuantityField
                                                key={line.ingredient_id}
                                                hideLabel
                                                label={t(
                                                    'staff_inventory.po.quantity_for',
                                                    {
                                                        name:
                                                            ingredient?.name ??
                                                            '',
                                                    },
                                                )}
                                                unit={unit}
                                                initial={line.initial}
                                                onChange={(value) =>
                                                    setLines((current) =>
                                                        current.map((l) =>
                                                            l.ingredient_id ===
                                                            line.ingredient_id
                                                                ? {
                                                                      ...l,
                                                                      quantity:
                                                                          value,
                                                                  }
                                                                : l,
                                                        ),
                                                    )
                                                }
                                            />
                                        ) : (
                                            <p className="tabular-nums">
                                                {formatQuantity(
                                                    line.quantity ?? 0,
                                                    unit,
                                                )}
                                            </p>
                                        )}
                                        <p className="text-sm font-semibold tabular-nums md:text-right">
                                            {formatMoney(lineTotal(line))}
                                        </p>
                                        {editable && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t(
                                                    'staff_inventory.po.remove',
                                                    {
                                                        name:
                                                            ingredient?.name ??
                                                            '',
                                                    },
                                                )}
                                                onClick={() =>
                                                    setLines((current) =>
                                                        current.filter(
                                                            (l) =>
                                                                l.ingredient_id !==
                                                                line.ingredient_id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {editable && (
                        <div className="flex flex-wrap items-center gap-2 border-t border-line bg-paper px-4 py-3">
                            <Select
                                value={adding}
                                onValueChange={(value) => {
                                    const picked = byId.get(Number(value));

                                    if (picked) {
                                        const suggested = Math.max(
                                            1,
                                            picked.par_level - picked.stock,
                                        );

                                        setLines((current) => [
                                            ...current,
                                            {
                                                ingredient_id: picked.id,
                                                quantity: suggested,
                                                initial: suggested,
                                                unit_cost:
                                                    picked.cost_per_thousand,
                                            },
                                        ]);
                                    }

                                    setAdding('');
                                }}
                            >
                                <SelectTrigger
                                    className="min-h-11 w-64"
                                    aria-label={t(
                                        'staff_inventory.po.add_line',
                                    )}
                                >
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    <SelectValue
                                        placeholder={t(
                                            'staff_inventory.po.add_line',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {ingredients
                                        .filter((i) => !used.has(i.id))
                                        .sort(
                                            (a, b) =>
                                                Number(
                                                    b.supplier_id ===
                                                        order.supplier.id,
                                                ) -
                                                Number(
                                                    a.supplier_id ===
                                                        order.supplier.id,
                                                ),
                                        )
                                        .map((i) => (
                                            <SelectItem
                                                key={i.id}
                                                value={String(i.id)}
                                            >
                                                {i.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <Button
                                className="ml-auto"
                                disabled={
                                    !dirty || !complete || busy || !online
                                }
                                onClick={save}
                            >
                                {t('staff_inventory.save')}
                            </Button>
                        </div>
                    )}
                    <div className="flex items-center justify-between border-t border-line px-4 py-3">
                        <span className="font-semibold">
                            {t('staff_inventory.po.total')}
                        </span>
                        <span
                            className="font-display text-display-md tabular-nums"
                            aria-live="polite"
                        >
                            {formatMoney(editable ? total : order.total)}
                        </span>
                    </div>
                </section>

                <aside className="grid gap-4">
                    <section
                        aria-labelledby="po-supplier"
                        className="rounded-lg border border-line bg-surface p-4"
                    >
                        <h2
                            id="po-supplier"
                            className="text-label text-ink-muted uppercase"
                        >
                            {t('staff_inventory.po.supplier')}
                        </h2>
                        <p className="mt-1 font-semibold">
                            {order.supplier.name}
                        </p>
                        <p className="text-sm text-ink-muted">
                            {order.supplier.contact_name}
                        </p>
                        <p className="mt-1 text-sm">{order.supplier.email}</p>
                        <p className="text-sm">{order.supplier.phone}</p>
                        <p className="mt-2 text-sm text-ink-muted">
                            {choice(
                                t('staff_inventory.supplier.lead', {
                                    count: order.supplier.lead_days,
                                }),
                                order.supplier.lead_days,
                            )}
                        </p>
                        <dl className="mt-3 grid gap-1 border-t border-line pt-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-ink-muted">
                                    {t('staff_inventory.po.created')}
                                </dt>
                                <dd>{when(order.created_at)}</dd>
                            </div>
                            {order.sent_at && (
                                <div className="flex justify-between">
                                    <dt className="text-ink-muted">
                                        {t('staff_inventory.po.sent')}
                                    </dt>
                                    <dd>{when(order.sent_at)}</dd>
                                </div>
                            )}
                            {order.received_at && (
                                <div className="flex justify-between">
                                    <dt className="text-ink-muted">
                                        {t('staff_inventory.po.received')}
                                    </dt>
                                    <dd>{when(order.received_at)}</dd>
                                </div>
                            )}
                        </dl>
                    </section>

                    <section
                        aria-labelledby="po-actions"
                        className="grid gap-3 rounded-lg border border-line bg-surface p-4"
                    >
                        <h2
                            id="po-actions"
                            className="text-label text-ink-muted uppercase"
                        >
                            {t('staff_inventory.po.next')}
                        </h2>
                        {order.status === 'draft' && abilities.manage && (
                            <>
                                <Button
                                    size="lg"
                                    className="min-h-12"
                                    disabled={
                                        dirty ||
                                        lines.length === 0 ||
                                        busy ||
                                        !online
                                    }
                                    onClick={() => act('send', 'post')}
                                >
                                    {t('staff_inventory.po.mark_sent')}
                                </Button>
                                <p className="text-sm text-ink-muted">
                                    {dirty
                                        ? t('staff_inventory.po.save_first')
                                        : t('staff_inventory.po.send_hint')}
                                </p>
                            </>
                        )}
                        {order.status === 'sent' && (
                            <>
                                <Button
                                    size="lg"
                                    className="min-h-12"
                                    disabled={busy || !online}
                                    onClick={() => setConfirm('receive')}
                                >
                                    {t('staff_inventory.po.mark_received')}
                                </Button>
                                <p className="text-sm text-ink-muted">
                                    {t('staff_inventory.po.receive_hint')}
                                </p>
                            </>
                        )}
                        {(order.status === 'draft' ||
                            order.status === 'sent') &&
                            abilities.manage && (
                                <Button
                                    variant="outline"
                                    disabled={busy || !online}
                                    onClick={() => setConfirm('cancel')}
                                >
                                    {t('staff_inventory.po.cancel_order')}
                                </Button>
                            )}
                        {(order.status === 'received' ||
                            order.status === 'cancelled') && (
                            <p className="text-sm text-ink-muted">
                                {t(`staff_inventory.po.closed_${order.status}`)}
                            </p>
                        )}
                        {order.status === 'draft' && !abilities.manage && (
                            <p className="text-sm text-ink-muted">
                                {t('staff_inventory.po.managers_only')}
                            </p>
                        )}
                        {disabledReason && (
                            <p className="text-sm text-chilli">
                                {disabledReason}
                            </p>
                        )}
                    </section>
                </aside>
            </div>

            <Dialog
                open={confirm !== null}
                onOpenChange={(open) => !open && setConfirm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                `staff_inventory.po.confirm_${confirm ?? 'receive'}_title`,
                                { reference: order.reference },
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                `staff_inventory.po.confirm_${confirm ?? 'receive'}_body`,
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirm(null)}
                        >
                            {t('staff_inventory.po.keep')}
                        </Button>
                        <Button
                            variant={
                                confirm === 'cancel' ? 'destructive' : 'default'
                            }
                            disabled={busy || !online}
                            onClick={() =>
                                act(
                                    confirm === 'cancel' ? 'cancel' : 'receive',
                                    'post',
                                )
                            }
                        >
                            {t(
                                `staff_inventory.po.confirm_${confirm ?? 'receive'}`,
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </InventoryShell>
    );
}
