import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Home,
    Menu,
    ShoppingBag,
    User,
    UtensilsCrossed,
} from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { Logo } from '@/components/brand/logo';
import { DemoBanner, OfflineBanner } from '@/components/brand/states';
import { ThemeToggle } from '@/components/brand/theme-toggle';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type NavItem = { href: string; label: string; match: (url: string) => boolean };

// Under 1024px the header fits this many links; the rest move to the menu.
const TABLET_LINKS = 2;

function isCurrent(url: string, href: string): boolean {
    const path = url.split('?')[0] ?? url;

    return href === '/'
        ? path === '/'
        : path === href || path.startsWith(`${href}/`);
}

export function BasketBadge({
    count,
    className,
}: {
    count: number;
    className?: string;
}) {
    const reduced = useReducedMotion();

    if (count === 0) {
        return null;
    }

    return (
        <motion.span
            key={count}
            data-basket-badge
            initial={reduced ? false : { scale: 1 }}
            animate={reduced ? undefined : { scale: [1, 1.15, 1] }}
            transition={{ duration: 0.25 }}
            className={cn(
                'absolute -top-1 -right-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-ember px-1 text-[0.6875rem] font-bold text-ember-ink tabular-nums',
                className,
            )}
            aria-hidden="true"
        >
            {count}
        </motion.span>
    );
}

/**
 * The pages and settings the phone tab bar and tablet header have no room
 * for. The sheet closes on a link tap because the layout stays mounted.
 */
function MoreMenu({
    links,
    className,
}: {
    links: NavItem[];
    className?: string;
}) {
    const t = useT();
    const { url } = usePage();
    const extras = [{ href: '/allergens', label: t('nav.footer.allergens') }];

    return (
        <Sheet>
            <SheetTrigger
                aria-label={t('nav.more.open')}
                className={cn(
                    'grid size-11 place-items-center rounded-full text-ink outline-hidden hover:bg-clay focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid',
                    className,
                )}
            >
                <Menu className="size-6" aria-hidden="true" />
            </SheetTrigger>
            <SheetContent side="right" className="gap-6 p-5">
                <SheetTitle className="pr-12 font-display text-2xl font-normal">
                    {t('nav.more.title')}
                </SheetTitle>
                <SheetDescription className="sr-only">
                    {t('nav.more.description')}
                </SheetDescription>
                <nav aria-label={t('nav.more.title')}>
                    <ul className="flex flex-col">
                        {[...links, ...extras].map(({ href, label }) => (
                            <li key={href}>
                                <SheetClose asChild>
                                    <Link
                                        href={href}
                                        aria-current={
                                            isCurrent(url, href)
                                                ? 'page'
                                                : undefined
                                        }
                                        className="flex min-h-12 items-center rounded-md px-2 font-semibold outline-hidden hover:bg-clay focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid aria-[current=page]:text-ember"
                                    >
                                        {label}
                                    </Link>
                                </SheetClose>
                            </li>
                        ))}
                    </ul>
                </nav>
                <div className="flex flex-col gap-2 border-t border-line pt-5">
                    <p className="text-label text-ink-muted">
                        {t('ui.theme.label')}
                    </p>
                    <ThemeToggle className="self-start" />
                </div>
            </SheetContent>
        </Sheet>
    );
}

