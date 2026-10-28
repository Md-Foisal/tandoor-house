import { Deferred, Head, usePage } from '@inertiajs/react';
import { FindUs } from '@/components/home/find-us';
import { Hero } from '@/components/home/hero';
import { Numbers, NumbersSkeleton } from '@/components/home/numbers';
import { QuickReorder } from '@/components/home/reorder';
import { Reviews, ReviewsSkeleton } from '@/components/home/reviews';
import { Specials } from '@/components/home/specials';
import { StoryTeaser } from '@/components/home/story-teaser';
import type { HomeProps } from '@/components/home/types';
import { useT } from '@/lib/i18n';

export default function Home({
    specials,
    recentOrders,
    hours,
    directionsUrl,
    reviews,
    numbers,
}: HomeProps) {
    const t = useT();
    const { auth } = usePage().props;

    return (
        <>
            <Head title={t('home.meta.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('home.meta.description')}
                />
            </Head>

            <Hero />
            <Specials items={specials} />
            <QuickReorder orders={recentOrders} signedIn={auth.user !== null} />
            <StoryTeaser />
            <Deferred data="numbers" fallback={<NumbersSkeleton />}>
                {numbers && <Numbers numbers={numbers} />}
            </Deferred>
            <Deferred data="reviews" fallback={<ReviewsSkeleton />}>
                {reviews && <Reviews reviews={reviews} />}
            </Deferred>
            <FindUs hours={hours} directionsUrl={directionsUrl} />
        </>
    );
}
