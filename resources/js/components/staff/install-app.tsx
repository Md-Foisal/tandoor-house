import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useT } from '@/lib/i18n';

type InstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

function isInstalled(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

function isIpad(): boolean {
    return (
        /iPad/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

/**
 * Links the staff manifest, sets the browser chrome colour and registers the
 * service worker. Mounted by the staff layout only, so public pages stay clean.
 */
export function StaffPwaHead() {
    useEffect(() => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker
                .register('/staff-sw.js', { scope: '/staff/' })
                .catch(() => undefined);
        }
    }, []);

    return (
        <Head>
            <link rel="manifest" href="/staff/manifest.webmanifest" />
            <link
                rel="apple-touch-icon"
                href="/staff/icons/apple-touch-icon.png"
            />
            <meta name="theme-color" content="#16110D" />
            <meta name="mobile-web-app-capable" content="yes" />
            <meta name="apple-mobile-web-app-capable" content="yes" />
            <meta
                name="apple-mobile-web-app-status-bar-style"
                content="black-translucent"
            />
        </Head>
    );
}

/**
 * Sidebar button that installs the staff app. Hidden once installed; where the
 * browser has no install prompt (Safari on iPad) it shows the manual steps.
 */
export function InstallApp() {
    const t = useT();
    const [installed, setInstalled] = useState(true);
    const [prompt, setPrompt] = useState<InstallPromptEvent | null>(null);
    const [helpOpen, setHelpOpen] = useState(false);
    const [ipad, setIpad] = useState(false);

    useEffect(() => {
        setInstalled(isInstalled());
        setIpad(isIpad());

        const onPrompt = (event: Event) => {
            event.preventDefault();
            setPrompt(event as InstallPromptEvent);
        };
        const onInstalled = () => {
            setInstalled(true);
            setPrompt(null);
        };

        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', onPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    if (installed) {
        return null;
    }

    const install = async () => {
        if (!prompt) {
            setHelpOpen(true);

            return;
        }

        await prompt.prompt();
        const { outcome } = await prompt.userChoice;

        if (outcome === 'accepted') {
            setInstalled(true);
        }

        setPrompt(null);
    };

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                type="button"
                onClick={install}
                tooltip={t('staff.install.button')}
                className="h-11 font-semibold"
            >
                <Download aria-hidden="true" />
                <span>{t('staff.install.button')}</span>
            </SidebarMenuButton>
            <Dialog open={helpOpen} onOpenChange={setHelpOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff.install.help_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                ipad
                                    ? 'staff.install.help_ipad'
                                    : 'staff.install.help_other',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <p className="text-sm text-ink-muted">
                        {t('staff.install.help_why')}
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            className="min-h-11"
                            onClick={() => setHelpOpen(false)}
                        >
                            {t('staff.install.close')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SidebarMenuItem>
    );
}