function DesktopHeader({ links }: { links: NavItem[] }) {
    const t = useT();
    const { name, basketCount, auth } = usePage().props;
    const { url } = usePage();

    return (
        <header className="sticky top-0 z-40 hidden border-b border-line bg-paper/95 backdrop-blur-sm md:block">
            <div className="container-page flex h-[4.5rem] items-center gap-8">
                <Link
                    href="/"
                    className="inline-flex min-h-11 shrink-0 items-center rounded-sm whitespace-nowrap outline-hidden focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid"
                >
                    <Logo name={name} />
                </Link>
                <nav
                    aria-label={t('nav.main')}
                    className="flex flex-1 items-center gap-1"
                >
                    {links.map((link, index) => {
                        const current = link.match(url);

                        return (
                            <Link
                                key={link.href}
                                href={link.href}
                                prefetch
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'relative rounded-md px-3 py-2.5 font-semibold whitespace-nowrap text-ink-muted outline-hidden transition-colors duration-150 hover:text-ink focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid',
                                    current && 'text-ink',
                                    index >= TABLET_LINKS && 'hidden lg:block',
                                )}
                            >
                                {link.label}
                                {current && (
                                    <svg
                                        viewBox="0 0 60 6"
                                        preserveAspectRatio="none"
                                        aria-hidden="true"
                                        className="absolute right-3 -bottom-0.5 left-3 h-1.5 text-ember"
                                    >
                                        <path
                                            d="M1 4C14 2 26 1.6 38 2.4c8 .5 14 1 21 .4"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.4"
                                            strokeLinecap="round"
                                        />
                                    </svg>
                                )}
                            </Link>
                        );
                    })}
                </nav>
                <div className="flex items-center gap-2">
                    <ThemeToggle className="hidden lg:inline-flex" />
                    <Link
                        href={auth.user ? '/account' : '/login'}
                        className="grid size-11 place-items-center rounded-full text-ink outline-hidden hover:bg-clay focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid"
                        aria-label={
                            auth.user ? t('nav.account') : t('nav.sign_in')
                        }
                    >
                        <User className="size-5" aria-hidden="true" />
                    </Link>
                    <Link
                        href="/basket"
                        className="relative grid size-11 place-items-center rounded-full text-ink outline-hidden hover:bg-clay focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid"
                        aria-label={t('nav.basket_count', {
                            count: basketCount,
                        })}
                    >
                        <ShoppingBag className="size-5" aria-hidden="true" />
                        <BasketBadge
                            count={basketCount}
                            className="top-0.5 right-0"
                        />
                    </Link>
                    <Button asChild className="ml-2">
                        <Link href="/menu">{t('nav.order_now')}</Link>
                    </Button>
                    <MoreMenu links={links} className="lg:hidden" />
                </div>
            </div>
        </header>
    );
}

function MobileHeader({ links }: { links: NavItem[] }) {
    const { name } = usePage().props;

    return (
        <header className="sticky top-0 z-40 border-b border-line bg-paper/95 backdrop-blur-sm md:hidden">
            <div className="flex h-14 items-center justify-between px-4">
                <Link
                    href="/"
                    className="inline-flex min-h-11 items-center rounded-sm outline-hidden focus-visible:outline-2 focus-visible:outline-ember focus-visible:outline-solid"
                >
                    <Logo
                        name={name}
                        className="[&_svg]:size-7 [&>span:last-child]:text-xl"
                    />
                </Link>
                <MoreMenu links={links} />
            </div>
        </header>
    );
}

