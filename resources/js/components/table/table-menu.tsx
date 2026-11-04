import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { SectionHeading } from '@/components/brand/section-heading';
import { useOnline } from '@/components/brand/states';
import { CategoryTabs, sectionId } from '@/components/menu-page/category-tabs';
import { DishRow, DishRowSkeleton } from '@/components/menu/dish-row';
import { useAddToBasket } from '@/lib/basket';
import { useT } from '@/lib/i18n';
import type { Category, MenuItem } from '@/types';

/**
 * The whole menu in the compact row style. Allergens stay visible in every
 * row; dishes that need a choice open their page.
 */
export function TableMenu({ categories }: { categories: Category[] }) {
    const t = useT();
    const online = useOnline();
    const { add } = useAddToBasket();

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
            <CategoryTabs
                tabs={categories.map(({ slug, name }) => ({ slug, name }))}
            />
            <div className="container-page">
                {categories.map((category) => (
                    <section
                        key={category.id}
                        id={sectionId(category.slug)}
                        data-slug={category.slug}
                        tabIndex={-1}
                        aria-labelledby={`${sectionId(category.slug)}-title`}
                        className="scroll-mt-28 py-6 outline-hidden md:scroll-mt-32"
                    >
                        <SectionHeading
                            id={`${sectionId(category.slug)}-title`}
                            title={category.name}
                            description={category.description}
                            size="md"
                        />
                        <ul className="mt-2 lg:grid lg:grid-cols-2 lg:gap-x-12">
                            {category.items.map((item) => (
                                <li
                                    key={item.id}
                                    className="border-b border-line"
                                >
                                    <DishRow
                                        item={item}
                                        href={`/menu/${item.slug}`}
                                        onAdd={(chosen) =>
                                            onAdd(
                                                chosen,
                                                document
                                                    .querySelector<HTMLElement>(
                                                        `#dish-${chosen.id}`,
                                                    )
                                                    ?.closest('article')
                                                    ?.querySelector('button') ??
                                                    null,
                                            )
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </div>
    );
}

export function TableMenuSkeleton() {
    return (
        <div className="container-page" aria-hidden="true">
            {[0, 1, 2, 3].map((index) => (
                <DishRowSkeleton key={index} />
            ))}
        </div>
    );
}
