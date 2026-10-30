import { Link, router } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useEffect, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import { enforceConsent } from '@/components/consent/consent-store';
import { useConsent } from '@/components/consent/use-consent';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/lib/i18n';
import { reducedFade, uiTransition } from '@/lib/motion';

type View = 'main' | 'choose';

/**
 * PECR cookie notice. Reject and Accept share one size and style, sit side
 * by side and take one click each. It is a non-modal region: the page behind
 * stays usable, and nothing is gated behind it.
 */
export function ConsentBanner() {
    const t = useT();
    const consent = useConsent();
    const reduced = useReducedMotion();
    const [view, setView] = useState<View>('main');
    const [preferences, setPreferences] = useState(false);
    const heading = useRef<HTMLHeadingElement>(null);
    const returnTo = useRef<HTMLElement | null>(null);
    const mounted = useRef(false);
    const titleId = useId();
    const preferencesId = useId();

    // Clears anything a component wrote without consent, on load and as the
    // visitor moves around or leaves.
    useEffect(() => {
        enforceConsent();

        const leave = () => enforceConsent();
        window.addEventListener('pagehide', leave);
        const offNavigate = router.on('navigate', leave);

        return () => {
            window.removeEventListener('pagehide', leave);
            offNavigate();
        };
    }, []);

    // A reopened banner takes focus and gives it back; a first-visit banner
    // never steals it.
    useEffect(() => {
        if (consent.open && consent.decided) {
            returnTo.current =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;
            setPreferences(consent.preferences);
            setView('main');
            heading.current?.focus();
        } else if (!consent.open) {
            setView('main');

            if (returnTo.current?.isConnected) {
                returnTo.current.focus();
            }

            returnTo.current = null;
        }
    }, [consent.open, consent.decided, consent.preferences]);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        if (consent.open) {
            heading.current?.focus();
        }
    }, [view, consent.open]);

    useEffect(() => {
        if (!consent.open || !consent.decided) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                consent.close();
            }
        };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [consent]);

    const finish = (value: boolean) => {
        consent.save(value);
        toast.success(t('legal.consent.saved'));
    };

    const motionProps = reduced
        ? {
              initial: { opacity: 0 },
              animate: { opacity: 1 },
              exit: { opacity: 0 },
              transition: reducedFade,
          }
        : {
              initial: { opacity: 0, y: 24 },
              animate: { opacity: 1, y: 0 },
              exit: { opacity: 0, y: 24 },
              transition: uiTransition,
          };

    return (
        <AnimatePresence>
            {consent.open && (
                <motion.div
                    key="consent"
                    role="dialog"
                    aria-modal="false"
                    aria-labelledby={titleId}
                    {...motionProps}
                    className="fixed inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-50 max-h-[calc(100svh-6rem)] overflow-y-auto rounded-lg border border-line-strong bg-surface p-5 text-ink shadow-plate md:inset-x-auto md:bottom-4 md:left-4 md:w-[26rem]"
                >
                    <h2
                        id={titleId}
                        ref={heading}
                        tabIndex={-1}
                        className="font-display text-xl outline-hidden"
                    >
                        {view === 'main'
                            ? t('legal.consent.title')
                            : t('legal.consent.choose_title')}
                    </h2>

                    {view === 'main' ? (
                        <>
                            <p className="mt-2 text-sm text-ink-muted">
                                {t('legal.consent.body')}{' '}
                                <Link
                                    href="/cookies"
                                    className="inline-block py-2 text-ink underline underline-offset-4"
                                >
                                    {t('legal.consent.more')}
                                </Link>
                            </p>
                            <div className="mt-3 grid grid-cols-2 gap-3">
                                <Button
                                    variant="secondary"
                                    onClick={() => finish(false)}
                                    className="w-full whitespace-normal"
                                >
                                    {t('legal.consent.reject')}
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={() => finish(true)}
                                    className="w-full whitespace-normal"
                                >
                                    {t('legal.consent.accept')}
                                </Button>
                            </div>
                            <Button
                                variant="ghost"
                                onClick={() => setView('choose')}
                                className="mt-2 w-full"
                            >
                                {t('legal.consent.choose')}
                            </Button>
                        </>
                    ) : (
                        <>
                            <ul className="mt-3 flex flex-col divide-y divide-line">
                                <li className="flex items-start justify-between gap-4 py-3">
                                    <div>
                                        <p className="font-semibold">
                                            {t('legal.consent.necessary_title')}
                                        </p>
                                        <p className="text-sm text-ink-muted">
                                            {t('legal.consent.necessary_body')}
                                        </p>
                                    </div>
                                    <span className="shrink-0 pt-0.5 text-sm font-semibold text-ink-muted">
                                        {t('legal.consent.necessary_state')}
                                    </span>
                                </li>
                                <li className="flex items-start justify-between gap-4 py-3">
                                    <div>
                                        <Label
                                            htmlFor={preferencesId}
                                            className="font-semibold"
                                        >
                                            {t(
                                                'legal.consent.preferences_title',
                                            )}
                                        </Label>
                                        <p className="text-sm text-ink-muted">
                                            {t(
                                                'legal.consent.preferences_body',
                                            )}
                                        </p>
                                    </div>
                                    <Switch
                                        id={preferencesId}
                                        checked={preferences}
                                        onCheckedChange={setPreferences}
                                    />
                                </li>
                            </ul>
                            <div className="mt-3 grid grid-cols-2 gap-3">
                                <Button
                                    variant="secondary"
                                    onClick={() => setView('main')}
                                    className="w-full"
                                >
                                    {t('legal.consent.back')}
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={() => finish(preferences)}
                                    className="w-full whitespace-normal"
                                >
                                    {t('legal.consent.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </motion.div>
            )}
        </AnimatePresence>
    );
}