function MobileTabBar() {
    const t = useT();
    const { basketCount, auth } = usePage().props;
    const { url } = usePage();

    const tabs = [
        { href: '/', label: t('nav.home'), icon: Home },
        { href: '/menu', label: t('nav.menu'), icon: UtensilsCrossed },
        { href: '/book', label: t('nav.book'), icon: CalendarDays },
        {
            href: '/basket',
            label: t('nav.basket'),
            icon: ShoppingBag,
            badge: basketCount,
        },
        {
            href: auth.user ? '/account' : '/login',
            label: t('nav.account'),
            icon: User,
        },
    ];

    return (
        <nav
            aria-label={t('nav.main')}
            className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/97 pb-[env(safe-area-inset-bottom)] backdrop-blur-sm md:hidden"
        >
            <ul className="grid grid-cols-5">
                {tabs.map(({ href, label, icon: Icon, badge }) => {
                    const current = isCurrent(url, href);

                    return (
                        <li key={label}>
                            <Link
                                href={href}
                                prefetch
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'flex h-16 flex-col items-center justify-center gap-1 text-[0.6875rem] font-semibold text-ink-muted outline-hidden focus-visible:bg-clay',
                                    current && 'text-ember',
                                )}
                            >
                                <span className="relative">
                                    <Icon
                                        className="size-[1.375rem]"
                                        strokeWidth={current ? 2.25 : 1.75}
                                        aria-hidden="true"
                                    />
                                    {badge !== undefined && (
                                        <BasketBadge count={badge} />
                                    )}
                                </span>
                                {label}
                                {badge !== undefined && badge > 0 && (
                                    <span className="sr-only">
                                        {t('nav.basket_count', {
                                            count: badge,
                                        })}
                                    </span>
                                )}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

function Footer() {
    const t = useT();
    const { name, restaurant, footer } = usePage().props;

    return (
        <footer className="mt-section border-t border-line bg-surface pb-24 md:pb-0">
            <div className="container-page grid gap-10 py-14 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div className="flex flex-col gap-3">
                    <Logo name={name} />
                    <p className="max-w-[28ch] text-ink-muted">
                        {t('nav.footer.tagline')}
                    </p>
                    <p className="mt-2 inline-flex w-fit items-center gap-2 rounded-sm border border-line px-2.5 py-1.5 text-sm font-semibold">
                        {typeof footer.hygieneRating === 'number'
                            ? t('nav.footer.hygiene', {
                                  rating: footer.hygieneRating,
                                  label: t(
                                      `nav.footer.hygiene_labels.${footer.hygieneRating}`,
                                  ),
                              })
                            : t('nav.footer.hygiene_awaiting')}
                    </p>
                </div>
                <div>
                    <h2 className="text-label text-ink-muted">
                        {t('nav.footer.hours')}
                    </h2>
                    <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        {footer.hours.map((day) => (
                            <div key={day.day} className="contents">
                                <dt className="font-semibold">
                                    {day.day.slice(0, 3)}
                                </dt>
                                <dd className="text-ink-muted tabular-nums">
                                    {day.periods.length > 0
                                        ? day.periods.join(', ')
                                        : t('nav.footer.closed')}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
                <div>
                    <h2 className="text-label text-ink-muted">
                        {t('nav.footer.contact')}
                    </h2>
                    <address className="mt-3 flex flex-col text-sm not-italic">
                        <span>{restaurant.address.line1}</span>
                        <span>
                            {restaurant.address.city}{' '}
                            {restaurant.address.postcode}
                        </span>
                        <a
                            href={`tel:${restaurant.phone.replaceAll(' ', '')}`}
                            className="inline-flex min-h-11 items-center self-start font-semibold underline-offset-4 hover:underline"
                        >
                            {restaurant.phone}
                        </a>
                        <a
                            href={`mailto:${restaurant.email}`}
                            className="inline-flex min-h-11 items-center self-start font-semibold underline-offset-4 hover:underline"
                        >
                            {restaurant.email}
                        </a>
                    </address>
                </div>
                <div>
                    <h2 className="text-label text-ink-muted">
                        {t('nav.footer.legal')}
                    </h2>
                    <ul className="mt-3 flex flex-col text-sm">
                        <li>
                            <Link
                                href="/allergens"
                                className="inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                            >
                                {t('nav.footer.allergens')}
                            </Link>
                        </li>
                        <li>
                            <Link
                                href="/privacy"
                                className="inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                            >
                                {t('nav.footer.privacy')}
                            </Link>
                        </li>
                        <li>
                            <Link
                                href="/cookies"
                                className="inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                            >
                                {t('nav.footer.cookies')}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
            <p className="container-page border-t border-line py-5 text-xs text-ink-muted">
                {t('nav.footer.company', {
                    legal: restaurant.company.legal_name,
                    number: restaurant.company.company_number,
                    vat: restaurant.company.vat_number,
                })}
            </p>
        </footer>
    );
}

/**
 * Customer-facing shell: top navigation from 768px, a thumb-reachable tab
 * bar below it. Pages that need a sticky action bar pad themselves above it.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const t = useT();

    const links: NavItem[] = [
        {
            href: '/menu',
            label: t('nav.menu'),
            match: (url) => isCurrent(url, '/menu'),
        },
        {
            href: '/book',
            label: t('nav.book_long'),
            match: (url) => isCurrent(url, '/book'),
        },
        {
            href: '/story',
            label: t('nav.story'),
            match: (url) => isCurrent(url, '/story'),
        },
        {
            href: '/visit',
            label: t('nav.visit'),
            match: (url) => isCurrent(url, '/visit'),
        },
    ];

    return (
        <div className="flex min-h-dvh flex-col bg-paper">
            <a
                href="#main"
                className="sr-only z-50 min-h-11 min-w-11 items-center rounded-md bg-ink px-4 py-3 font-semibold text-paper focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:inline-flex"
            >
                {t('ui.skip_to_content')}
            </a>
            <DemoBanner />
            <OfflineBanner />
            <DesktopHeader links={links} />
            <MobileHeader links={links} />
            <main id="main" className="flex-1">
                {children}
            </main>
            <Footer />
            <MobileTabBar />
        </div>
    );
}
