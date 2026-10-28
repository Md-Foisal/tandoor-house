import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import { Field } from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';

type Props = {
    onSuccess: () => void;
};

export default function PasskeyRegistration({ onSuccess }: Props) {
    const t = useT();
    const [name, setName] = useState(() => {
        const ua = navigator.userAgent;

        const browser = [
            { pattern: /Edg|Edge/, name: 'Edge' },
            { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
            { pattern: /Firefox|FxiOS/, name: 'Firefox' },
            { pattern: /Chrome|CriOS/, name: 'Chrome' },
            { pattern: /Safari/, name: 'Safari' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        const os = [
            { pattern: /iPhone/, name: 'iPhone' },
            { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
            { pattern: /Android/, name: 'Android' },
            { pattern: /Mac/, name: 'Mac' },
            { pattern: /Windows/, name: 'Windows' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        return [browser, os].filter(Boolean).join(' on ') || '';
    });

    const [showForm, setShowForm] = useState(false);
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setName('');
            setShowForm(false);
            onSuccess();
        },
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            return;
        }

        await register(name);
    };

    const handleCancel = () => {
        setShowForm(false);
        setName('');
    };

    if (!isSupported) {
        return (
            <p className="text-ink-muted">
                {t('settings_pages.passkeys.unsupported')}
            </p>
        );
    }

    if (!showForm) {
        return (
            <Button variant="outline" onClick={() => setShowForm(true)}>
                {t('settings_pages.passkeys.add')}
            </Button>
        );
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="space-y-4 rounded-lg bg-clay p-4"
        >
            <Field
                id="passkey-name"
                label={t('settings_pages.passkeys.name_label')}
                hint={t('settings_pages.passkeys.name_hint')}
                error={error ?? undefined}
            >
                {(control) => (
                    <Input
                        {...control}
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder={t(
                            'settings_pages.passkeys.name_placeholder',
                        )}
                        autoFocus
                    />
                )}
            </Field>

            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={isLoading || !name.trim()}>
                    {isLoading && <Spinner />}
                    {isLoading
                        ? t('settings_pages.passkeys.registering')
                        : t('settings_pages.passkeys.register')}
                </Button>
                <Button type="button" variant="ghost" onClick={handleCancel}>
                    {t('settings_pages.common.cancel')}
                </Button>
            </div>
        </form>
    );
}
