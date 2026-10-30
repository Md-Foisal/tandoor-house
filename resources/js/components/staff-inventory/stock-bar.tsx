import { cn } from '@/lib/utils';
import type { IngredientRow } from './types';
import { formatQuantity } from './units';

/** Stock against par level; the colour follows the low-stock rule. */
export function StockBar({ ingredient }: { ingredient: IngredientRow }) {
    const share = Math.max(
        0,
        Math.min(100, (ingredient.stock / ingredient.par_level) * 100),
    );
    const tone = ingredient.is_low
        ? 'bg-chilli'
        : ingredient.stock <= ingredient.low_threshold * 1.5
          ? 'bg-turmeric'
          : 'bg-leaf';

    return (
        <div
            role="meter"
            aria-valuemin={0}
            aria-valuemax={ingredient.par_level}
            aria-valuenow={Math.min(ingredient.stock, ingredient.par_level)}
            aria-valuetext={`${formatQuantity(ingredient.stock, ingredient.unit)} of ${formatQuantity(ingredient.par_level, ingredient.unit)}`}
            className="h-2 w-full overflow-hidden rounded-full bg-clay"
        >
            <div
                className={cn('h-full rounded-full', tone)}
                style={{ width: `${share}%` }}
            />
        </div>
    );
}
