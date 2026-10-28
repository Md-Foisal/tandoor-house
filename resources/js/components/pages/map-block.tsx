import { MapPin } from 'lucide-react';
import { MarkerDrop } from '@/components/motion/served';
import { useT } from '@/lib/i18n';

/* A drawn street plan, not an embed: no third-party requests, no tracking. */
export function MapBlock({ address, name }: { address: string; name: string }) {
    const t = useT();

    return (
        <div
            role="img"
            aria-label={t('pages.visit.map.label', { name, address })}
            className="relative aspect-[4/3] overflow-hidden rounded-lg border border-line bg-clay md:aspect-[16/10]"
        >
            <svg
                viewBox="0 0 400 300"
                preserveAspectRatio="xMidYMid slice"
                aria-hidden="true"
                className="absolute inset-0 size-full text-line-strong"
            >
                <g fill="none" stroke="currentColor" strokeLinecap="round">
                    <path d="M-10 70 L410 40" strokeWidth="14" opacity="0.35" />
                    <path d="M60 -10 L90 310" strokeWidth="10" opacity="0.35" />
                    <path
                        d="M280 -10 L250 310"
                        strokeWidth="10"
                        opacity="0.35"
                    />
                    <path
                        d="M-10 230 L410 250"
                        strokeWidth="10"
                        opacity="0.35"
                    />
                    <path
                        d="M-10 150 L410 140"
                        strokeWidth="18"
                        opacity="0.55"
                    />
                    <path d="M170 -10 L180 310" strokeWidth="5" opacity="0.3" />
                    <path d="M-10 195 L410 205" strokeWidth="4" opacity="0.3" />
                    <path
                        d="M330 -10 L345 310"
                        strokeWidth="4"
                        opacity="0.25"
                    />
                </g>
            </svg>
            <p className="absolute top-[56%] left-[18%] -rotate-1 text-label text-ink-muted">
                {t('pages.visit.map.street')}
            </p>
            <MarkerDrop className="absolute top-[30%] left-[52%] -translate-x-1/2">
                <MapPin
                    aria-hidden="true"
                    className="size-12 fill-ember text-ember-ink drop-shadow-sm"
                    strokeWidth={1.5}
                />
            </MarkerDrop>
        </div>
    );
}
