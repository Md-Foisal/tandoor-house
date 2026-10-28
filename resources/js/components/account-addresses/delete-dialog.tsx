import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useOnline } from '@/components/brand/states';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { destroy } from '@/routes/account/addresses';
import type { SavedAddress } from './types';

export function DeleteAddressDialog({
    address,
    onClose,
}: {
    address: SavedAddress | null;
    onClose: () => void;
}) {
    const t = useT();
    const online = useOnline();
    const [removing, setRemoving] = useState(false);

    const remove = () => {
        if (!address) {
            return;
        }

        router.delete(destroy(address.id).url, {
            preserveScroll: true,
            onStart: () => setRemoving(true),
            onFinish: () => setRemoving(false),
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open={address !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="font-display text-display-md">
                        {t('addresses.confirm.title', {
                            label: address?.label ?? '',
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {address &&
                            t('addresses.confirm.body', {
                                address: `${address.line1}, ${address.postcode}`,
                            })}
                        {address?.is_default &&
                            ` ${t('addresses.confirm.default_note')}`}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        className="min-h-12 sm:min-h-11"
                        onClick={onClose}
                    >
                        {t('addresses.confirm.cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        className="min-h-12 sm:min-h-11"
                        onClick={remove}
                        disabled={removing || !online}
                    >
                        {removing
                            ? t('addresses.confirm.removing')
                            : t('addresses.confirm.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
