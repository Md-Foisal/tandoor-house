import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState, useOnline } from '@/components/brand/states';
import { CategoryDialog } from '@/components/staff-menu/category-dialog';
import { CategoryPanel } from '@/components/staff-menu/category-panel';
import { MenuFilters } from '@/components/staff-menu/filters';
import {
    ItemRow,
    ItemRowSkeleton,
    ItemTableHeader,
} from '@/components/staff-menu/item-row';
import type {
    CategoryRow,
    ItemRow as Item,
    MenuIndexProps,
} from '@/components/staff-menu/types';
import { StaffPageHeader } from '@/components/staff/page-header';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useT, choice } from '@/lib/i18n';

function isTyping(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
            target.closest('[role="dialog"], [role="listbox"]') !== null)
    );
}

export default function MenuIndex({
    filters,
    categories,
    items,
    total,
    stations,
    periods,
}: MenuIndexProps) {
    const t = useT();
    const online = useOnline();
    const searchRef = useRef<HTMLInputElement>(null);
    const [tab, setTab] = useState<'items' | 'categories'>('items');
    const [loading, setLoading] = useState(false);
    const [pending, setPending] = useState<Set<number>>(new Set());
    const [optimistic, setOptimistic] = useState<Record<string, boolean>>({});
    const [dialog, setDialog] = useState<{
        category: CategoryRow | null;
    } | null>(null);

    const filtered =
        filters.q !== '' ||
        filters.category !== null ||
        filters.availability !== null ||
        filters.diet !== null ||
        filters.station !== null;

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                isTyping(event.target)
            ) {
                return;
            }

            if (event.key === '/') {
                event.preventDefault();
                setTab('items');
                window.setTimeout(() => searchRef.current?.focus(), 0);
            } else if (event.key.toLowerCase() === 'n') {
                event.preventDefault();
                router.visit('/staff/menu/items/create');
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const grouped = useMemo(
        () =>
            categories
                .map((category) => ({
                    category,
                    items: items.filter(
                        (item) => item.category_id === category.id,
                    ),
                }))
                .filter((group) => !filtered || group.items.length > 0),
        [categories, items, filtered],
    );

    const setFlag = (
        item: Item,
        flag: 'availability' | 'special',
        value: boolean,
    ) => {
        const key = `${item.id}:${flag}`;

        setOptimistic((current) => ({ ...current, [key]: value }));
        setPending((current) => new Set(current).add(item.id));

        router.patch(
            `/staff/menu/items/${item.id}/${flag}`,
            { value },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['items', 'categories'],
                onError: () => toast.error(t('staff_menu.errors.failed')),
                onFinish: () => {
                    setOptimistic((current) => {
                        const { [key]: _gone, ...rest } = current;

                        return rest;
                    });
                    setPending((current) => {
                        const next = new Set(current);
                        next.delete(item.id);

                        return next;
                    });
                },
            },
        );
    };

    const shown = (item: Item): Item => ({
        ...item,
        is_available:
            optimistic[`${item.id}:availability`] ?? item.is_available,
        is_special: optimistic[`${item.id}:special`] ?? item.is_special,
    });

    return (
        <>
            <Head title={t('staff_menu.title')} />
            <StaffPageHeader
                title={t('staff_menu.title')}
                description={t('staff_menu.description')}
                actions={
                    <>
                        <Button
                            variant="outline"
                            size="lg"
                            disabled={!online}
                            onClick={() => setDialog({ category: null })}
                        >
                            <Plus aria-hidden="true" />
                            {t('staff_menu.new_category')}
                        </Button>
                        <Button asChild size="lg">
                            <Link href="/staff/menu/items/create">
                                <Plus aria-hidden="true" />
                                {t('staff_menu.new_item')}
                            </Link>
                        </Button>
                    </>
                }
            >
                <div className="grid gap-3">
                    <Tabs
                        value={tab}
                        onValueChange={(v) => setTab(v as typeof tab)}
                    >
                        <TabsList>
                            <TabsTrigger
                                value="items"
                                aria-controls={undefined}
                                className="min-h-11"
                            >
                                {t('staff_menu.tabs.items')}
                            </TabsTrigger>
                            <TabsTrigger
                                value="categories"
                                aria-controls={undefined}
                                className="min-h-11"
                            >
                                {t('staff_menu.tabs.categories')}
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    {tab === 'items' && (
                        <>
                            <MenuFilters
                                filters={filters}
                                categories={categories}
                                stations={stations}
                                searchRef={searchRef}
                                onLoading={setLoading}
                            />
                            <p className="text-sm text-ink-muted">
                                {t('staff_menu.shortcuts')}
                            </p>
                        </>
                    )}
                </div>
            </StaffPageHeader>

            {tab === 'categories' ? (
                <CategoryPanel
                    categories={categories}
                    online={online}
                    onCreate={() => setDialog({ category: null })}
                    onEdit={(category) => setDialog({ category })}
                />
            ) : total === 0 && categories.length === 0 ? (
                <EmptyState
                    title={t('staff_menu.list.blank_title')}
                    description={t('staff_menu.list.blank_body')}
                    action={
                        <Button
                            size="lg"
                            onClick={() => setDialog({ category: null })}
                        >
                            {t('staff_menu.new_category')}
                        </Button>
                    }
                />
            ) : (
                <div aria-busy={loading} aria-live="polite">
                    {loading && (
                        <ul
                            role="status"
                            aria-label={t('staff_menu.list.loading')}
                        >
                            {Array.from({ length: 6 }, (_, i) => (
                                <ItemRowSkeleton key={i} />
                            ))}
                        </ul>
                    )}
                    {!loading && filtered && items.length === 0 && (
                        <EmptyState
                            title={t('staff_menu.list.none_title')}
                            description={t('staff_menu.list.none_body')}
                        />
                    )}
                    {!loading && <ItemTableHeader />}
                    {!loading &&
                        grouped.map(({ category, items: rows }) => (
                            <section
                                key={category.id}
                                aria-labelledby={`cat-${category.id}`}
                            >
                                <div className="flex items-baseline justify-between gap-3 bg-clay/60 px-4 py-2 md:px-4">
                                    <h2
                                        id={`cat-${category.id}`}
                                        className="font-display text-lg"
                                    >
                                        {category.name}
                                        <span className="ml-2 text-sm font-normal text-ink-muted">
                                            {choice(
                                                t('staff_menu.list.count'),
                                                rows.length,
                                            ).replace(
                                                ':count',
                                                String(rows.length),
                                            )}
                                        </span>
                                    </h2>
                                </div>
                                {rows.length === 0 ? (
                                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-4">
                                        <p className="text-ink-muted">
                                            {t(
                                                'staff_menu.list.empty_category',
                                            )}
                                        </p>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="lg"
                                        >
                                            <Link
                                                href={`/staff/menu/items/create?category=${category.id}`}
                                            >
                                                <Plus aria-hidden="true" />
                                                {t(
                                                    'staff_menu.list.add_to_category',
                                                )}
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    <ul>
                                        {rows.map((item) => (
                                            <ItemRow
                                                key={item.id}
                                                item={shown(item)}
                                                stations={stations}
                                                online={online}
                                                pending={pending.has(item.id)}
                                                onFlag={setFlag}
                                            />
                                        ))}
                                    </ul>
                                )}
                            </section>
                        ))}
                </div>
            )}

            <CategoryDialog
                open={dialog !== null}
                category={dialog?.category ?? null}
                periods={periods}
                online={online}
                onOpenChange={(open) => !open && setDialog(null)}
            />
        </>
    );
}
