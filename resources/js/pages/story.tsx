import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ArchFrame } from '@/components/brand/arch-frame';
import { Picture } from '@/components/brand/picture';
import { SectionHeading } from '@/components/brand/section-heading';
import { Serve, PhotoPrint } from '@/components/motion/served';
import { Chapter } from '@/components/pages/chapter';
import { RoomGallery } from '@/components/pages/room-gallery';
import { Signature } from '@/components/pages/signature';
import { StoryFigures } from '@/components/pages/story-numbers';
import type { StoryProps } from '@/components/pages/types';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

const chapters = [
    { key: 'one', media: 'story/hands', side: 'left', shape: 'landscape' },
    { key: 'two', media: 'story/tandoor', side: 'right', shape: 'arch' },
    {
        key: 'three',
        media: 'dish/house-black-daal',
        side: 'left',
        shape: 'arch',
    },
    { key: 'four', media: undefined, side: 'right', shape: 'arch' },
] as const;

export default function Story({ numbers }: StoryProps) {
    const t = useT();

    return (
        <>
            <Head title={t('pages.story.meta.title')}>
                <meta
                    head-key="description"
                    name="description"
                    content={t('pages.story.meta.description')}
                />
            </Head>

            <header className="container-page grid items-end gap-10 pt-10 md:grid-cols-12 md:gap-x-8 md:pt-16">
                <Serve className="flex flex-col gap-6 md:col-span-7">
                    <SectionHeading
                        as="h1"
                        size="xl"
                        eyebrow={t('pages.story.eyebrow')}
                        title={t('pages.story.title')}
                    />
                    <p className="max-w-[56ch] text-body-lg text-ink-muted">
                        {t('pages.story.intro')}
                    </p>
                </Serve>
                <PhotoPrint
                    className="md:col-span-4 md:col-start-9"
                    caption={t('pages.story.hero_caption')}
                >
                    <ArchFrame ratio="portrait">
                        <Picture
                            mediaKey="story/tandoor"
                            priority
                            sizes="(min-width: 768px) 30vw, 100vw"
                        />
                    </ArchFrame>
                </PhotoPrint>
            </header>

            {chapters.map((chapter, index) => (
                <div key={chapter.key}>
                    <Chapter
                        id={`chapter-${chapter.key}`}
                        number={String(index + 1).padStart(2, '0')}
                        eyebrow={t('pages.story.chapter_label', {
                            number: index + 1,
                        })}
                        title={t(`pages.story.chapters.${chapter.key}.title`)}
                        paragraphs={[
                            t(`pages.story.chapters.${chapter.key}.body_1`),
                            t(`pages.story.chapters.${chapter.key}.body_2`),
                        ]}
                        mediaKey={chapter.media}
                        caption={
                            chapter.media
                                ? t(
                                      `pages.story.chapters.${chapter.key}.caption`,
                                  )
                                : undefined
                        }
                        side={chapter.side}
                        shape={chapter.shape}
                    />
                    {index === 1 && (
                        <figure className="container-page pt-section">
                            <Serve>
                                <blockquote className="mx-auto max-w-[26ch] border-l-4 border-ember pl-6 font-display text-display-lg text-ink md:max-w-[34ch]">
                                    {t('pages.story.quote.text')}
                                </blockquote>
                                <figcaption className="mx-auto mt-4 max-w-[34ch] pl-7 text-label text-ink-muted">
                                    {t('pages.story.quote.by')}
                                </figcaption>
                            </Serve>
                        </figure>
                    )}
                </div>
            ))}

            <StoryFigures numbers={numbers} />
            <RoomGallery />

            <section
                aria-label={t('pages.story.signature.label')}
                className="container-page flex flex-col gap-3 pt-section md:items-end"
            >
                <Serve className="flex flex-col gap-2">
                    <Signature />
                    <p className="text-ink-muted">
                        {t('pages.story.signature.note')}
                    </p>
                </Serve>
            </section>

            <section
                aria-labelledby="story-cta"
                className="container-page pt-section"
            >
                <Serve className="flex flex-col gap-6 rounded-lg bg-clay p-6 md:flex-row md:items-center md:justify-between md:p-10">
                    <div className="flex flex-col gap-2">
                        <h2
                            id="story-cta"
                            className="font-display text-display-md text-ink"
                        >
                            {t('pages.story.cta.title')}
                        </h2>
                        <p className="max-w-[44ch] text-ink-muted">
                            {t('pages.story.cta.body')}
                        </p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button asChild size="lg">
                            <Link href="/book" prefetch>
                                {t('pages.story.cta.book')}
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <Link href="/menu" prefetch>
                                {t('pages.story.cta.menu')}
                                <ArrowRight aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                </Serve>
            </section>
        </>
    );
}
