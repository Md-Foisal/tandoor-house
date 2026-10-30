import { Link } from '@inertiajs/react';
import { motion } from 'motion/react';
import { ArchFrame } from '@/components/brand/arch-frame';
import { DietMarks, SpiceMeter } from '@/components/brand/diet';
import { Picture } from '@/components/brand/picture';
import { Price } from '@/components/brand/price';
import { SteamWisp } from '@/components/motion/served';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { MenuItem } from '@/types';

/**
 * Photo-led card for specials and features. The arch photo carries a
 * layoutId so it can grow into the dish page hero.
 */
export function DishCard({
    item,
    href,
    className,
}: {
    item: MenuItem;
    href: string;
    className?: string;
}) {
    const t = useT();

    return (
        <article
            className={cn('group relative flex flex-col gap-3', className)}
        >
            <motion.div
                layoutId={`dish-photo-${item.slug}`}
                className="relative"
            >
                <ArchFrame ratio="portrait">
                    <Picture
                        mediaKey={item.image}
                        alt=""
                        sizes="(min-width: 1024px) 22rem, (min-width: 640px) 45vw, 80vw"
                        steam
                        className="transition-transform duration-500 ease-out group-hover:scale-[1.03]"
                    />
                </ArchFrame>
                {item.is_hot && (
                    <SteamWisp className="absolute top-6 left-1/2 -translate-x-1/2" />
                )}
                {item.is_special && (
                    <span className="absolute top-[18%] left-3 rounded-sm bg-turmeric-soft px-2 py-1 text-label text-turmeric">
                        {t('ui.special')}
                    </span>
                )}
            </motion.div>
            <div className="flex items-start justify-between gap-3">
                <h3 className="font-display text-display-md text-ink">
                    <Link
                        href={href}
                        prefetch
                        className="outline-hidden after:absolute after:inset-0 after:content-[''] focus-visible:underline focus-visible:decoration-ember focus-visible:underline-offset-4"
                    >
                        {item.name}
                    </Link>
                </h3>
                <Price pence={item.price} className="mt-1.5 text-lg" />
            </div>
            <p className="line-clamp-2 text-ink-muted">{item.description}</p>
            <div className="flex items-center gap-3">
                <span className="text-sm text-ink-muted tabular-nums">
                    {t('ui.kcal', { kcal: item.kcal })}
                </span>
                <DietMarks
                    vegetarian={item.is_vegetarian}
                    vegan={item.is_vegan}
                />
                <SpiceMeter level={item.spice_level} />
            </div>
        </article>
    );
}

export function DishCardSkeleton() {
    return (
        <div className="flex flex-col gap-3" aria-hidden="true">
            <Skeleton className="aspect-[4/5] w-full rounded-arch" />
            <Skeleton className="h-8 w-3/4" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-1/2" />
        </div>
    );
}
