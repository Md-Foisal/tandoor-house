import { Link, usePage } from '@inertiajs/react';
import { HeroVideo } from '@/components/brand/hero-video';
import { OpenStatus } from '@/components/brand/open-status';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

/*
 * Static on purpose: the poster is the LCP element and nothing here
 * animates before it has painted. The scrim is the one allowed gradient.
 */
export function Hero() {
    const t = useT();
    const { openStatus } = usePage().props;

    return (
        <section
            aria-labelledby="hero-title"
            className="relative isolate flex min-h-[calc(100svh-7.5rem)] items-end overflow-hidden bg-clay text-paper md:min-h-[min(78svh,50rem)] dark:text-ink"
        >
            <HeroVideo className="absolute inset-0 -z-20" />
            <div
                aria-hidden="true"
                className="absolute inset-0 -z-10 bg-linear-to-t from-ink/80 via-ink/35 to-ink/0 md:bg-linear-to-r md:from-ink/75 md:via-ink/35 md:to-ink/0 dark:from-black/80 dark:via-black/40 dark:to-black/0"
            />
            <div className="container-page w-full pt-24 pb-20 md:pb-16">
                <div className="flex max-w-[46rem] flex-col items-start gap-5">
                    <OpenStatus
                        status={openStatus}
                        className="rounded-full bg-paper/90 px-3 py-1.5 text-ink"
                    />
                    <h1 className="font-display text-display-2xl text-balance">
                        {t('home.hero.title')}
                    </h1>
                    <p className="max-w-[44ch] text-body-lg">
                        {t('home.hero.lede')}
                    </p>
                    <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                        <Button asChild size="lg" className="sm:min-w-40">
                            <Link href="/menu" prefetch>
                                {t('home.hero.order')}
                            </Link>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="border-paper bg-transparent text-paper hover:bg-paper/15 hover:text-paper sm:min-w-40 dark:border-ink dark:text-ink dark:hover:bg-ink/15 dark:hover:text-ink"
                        >
                            <Link href="/book" prefetch>
                                {t('home.hero.book')}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </section>
    );
}
