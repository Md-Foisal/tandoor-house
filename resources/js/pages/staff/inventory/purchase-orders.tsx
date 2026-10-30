import { Deferred, Link, router, useForm } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState, useOnline } from '@/components/brand/states';
import {
    InventoryShell,
    RowsSkeleton,
    useWhen,
} from '@/components/staff-inventory/shell';
import {
    poStatusTone,
    type Abilities,
    type PurchaseOrderRow,
    type PurchaseOrderStatus,
} from '@/components/staff-inventory/types';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { choice, useT } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

const statuses: PurchaseOrderStatus[] = [
    'draft',
    'sent',
    'received',
    'cancelled',
];

export default function PurchaseOrders({
    orders,
    filters,
    abilities,
    lowCount,
    counts,
    suppliers,
}: {
    orders?: PurchaseOrderRow[];
    filters: { status: PurchaseOrderStatus | null };
    abilities: Abilities;
    lowCount: number;
    counts: Partial<Record<PurchaseOrderStatus, number>>;
    suppliers: { id: number; name: string; low: number }[];
}) {
    const t = useT();
    const when = useWhen();
    const online = useOnline();
    const [loading, setLoading] = useState(false);
    const [creating, setCreating] = useState(false);
    const form = useForm<{ supplier_id: number | null }>({ supplier_id: null });
    const chosen = suppliers.find(
        (supplier) => supplier.id === form.data.supplier_id,
    );

    const filter = (status: PurchaseOrderStatus | null) =>
        router.get(
            '/staff/inventory/purchase-orders',
            status ? { status } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['orders', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    return (
        <InventoryShell
            active="purchase_orders"
            lowCount={lowCount}
            description={t('staff_inventory.po.description')}
            actions={
                abilities.manage && (
                    <Button size="lg" onClick={() => setCreating(true)}>
                        <Plus /> {t('staff_inventory.po.new')}
                    </Button>
                )
            }
            toolbar={
                <div
                    role="group"
                    aria-label={t('staff_inventory.po.filter')}
                    className="flex flex-wrap gap-2"
                >
                    {[null, ...statuses].map((status) => (
                        <button
                            key={status ?? 'all'}
                            type="button"
                            aria-pressed={filters.status === status}
                            onClick={() => filter(status)}
                            className={cn(
                                'min-h-11 rounded-full border px-4 text-sm font-semibold transition-colors',
                                filters.status === status
                                    ? 'border-ember bg-ember-soft'
                                    : 'border-line-strong bg-surface hover:bg-clay',
                            )}
                        >
                            {status
                                ? t(`staff_inventory.po.status.${status}`)
                                : t('staff_inventory.po.all')}
                            {status && (counts[status] ?? 0) > 0 && (
                                <span className="ml-2 text-ink-muted tabular-nums">
                                    {counts[status]}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            }
        >
            <Deferred data="orders" fallback={<RowsSkeleton rows={6} />}>
                {orders && orders.length === 0 ? (
                    <div className="rounded-lg border border-line bg-surface">
                        <EmptyState
                            title={t('staff_inventory.po.empty_title')}
                            description={
                                filters.status
                                    ? t('staff_inventory.po.empty_filtered')
                                    : t('staff_inventory.po.empty')
                            }
                        />
                    </div>
                ) : (
                    <ul
                        className={cn(
                            'divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface transition-opacity',
                            loading && 'opacity-60',
                        )}
                        aria-busy={loading}
                    >
                        {(orders ?? []).map((order) => (
                            <li key={order.id}>
                                <Link
                                    href={`/staff/inventory/purchase-orders/${order.id}`}
                                    className="grid min-h-16 items-center gap-2 px-4 py-3 hover:bg-clay md:grid-cols-[8rem_minmax(0,2fr)_7rem_6rem_9rem_1.5rem] md:gap-4"
                                >
                                    <p className="font-semibold tabular-nums">
                                        {order.reference}
                                    </p>
                                    <p className="min-w-0 truncate">
                                        {order.supplier}
                                    </p>
                                    <p>
                                        <Badge
                                            variant={poStatusTone[order.status]}
                                        >
                                            {t(
                                                `staff_inventory.po.status.${order.status}`,
                                            )}
                                        </Badge>
                                    </p>
                                    <p className="text-sm text-ink-muted">
                                        {choice(
                                            t('staff_inventory.po.lines', {
                                                count: order.lines,
                                            }),
                                            order.lines,
                                        )}
                                    </p>
                                    <p className="text-sm tabular-nums md:text-right">
                                        <span className="font-semibold">
                                            {formatMoney(order.total)}
                                        </span>
                                        <span className="block text-ink-muted">
                                            {order.received_at
                                                ? t(
                                                      'staff_inventory.po.received_when',
                                                      {
                                                          when: when(
                                                              order.received_at,
                                                          ),
                                                      },
                                                  )
                                                : order.sent_at
                                                  ? t(
                                                        'staff_inventory.po.sent_when',
                                                        {
                                                            when: when(
                                                                order.sent_at,
                                                            ),
                                                        },
                                                    )
                                                  : t(
                                                        'staff_inventory.po.created_when',
                                                        {
                                                            when: when(
                                                                order.created_at,
                                                            ),
                                                        },
                                                    )}
                                        </span>
                                    </p>
                                    <ChevronRight
                                        className="hidden size-5 text-ink-muted md:block"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Deferred>

            <Dialog
                open={creating}
                onOpenChange={(open) => !open && setCreating(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff_inventory.po.new_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('staff_inventory.po.new_body')}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/staff/inventory/purchase-orders', {
                                onSuccess: () => setCreating(false),
                            });
                        }}
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="po-supplier">
                                {t('staff_inventory.po.supplier')}
                            </Label>
                            <Select
                                value={
                                    form.data.supplier_id
                                        ? String(form.data.supplier_id)
                                        : undefined
                                }
                                onValueChange={(value) =>
                                    form.setData('supplier_id', Number(value))
                                }
                            >
                                <SelectTrigger
                                    id="po-supplier"
                                    className="min-h-11"
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'staff_inventory.po.pick_supplier',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {suppliers.map((supplier) => (
                                        <SelectItem
                                            key={supplier.id}
                                            value={String(supplier.id)}
                                        >
                                            {supplier.name}
                                            {supplier.low > 0
                                                ? ` · ${choice(t('staff_inventory.po.low_count', { count: supplier.low }), supplier.low)}`
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {chosen && (
                                <p
                                    className="text-sm text-ink-muted"
                                    aria-live="polite"
                                >
                                    {chosen.low > 0
                                        ? choice(
                                              t(
                                                  'staff_inventory.po.suggested',
                                                  { count: chosen.low },
                                              ),
                                              chosen.low,
                                          )
                                        : t('staff_inventory.po.nothing_low')}
                                </p>
                            )}
                            {form.errors.supplier_id && (
                                <p role="alert" className="text-sm text-chilli">
                                    {form.errors.supplier_id}
                                </p>
                            )}
                        </div>
                        <DialogFooter className="items-center">
                            {!online && (
                                <p className="mr-auto text-sm text-chilli">
                                    {t('staff_inventory.offline')}
                                </p>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreating(false)}
                            >
                                {t('staff_inventory.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !online ||
                                    form.data.supplier_id === null
                                }
                            >
                                {t('staff_inventory.po.create')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </InventoryShell>
    );
}
