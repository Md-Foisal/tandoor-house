import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useRef } from 'react';
import { SectionHeading } from '@/components/brand/section-heading';
import { sectionId } from '@/components/menu-page/category-tabs';
import { DishRow } from '@/components/menu/dish-row';
import { Serve } from '@/components/motion/served';
import { duration, ease, inView, uiTransition } from '@/lib/motion';
import type { Category, MenuItem } from '@/types';

type AddHandler = (item: MenuItem, origin: HTMLElement | null) => void;

function MenuRow({ item, onAdd }: { item: MenuItem; onAdd: AddHandler }) {
    const reduced = useReducedMotion();
    const row = useRef<HTMLLIElement>(null);

    return (
        <motion.li
            ref={row}
            layout={reduced ? false : 'position'}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0, scale: reduced ? 1 : 0.96 }}
            transition={uiTransition}
            className="border-b border-line"
        >
            <DishRow
                item={item}
                href={`/menu/${item.slug}`}
                onAdd={(chosen) =>
                    onAdd(chosen, row.current?.querySelector('button') ?? null)
                }
            />
        </motion.li>
    );
}

/**
 * One course of the menu: the heading arrives first, then its dishes.
 * Dishes that stop matching are cleared away and the rest close up.
 */
export function MenuSection({
    category,
    onAdd,
}: {
    category: Category;
    onAdd: AddHandler;
}) {
    const headingId = `${sectionId(category.slug)}-title`;

    return (
        <section
            id={sectionId(category.slug)}
            data-slug={category.slug}
            tabIndex={-1}
            aria-labelledby={headingId}
            className="scroll-mt-28 py-8 outline-none md:scroll-mt-32"
        >
            <Serve>
                <SectionHeading
                    id={headingId}
                    title={category.name}
                    description={category.description}
                    size="md"
                />
            </Serve>
            <motion.ul
                data-reveal
                initial={{ opacity: 0, y: 16 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={inView}
                transition={{
                    duration: duration.slow,
                    ease: ease.out,
                    delay: 0.12,
                }}
                className="mt-2 lg:grid lg:grid-cols-2 lg:gap-x-12"
            >
                <AnimatePresence initial={false} mode="popLayout">
                    {category.items.map((item) => (
                        <MenuRow key={item.id} item={item} onAdd={onAdd} />
                    ))}
                </AnimatePresence>
            </motion.ul>
        </section>
    );
}
