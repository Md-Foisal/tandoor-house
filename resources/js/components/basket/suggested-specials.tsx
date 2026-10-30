import { Plus } from 'lucide-react';
import { AllergenList } from '@/components/brand/allergen-list';
import { ArchFrame } from '@/components/brand/arch-frame';
import { Picture } from '@/components/brand/picture';
import { Price } from '@/components/brand/price';
import { Skeleton } from '@/components/ui/skeleton';
import { useAddToBasket } from '@/lib/basket';
import { useT } from '@/lib/i18n';
import type { MenuItem } from '@/types';

export function SuggestedSpecials({ items }: { items: MenuItem[] }) {
    const t = useT();
    const { add, pending } = useAddToBasket();

    if (items.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="basket-specials" className="w-full max-w-xl">
            <h2
                id="basket-specials"
                className="text-left text-label text-turmeric"
            >
                {t('basket.specials_heading')}
            </h2>
            <ul className="mt-2 divide-y divide-line text-left">
                {items.map((item) => (
                    <li key={item.id} className="flex items-center gap-3 py-3">
                        <ArchFrame
                            ratio="portrait"
                            className="h-16 w-12 shrink-0"
                        >
                            <Picture
                                mediaKey={item.image}
                                alt=""
                                sizes="48px"
                            />
                        </ArchFrame>
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold text-ink">
                                {item.name}
                            </p>
                            <Price
                                pence={item.price}
                                className="text-sm text-ink-muted"
                            />
                            <AllergenList allergens={item.allergens} compact />
                        </div>
                        <button
                            type="button"
                            disabled={pending === item.id}
                            onClick={(event) =>
                                add(
                                    { menu_item_id: item.id },
                                    event.currentTarget,
                                )
                            }
                            aria-label={t('basket.quick_add', {
                                item: item.name,
                            })}
                            className="grid size-11 shrink-0 place-items-center rounded-full bg-ember text-ember-ink outline-hidden transition-transform duration-150 hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember active:scale-95 disabled:opacity-45"
                        >
                            <Plus
                                className="size-5"
                                strokeWidth={2.5}
                                aria-hidden="true"
                            />
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export function SuggestedSpecialsSkeleton() {
    return (
        <div className="w-full max-w-xl" aria-hidden="true">
            {[0, 1, 2].map((row) => (
                <div key={row} className="flex items-center gap-3 py-3">
                    <Skeleton className="h-16 w-12 shrink-0 rounded-arch" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-5 w-1/2" />
                        <Skeleton className="h-4 w-1/4" />
                    </div>
                    <Skeleton className="size-11 rounded-full" />
                </div>
            ))}
        </div>
    );
}
