import { KeyRound, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import type { Passkey } from '@/types/auth';

type Props = {
    passkey: Passkey;
    onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
    const t = useT();
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        onDelete(passkey.id, () => setIsDeleting(false));
    };

    return (
        <li className="flex items-center justify-between gap-3 border-b border-line p-4 last:border-b-0">
            <div className="flex min-w-0 items-center gap-4">
                <div className="grid size-10 shrink-0 place-items-center rounded-full bg-clay">
                    <KeyRound className="size-5 text-ink-muted" aria-hidden />
                </div>
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold">{passkey.name}</p>
                        {passkey.authenticator && (
                            <Badge variant="secondary">
                                {passkey.authenticator}
                            </Badge>
                        )}
                    </div>
                    <p className="text-sm text-ink-muted">
                        {t('settings_pages.passkeys.added', {
                            when: passkey.created_at_diff,
                        })}
                        {passkey.last_used_at_diff &&
                            `, ${t('settings_pages.passkeys.last_used', {
                                when: passkey.last_used_at_diff,
                            })}`}
                    </p>
                </div>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-chilli hover:bg-chilli-soft"
                        aria-label={t('settings_pages.passkeys.remove', {
                            name: passkey.name,
                        })}
                    >
                        <Trash2 className="size-4" aria-hidden />
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        {t('settings_pages.passkeys.remove_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('settings_pages.passkeys.remove_body', {
                            name: passkey.name,
                        })}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">
                                {t('settings_pages.common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting && <Spinner />}
                            {isDeleting
                                ? t('settings_pages.passkeys.removing')
                                : t('settings_pages.passkeys.remove_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </li>
    );
}
