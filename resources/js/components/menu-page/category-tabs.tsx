import { motion, useReducedMotion } from 'motion/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { EmberLine } from '@/components/brand/ember-line';
import { Skeleton } from '@/components/ui/skeleton';
import { useT } from '@/lib/i18n';
import { uiTransition } from '@/lib/motion';
import { cn } from '@/lib/utils';

export const sectionId = (slug: string) => `course-${slug}`;

type Tab = { slug: string; name: string };

/**
 * Highlights the section that is currently under the sticky bars. A tap on
 * a tab sets the highlight straight away and holds it while the page glides.
 */
function useScrollSpy(slugs: string[]) {
    const [active, setActive] = useState<string | null>(slugs[0] ?? null);
    const holdUntil = useRef(0);
    const key = slugs.join('|');

    useEffect(() => {
        const sections = slugs
            .map((slug) => document.getElementById(sectionId(slug)))
            .filter((element): element is HTMLElement => element !== null);

        if (sections.length === 0 || !('IntersectionObserver' in window)) {
            return;
        }

        const visible = new Map<string, number>();
        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    const slug = entry.target.getAttribute('data-slug') ?? '';

                    if (entry.isIntersecting) {
                        visible.set(slug, entry.boundingClientRect.top);
                    } else {
                        visible.delete(slug);
                    }
                }

                if (performance.now() < holdUntil.current) {
                    return;
                }

                const first = slugs.find((slug) => visible.has(slug));

                if (first) {
                    setActive(first);
                }
            },
            { rootMargin: '-140px 0px -55% 0px', threshold: 0 },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    const choose = useCallback((slug: string) => {
        holdUntil.current = performance.now() + 900;
        setActive(slug);
    }, []);

    return { active: active ?? slugs[0] ?? null, choose };
}

export function CategoryTabs({ tabs }: { tabs: Tab[] }) {
    const t = useT();
    const reduced = useReducedMotion();
    const list = useRef<HTMLUListElement>(null);
    const { active, choose } = useScrollSpy(tabs.map((tab) => tab.slug));

    useEffect(() => {
        const current = list.current?.querySelector<HTMLElement>(
            '[aria-current="location"]',
        );
        const container = list.current;

        if (!current || !container) {
            return;
        }

        const target =
            current.offsetLeft -
            container.clientWidth / 2 +
            current.clientWidth / 2;

        container.scrollTo({
            left: target,
            behavior: reduced ? 'auto' : 'smooth',
        });
    }, [active, reduced]);

    const go = (event: React.MouseEvent<HTMLAnchorElement>, slug: string) => {
        const section = document.getElementById(sectionId(slug));

        if (!section) {
            return;
        }

        event.preventDefault();
        choose(slug);
        section.scrollIntoView({
            behavior: reduced ? 'auto' : 'smooth',
            block: 'start',
        });
        section.focus({ preventScroll: true });
    };

    return (
        <nav
            aria-label={t('menu.categories')}
            className="sticky top-[calc(3.5rem+1px)] z-30 border-b border-line bg-paper/95 backdrop-blur-sm md:top-[calc(4.5rem+1px)]"
        >
            <ul
                ref={list}
                className="container-page flex [scrollbar-width:none] gap-1 overflow-x-auto overscroll-x-contain [&::-webkit-scrollbar]:hidden"
            >
                {tabs.map((tab) => {
                    const current = tab.slug === active;

                    return (
                        <li key={tab.slug} className="shrink-0">
                            <a
                                href={`#${sectionId(tab.slug)}`}
                                onClick={(event) => go(event, tab.slug)}
                                aria-current={current ? 'location' : undefined}
                                className={cn(
                                    'relative flex min-h-12 items-center px-3 text-base font-semibold whitespace-nowrap outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ember',
                                    current
                                        ? 'text-ink'
                                        : 'text-ink-muted hover:text-ink',
                                )}
                            >
                                {tab.name}
                                {current && (
                                    <motion.span
                                        layoutId="menu-tab-line"
                                        transition={uiTransition}
                                        className="absolute inset-x-3 bottom-1"
                                    >
                                        <EmberLine className="h-1.5 w-full" />
                                    </motion.span>
                                )}
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

export function CategoryTabsSkeleton() {
    return (
        <div
            aria-hidden="true"
            className="sticky top-[calc(3.5rem+1px)] z-30 border-b border-line bg-paper md:top-[calc(4.5rem+1px)]"
        >
            <div className="container-page flex gap-3 overflow-hidden py-3">
                {[88, 72, 96, 64, 80].map((width) => (
                    <Skeleton
                        key={width}
                        className="h-6 shrink-0 rounded-full"
                        style={{ width }}
                    />
                ))}
            </div>
        </div>
    );
}
