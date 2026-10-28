import { ArchFrame } from '@/components/brand/arch-frame';
import { Picture } from '@/components/brand/picture';
import { SectionHeading } from '@/components/brand/section-heading';
import { PhotoPrint, Serve } from '@/components/motion/served';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    number: string;
    eyebrow: string;
    title: string;
    paragraphs: string[];
    mediaKey?: string;
    caption?: string;
    /** Which side the photograph sits on from md upward. */
    side: 'left' | 'right';
    shape?: 'arch' | 'landscape';
};

/*
 * A new course: heading first, then the photograph develops, then the text.
 * Photo and copy alternate sides and sit at different heights so the page
 * reads like turned menu-book pages rather than a grid.
 */
export function Chapter({
    id,
    number,
    eyebrow,
    title,
    paragraphs,
    mediaKey,
    caption,
    side,
    shape = 'arch',
}: Props) {
    const photoLeft = side === 'left';

    return (
        <section
            aria-labelledby={id}
            className="container-page grid items-start gap-8 pt-section md:grid-cols-12 md:gap-x-8"
        >
            <Serve className="md:col-span-12">
                <div
                    className={cn(
                        'flex items-baseline gap-4',
                        !photoLeft && 'md:justify-end',
                    )}
                >
                    <span
                        aria-hidden="true"
                        className="font-display text-display-md text-ember"
                    >
                        {number}
                    </span>
                    <SectionHeading
                        id={id}
                        eyebrow={eyebrow}
                        title={title}
                        className={cn(
                            'max-w-[22ch]',
                            !photoLeft && 'md:items-end md:text-right',
                        )}
                    />
                </div>
            </Serve>

            {mediaKey && (
                <PhotoPrint
                    className={cn(
                        photoLeft
                            ? 'md:order-1 md:col-span-6'
                            : 'md:order-2 md:col-span-5 md:col-start-8',
                        shape === 'landscape' && 'md:mt-10',
                    )}
                    caption={caption}
                >
                    <ArchFrame
                        ratio={shape === 'arch' ? 'portrait' : 'landscape'}
                        className={shape === 'landscape' ? 'rounded-lg' : ''}
                    >
                        <Picture
                            mediaKey={mediaKey}
                            sizes="(min-width: 768px) 45vw, 100vw"
                            steam={mediaKey.startsWith('dish/')}
                        />
                    </ArchFrame>
                </PhotoPrint>
            )}

            <Serve
                className={cn(
                    'flex flex-col gap-5',
                    mediaKey
                        ? photoLeft
                            ? 'md:order-2 md:col-span-5 md:col-start-8 md:pt-24'
                            : 'md:order-1 md:col-span-6 md:col-start-1 md:pt-12'
                        : 'md:col-span-7 md:col-start-3',
                )}
            >
                {paragraphs.map((text) => (
                    <p
                        key={text}
                        className="max-w-[60ch] text-body-lg text-ink-muted first:text-ink"
                    >
                        {text}
                    </p>
                ))}
            </Serve>
        </section>
    );
}
