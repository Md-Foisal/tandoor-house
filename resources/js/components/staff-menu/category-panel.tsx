import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/brand/states';
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
import { useT, choice } from '@/lib/i18n';
import type { CategoryRow } from './types';

export function CategoryPanel({
    categories,
    online,
    onEdit,
    onCreate,
}: {
    categories: CategoryRow[];
    online: boolean;
    onEdit: (category: CategoryRow) => void;
    onCreate: () => void;
}) {
    const t = useT();
    const [deleting, setDeleting] = useState<CategoryRow | null>(null);
    const [busy, setBusy] = useState(false);

    if (categories.length === 0) {
        return (
            <EmptyState
                title={t('staff_menu.categories.empty_title')}
                description={t('staff_menu.categories.empty_body')}
                action={
                    <Button size="lg" onClick={onCreate}>
                        {t('staff_menu.new_category')}
                    </Button>
                }
            />
        );
    }

    const move = (category: CategoryRow, direction: 'up' | 'down') =>
        router.post(
            `/staff/menu/categories/${category.id}/move`,
            { direction },
            { preserveScroll: true, only: ['categories', 'items'] },
        );

    return (
        <section
            aria-labelledby="categories-heading"
            className="px-4 py-5 md:px-6"
        >
            <h2 id="categories-heading" className="font-display text-xl">
                {t('staff_menu.categories.heading')}
            </h2>
            <p className="mt-1 mb-4 text-sm text-ink-muted">
                {t('staff_menu.categories.help')}
            </p>

            <ol className="divide-y divide-line rounded-lg border border-line bg-surface">
                {categories.map((category, index) => {
                    const blocked = category.items_count > 0;

                    return (
                        <li
                            key={category.id}
                            className="flex flex-wrap items-center gap-3 px-3 py-3 sm:px-4"
                        >
                            <div className="flex flex-col">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    disabled={index === 0 || !online}
                                    aria-label={t(
                                        'staff_menu.categories.move_up',
                                        { name: category.name },
                                    )}
                                    onClick={() => move(category, 'up')}
                                >
                                    <ArrowUp aria-hidden="true" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    disabled={
                                        index === categories.length - 1 ||
                                        !online
                                    }
                                    aria-label={t(
                                        'staff_menu.categories.move_down',
                                        { name: category.name },
                                    )}
                                    onClick={() => move(category, 'down')}
                                >
                                    <ArrowDown aria-hidden="true" />
                                </Button>
                            </div>

                            <div className="min-w-0 flex-1">
                                <p className="font-semibold">{category.name}</p>
                                {category.description && (
                                    <p className="text-sm text-ink-muted">
                                        {category.description}
                                    </p>
                                )}
                                <div className="mt-1 flex flex-wrap items-center gap-1.5 text-sm text-ink-muted">
                                    <span>
                                        {choice(
                                            t('staff_menu.list.count'),
                                            category.items_count,
                                        ).replace(
                                            ':count',
                                            String(category.items_count),
                                        )}
                                    </span>
                                    {(category.service_periods.length > 0
                                        ? category.service_periods.map((p) =>
                                              t(`staff_menu.periods.${p}`),
                                          )
                                        : [t('staff_menu.categories.all_day')]
                                    ).map((label) => (
                                        <Badge key={label} variant="secondary">
                                            {label}
                                        </Badge>
                                    ))}
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="lg"
                                    onClick={() => onEdit(category)}
                                >
                                    <Pencil aria-hidden="true" />
                                    {t('staff_menu.categories.edit')}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="lg"
                                    disabled={blocked || !online}
                                    title={
                                        blocked
                                            ? t(
                                                  'staff_menu.categories.delete_blocked',
                                              )
                                            : undefined
                                    }
                                    aria-label={`${t('staff_menu.categories.delete')}: ${category.name}`}
                                    onClick={() => setDeleting(category)}
                                >
                                    <Trash2 aria-hidden="true" />
                                    <span className="sr-only sm:not-sr-only">
                                        {t('staff_menu.categories.delete')}
                                    </span>
                                </Button>
                            </div>
                            {blocked && (
                                <p className="basis-full pl-12 text-sm text-ink-muted">
                                    {t('staff_menu.categories.delete_blocked')}
                                </p>
                            )}
                        </li>
                    );
                })}
            </ol>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff_menu.categories.delete_title', {
                                name: deleting?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('staff_menu.categories.delete_body')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            size="lg"
                            onClick={() => setDeleting(null)}
                        >
                            {t('staff_menu.categories.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            size="lg"
                            disabled={busy || !online}
                            onClick={() => {
                                if (!deleting) return;
                                router.delete(
                                    `/staff/menu/categories/${deleting.id}`,
                                    {
                                        preserveScroll: true,
                                        onError: (errors) =>
                                            toast.error(
                                                errors.category ??
                                                    t(
                                                        'staff_menu.errors.failed',
                                                    ),
                                            ),
                                        onStart: () => setBusy(true),
                                        onFinish: () => {
                                            setBusy(false);
                                            setDeleting(null);
                                        },
                                    },
                                );
                            }}
                        >
                            {t('staff_menu.categories.delete_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
