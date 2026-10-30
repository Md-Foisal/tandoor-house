import { motion, useReducedMotion } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import { LogoMark } from '@/components/brand/logo';
import { resolveImage, srcSet } from '@/lib/media';
import type { MenuImage } from '@/lib/media';
import { cn } from '@/lib/utils';

type Props = {
    /** Manifest key, or an uploaded photo object, which is used as-is. */
    mediaKey: MenuImage;
    alt?: string;
    sizes?: string;
    priority?: boolean;
    /** Food photos start soft and warm, then clear as they land. */
    steam?: boolean;
    className?: string;
};

/**
 * Responsive AVIF/WebP picture from the media manifest, sized by its parent
 * (which fixes the aspect ratio, so nothing shifts). Missing media renders
 * a branded placeholder rather than a broken image.
 */
export function Picture({
    mediaKey,
    alt,
    sizes = '100vw',
    priority = false,
    steam = false,
    className,
}: Props) {
    const image = resolveImage(mediaKey);
    const reduced = useReducedMotion();
    const [loaded, setLoaded] = useState(priority);
    const ref = useRef<HTMLImageElement>(null);

    // A cached image can finish loading before React attaches onLoad.
    useEffect(() => {
        if (ref.current?.complete && ref.current.naturalWidth > 0) {
            setLoaded(true);
        }
    }, []);

    if (image === null) {
        return <PicturePlaceholder className={className} />;
    }

    const largest = image.sources.webp[image.sources.webp.length - 1];
    const avif = image.sources.avif;
    const clearing = steam && !reduced && !priority;

    return (
        <picture
            className={cn('absolute inset-0 block', className)}
            style={{
                backgroundImage: `url(${image.placeholder})`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
            }}
        >
            {avif.length > 0 && (
                <source
                    type="image/avif"
                    srcSet={srcSet(avif)}
                    sizes={sizes}
                />
            )}
            <source
                type="image/webp"
                srcSet={srcSet(image.sources.webp)}
                sizes={sizes}
            />
            <motion.img
                ref={ref}
                src={largest?.[1]}
                alt={alt ?? image.alt}
                width={image.width}
                height={image.height}
                loading={priority ? 'eager' : 'lazy'}
                decoding={priority ? 'sync' : 'async'}
                fetchPriority={priority ? 'high' : 'auto'}
                onLoad={() => setLoaded(true)}
                className="size-full object-cover"
                initial={
                    clearing
                        ? {
                              opacity: 0,
                              filter: 'blur(6px) brightness(0.9) sepia(0.15)',
                          }
                        : false
                }
                animate={
                    clearing
                        ? loaded
                            ? {
                                  opacity: 1,
                                  filter: 'blur(0px) brightness(1) sepia(0)',
                              }
                            : undefined
                        : { opacity: loaded ? 1 : 0 }
                }
                transition={{
                    duration: clearing ? 0.5 : 0.15,
                    ease: [0.22, 1, 0.36, 1],
                }}
            />
        </picture>
    );
}

export function PicturePlaceholder({ className }: { className?: string }) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'absolute inset-0 grid place-items-center bg-clay text-line-strong',
                className,
            )}
        >
            <LogoMark className="size-12 opacity-60" />
        </div>
    );
}
