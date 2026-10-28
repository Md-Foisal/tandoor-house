import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Heading from '@/components/heading';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import { useT } from '@/lib/i18n';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

const EmptyState = () => {
    const t = useT();

    return (
        <div className="p-8 text-center">
            <div className="mx-auto mb-4 grid size-14 place-items-center rounded-full bg-clay">
                <KeyRound className="size-7 text-ink-muted" aria-hidden />
            </div>
            <p className="font-semibold">
                {t('settings_pages.passkeys.empty_title')}
            </p>
            <p className="mt-1 text-ink-muted">
                {t('settings_pages.passkeys.empty_body')}
            </p>
        </div>
    );
};

export default function ManagePasskeys(props: Props) {
    const t = useT();
    const passkeys = props.passkeys ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <section
            aria-labelledby="passkeys-heading"
            className="space-y-6 border-t border-line pt-10"
        >
            <Heading
                id="passkeys-heading"
                variant="small"
                title={t('settings_pages.passkeys.title')}
                description={t('settings_pages.passkeys.description')}
            />

            <ul className="overflow-hidden rounded-lg border border-line bg-surface">
                {passkeys.length > 0 ? (
                    passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={handleDelete}
                        />
                    ))
                ) : (
                    <li>
                        <EmptyState />
                    </li>
                )}
            </ul>

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </section>
    );
}
