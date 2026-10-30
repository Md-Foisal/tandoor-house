import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { toast } from 'sonner';
import { OpenStatus } from '@/components/brand/open-status';
import { EmptyState, useOnline } from '@/components/brand/states';
import { CategoryTabs } from '@/components/menu-page/category-tabs';
import { FilterBar } from '@/components/menu-page/filter-bar';
import { filterCategories } from '@/components/menu-page/filters';
import { MenuErrorBoundary } from '@/components/menu-page/menu-error-boundary';
import { MenuSection } from '@/components/menu-page/menu-section';
import { MenuSkeleton } from '@/components/menu-page/menu-skeleton';
import { useMenuFilters } from '@/components/menu-page/use-menu-filters';
import { Button } from '@/components/ui/button';
import { useAddToBasket } from '@/lib/basket';
import { useT } from '@/lib/i18n';
import type { Category, MenuItem } from '@/types';

type Props = {
    categories?: Category[];
};

function MenuBody({ categories }: { categories: Category[] }) {
    const t = useT();
    const online = useOnline();
    const { add } = useAddToBasket();
    const { filters, update, clear } = useMenuFilters();

    const visible = useMemo(
        () => filterCategories(categories, filters),
        [categories, filters],
    );
    const resultCount = visible.reduce(
        (total, category) => total + category.items.length,
        0,
    );

    const onAdd = (item: MenuItem, origin: HTMLElement | null) => {
        if (!online) {
            toast.error(t('ui.states.offline'));

            return;
        }

        if (item.has_options) {
            router.visit(`/menu/${item.slug}`);

            return;
        }

        add({ menu_item_id: item.id }, origin);
    };

    return (
        <div>
            <div className="container-page pb-4">
                <FilterBar
                    filters={filters}
                    update={update}
                    clear={clear}
                    resultCount={resultCount}
                />
            </div>

            {resultCount === 0 ? (
                <div className="container-page">
                    <EmptyState
                        title={
                            filters.query.trim() !== ''
                                ? t('menu.empty.title', {
                                      query: filters.query.trim(),
                                  })
                                : t('menu.empty.title_filters')
                        }
                        description={t('menu.empty.body')}
                        action={
                            <Button variant="outline" onClick={clear}>
                                {t('menu.empty.clear')}
                            </Button>
                        }
                    />
                </div>
            ) : (
                <>
                    <CategoryTabs
                        tabs={visible.map(({ slug, name }) => ({
                            slug,
                            name,
                        }))}
                    />
                    <div className="container-page">
                        {visible.map((category) => (
                            <MenuSection
                                key={category.id}
                                category={category}
                                onAdd={onAdd}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

export default function MenuIndex({ categories }: Props) {
    const t = useT();
    const { openStatus } = usePage().props;

    return (
        <>
            <Head title={t('menu.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('menu.meta_description')}
                />
            </Head>

            <div className="container-page flex flex-col gap-3 pt-6 pb-5 md:pt-10">
                <h1 className="font-display text-display-lg text-ink">
                    {t('menu.heading')}
                </h1>
                <p className="max-w-[56ch] text-ink-muted">{t('menu.intro')}</p>
                <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                    <OpenStatus status={openStatus} />
                    <p className="text-ink-muted">
                        {t('menu.allergen_note')}{' '}
                        <Link
                            href="/allergens"
                            className="inline-flex min-h-11 items-center font-semibold text-ember underline underline-offset-4 outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember"
                        >
                            {t('menu.allergen_link')}
                        </Link>
                    </p>
                </div>
            </div>

            <MenuErrorBoundary title={t('menu.error.title')}>
                <Deferred data="categories" fallback={<MenuSkeleton />}>
                    <MenuBody categories={categories ?? []} />
                </Deferred>
            </MenuErrorBoundary>
        </>
    );
}
