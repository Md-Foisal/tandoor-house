import { motion, useReducedMotion } from 'motion/react';
import { useT } from '@/lib/i18n';
import { ease, inView } from '@/lib/motion';

/*
 * A hand-signed line: one continuous stroke that draws itself in once,
 * with a looped capital and a quick underline flick at the end.
 */
export function Signature() {
    const t = useT();
    const reduced = useReducedMotion();

    return (
        <figure className="flex flex-col items-start gap-1">
            <motion.svg
                viewBox="0 0 320 96"
                role="img"
                aria-label={t('pages.story.signature.label')}
                className="h-20 w-64 text-ember md:h-24 md:w-80"
                initial={reduced ? false : 'hidden'}
                whileInView="shown"
                viewport={inView}
            >
                <motion.path
                    d="M14 70c10-22 24-52 34-52 8 0 4 20-6 40-6 12-4 20 4 14 12-10 22-30 32-30 6 0 2 14-2 22-4 10 0 12 8 6 8-6 14-16 22-16 6 0 4 8 0 14-3 5 2 7 8 3 7-5 11-13 17-13 5 0 4 7 1 12-3 6 3 7 10 1 6-5 10-13 16-13 5 0 4 8 0 14-3 5 2 6 8 2 8-6 12-18 22-20 7-1 6 9 2 15-3 5 2 6 7 2 5-4 9-8 14-8 5 0 6 4 4 8M20 82c40-6 96-9 150-8 40 1 80 4 126 2"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    variants={{
                        hidden: { pathLength: 0 },
                        shown: {
                            pathLength: 1,
                            transition: { duration: 1.6, ease: ease.inOut },
                        },
                    }}
                />
            </motion.svg>
            <figcaption className="text-sm text-ink-muted">
                {t('pages.story.signature.role')}
            </figcaption>
        </figure>
    );
}
