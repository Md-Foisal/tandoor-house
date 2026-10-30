import { Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Picture } from '@/components/brand/picture';
import { useT } from '@/lib/i18n';
import { getVideo } from '@/lib/media';

type NetworkInformation = { saveData?: boolean };

function prefersStill(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    const connection = (
        navigator as Navigator & { connection?: NetworkInformation }
    ).connection;

    return (
        window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
        connection?.saveData === true
    );
}

/**
 * Ambient hero film. The poster is the LCP element and paints first; the
 * video loads after, pauses off-screen, and is skipped entirely for reduced
 * motion and Save-Data. A visible control pauses it (WCAG 2.2.2).
 */
export function HeroVideo({ className }: { className?: string }) {
    const t = useT();
    const video = getVideo('hero');
    const ref = useRef<HTMLVideoElement>(null);
    const [enabled, setEnabled] = useState(false);
    const [playing, setPlaying] = useState(true);

    useEffect(() => {
        setEnabled(video !== null && !prefersStill());
    }, [video]);

    useEffect(() => {
        const element = ref.current;

        if (!enabled || element === null) {
            return;
        }

        const observer = new IntersectionObserver(([entry]) => {
            if (entry?.isIntersecting && playing) {
                void element.play().catch(() => setPlaying(false));
            } else {
                element.pause();
            }
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, [enabled, playing]);

    const toggle = () => {
        const element = ref.current;

        if (element === null) {
            return;
        }

        if (playing) {
            element.pause();
        } else {
            void element.play();
        }

        setPlaying(!playing);
    };

    return (
        <div className={className}>
            <div className="absolute inset-0 md:hidden">
                <Picture
                    mediaKey="hero/poster-portrait"
                    priority
                    sizes="100vw"
                />
            </div>
            <div className="absolute inset-0 hidden md:block">
                <Picture mediaKey="hero/poster" priority sizes="100vw" />
            </div>
            {enabled && video && (
                <video
                    ref={ref}
                    className="absolute inset-0 size-full object-cover"
                    autoPlay
                    muted
                    loop
                    playsInline
                    preload="none"
                    aria-hidden="true"
                >
                    <source
                        media="(min-width: 768px)"
                        src={video.video['1080'].webm}
                        type="video/webm"
                    />
                    <source
                        media="(min-width: 768px)"
                        src={video.video['1080'].mp4}
                        type="video/mp4"
                    />
                    <source src={video.video['720'].webm} type="video/webm" />
                    <source src={video.video['720'].mp4} type="video/mp4" />
                </video>
            )}
            {enabled && (
                <button
                    type="button"
                    onClick={toggle}
                    className="absolute right-4 bottom-4 z-10 grid size-11 place-items-center rounded-full bg-ink/60 text-paper outline-hidden hover:bg-ink/75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paper dark:bg-black/60 dark:text-ink"
                    aria-label={
                        playing ? t('ui.video.pause') : t('ui.video.play')
                    }
                >
                    {playing ? (
                        <Pause className="size-4" />
                    ) : (
                        <Play className="size-4" />
                    )}
                </button>
            )}
        </div>
    );
}
