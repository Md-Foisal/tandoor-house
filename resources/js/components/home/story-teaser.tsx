import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ArchFrame } from '@/components/brand/arch-frame';
import { Picture } from '@/components/brand/picture';
import { SectionHeading } from '@/components/brand/section-heading';
import { PhotoPrint, Serve } from '@/components/motion/served';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

/*
 * Off-grid rhythm: a tall arch on the left, copy that sits lower, and a
 * small landscape print tucked under the copy on the right.
 */
export function StoryTeaser() {
    const t = useT();

    return (
        <section
            aria-labelledby="story-title"
            className="container-page grid items-start gap-10 pt-section md:grid-cols-12 md:gap-x-8"
        >
            <PhotoPrint
                className="md:col-span-5"
                caption={t('home.story.caption_oven')}
            >
                <ArchFrame ratio="portrait">
                    <Picture
                        mediaKey="story/tandoor"
                        sizes="(min-width: 768px) 40vw, 100vw"
                    />
                </ArchFrame>
            </PhotoPrint>

            <div className="flex flex-col gap-8 md:col-span-6 md:col-start-7 md:pt-24">
                <Serve className="flex flex-col gap-5">
                    <SectionHeading
                        id="story-title"
                        eyebrow={t('home.story.eyebrow')}
                        title={t('home.story.title')}
                    />
                    <p className="max-w-[60ch] text-body-lg text-ink-muted">
                        {t('home.story.body')}
                    </p>
                    <Button asChild variant="link" className="self-start">
                        <Link href="/story" prefetch>
                            {t('home.story.link')}
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </Serve>
                <PhotoPrint
                    className="w-3/4 self-end"
                    caption={t('home.story.caption_hands')}
                >
                    <ArchFrame ratio="landscape" className="rounded-lg">
                        <Picture
                            mediaKey="story/hands"
                            sizes="(min-width: 768px) 30vw, 75vw"
                        />
                    </ArchFrame>
                </PhotoPrint>
            </div>
        </section>
    );
}
