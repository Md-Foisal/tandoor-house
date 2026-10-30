import { ImageOff, ImagePlus, Trash2, Undo2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Thumb } from './thumb';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

export function PhotoField({
    file,
    photoUrl,
    mediaKey,
    removed,
    error,
    onChange,
    onRemoveChange,
}: {
    file: File | null;
    photoUrl: string | null;
    mediaKey: string | null;
    removed: boolean;
    error?: string;
    onChange: (file: File | null) => void;
    onRemoveChange: (removed: boolean) => void;
}) {
    const t = useT();
    const input = useRef<HTMLInputElement>(null);
    const removeButton = useRef<HTMLButtonElement>(null);
    const undoButton = useRef<HTMLButtonElement>(null);
    const firstRender = useRef(true);
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    // The button that was pressed disappears; keep keyboard focus on its replacement.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        (removed ? undoButton : removeButton).current?.focus();
    }, [removed]);

    const hasUpload = photoUrl !== null;
    const currentUrl = removed ? null : photoUrl;
    const shown = preview ?? currentUrl;

    let status: string;

    if (file) {
        status = `${t('staff_menu.editor.photo_new')}: ${file.name}`;
    } else if (removed) {
        status = t('staff_menu.editor.photo_removing');
    } else if (currentUrl) {
        status = t('staff_menu.editor.photo_current');
    } else {
        status = t('staff_menu.editor.photo_none');
    }

    return (
        <div className="grid gap-3">
            <div className="relative aspect-[4/3] w-full max-w-sm overflow-hidden rounded-lg border border-line bg-clay">
                {shown ? (
                    <img
                        src={shown}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : removed ? (
                    <div className="grid size-full place-items-center text-ink-muted">
                        <ImageOff aria-hidden="true" className="size-8" />
                    </div>
                ) : mediaKey ? (
                    <Thumb
                        photoUrl={null}
                        mediaKey={mediaKey}
                        className="size-full rounded-none"
                    />
                ) : null}
            </div>
            <p aria-live="polite" className="text-sm text-ink-muted">
                {status}
            </p>
            <div className="flex flex-wrap gap-2">
                <input
                    ref={input}
                    id="field-photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    aria-invalid={error ? true : undefined}
                    aria-describedby="field-photo-hint field-photo-error"
                    onChange={(event) =>
                        onChange(event.target.files?.[0] ?? null)
                    }
                />
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    onClick={() => input.current?.click()}
                >
                    <ImagePlus aria-hidden="true" />
                    {t(
                        currentUrl || file
                            ? 'staff_menu.editor.photo_replace'
                            : 'staff_menu.editor.photo_choose',
                    )}
                </Button>
                {file && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="lg"
                        onClick={() => {
                            if (input.current) input.current.value = '';
                            onChange(null);
                        }}
                    >
                        <Undo2 aria-hidden="true" />
                        {t('staff_menu.editor.photo_remove')}
                    </Button>
                )}
                {hasUpload && !file && !removed && (
                    <Button
                        ref={removeButton}
                        type="button"
                        variant="ghost"
                        size="lg"
                        onClick={() => onRemoveChange(true)}
                    >
                        <Trash2 aria-hidden="true" />
                        {t('staff_menu.editor.photo_remove_saved')}
                    </Button>
                )}
                {removed && (
                    <Button
                        ref={undoButton}
                        type="button"
                        variant="ghost"
                        size="lg"
                        onClick={() => onRemoveChange(false)}
                    >
                        <Undo2 aria-hidden="true" />
                        {t('staff_menu.editor.photo_undo_removal')}
                    </Button>
                )}
            </div>
            <p id="field-photo-hint" className="text-sm text-ink-muted">
                {t('staff_menu.editor.photo_hint')}
            </p>
            {error && (
                <p
                    id="field-photo-error"
                    role="alert"
                    className="text-sm font-medium text-chilli"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
