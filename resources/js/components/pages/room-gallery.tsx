import { Picture } from '@/components/brand/picture';
import { SectionHeading } from '@/components/brand/section-heading';
import { DoorReveal, Serve } from '@/components/motion/served';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const frames = [
    { key: 'room', ratio: 'aspect-[4/5]', span: 'md:col-span-5 md:row-span-2' },
    { key: 'table', ratio: 'aspect-[3/2]', span: 'md:col-span-4' },
    { key: 'pass', ratio: 'aspect-[3/2]', span: 'md:col-span-3 md:mt-10' },
    { key: 'chai', ratio: 'aspect-square', span: 'md:col-span-3' },
    { key: 'spices', ratio: 'aspect-[4/5]', span: 'md:col-span-4 md:-mt-6' },
    {
        key: 'naan',
        ratio: 'aspect-[3/2]',
        span: 'md:col-span-5 md:col-start-4',
    },
] as const;

export function RoomGallery() {
    const t = useT();

    return (
        <section
            aria-labelledby="room-title"
            className="container-page flex flex-col gap-10 pt-section"
        >
            <Serve>
                <SectionHeading
                    id="room-title"
                    eyebrow={t('pages.story.room.eyebrow')}
                    title={t('pages.story.room.title')}
                    description={t('pages.story.room.description')}
                />
            </Serve>
            <ul className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-12 md:gap-x-6 md:gap-y-8">
                {frames.map((frame) => (
                    <li key={frame.key} className={cn('flex', frame.span)}>
                        <figure className="flex w-full flex-col gap-2">
                            <DoorReveal>
                                <div
                                    className={cn(
                                        'relative isolate overflow-hidden rounded-lg bg-clay',
                                        frame.ratio,
                                    )}
                                >
                                    <Picture
                                        mediaKey={`story/${frame.key}`}
                                        alt={t(
                                            `pages.story.room.frames.${frame.key}`,
                                        )}
                                        sizes="(min-width: 768px) 40vw, 100vw"
                                    />
                                </div>
                            </DoorReveal>
                            <figcaption className="text-sm text-ink-muted italic">
                                {t(`pages.story.room.frames.${frame.key}`)}
                            </figcaption>
                        </figure>
                    </li>
                ))}
            </ul>
        </section>
    );
}
