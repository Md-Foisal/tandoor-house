import { ImagePlus, Undo2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Thumb } from './thumb';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

export function PhotoField({
    file,
    photoUrl,
    mediaKey,
    error,
    onChange,
}: {
    file: File | null;
    photoUrl: string | null;
    mediaKey: string | null;
    error?: string;
    onChange: (file: File | null) => void;
}) {
    const t = useT();
    const input = useRef<HTMLInputElement>(null);
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

    const shown = preview ?? photoUrl;

    return (
        <div className="grid gap-3">
            <div className="relative aspect-[4/3] w-full max-w-sm overflow-hidden rounded-lg border border-line bg-clay">
                {shown ? (
                    <img
                        src={shown}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : mediaKey ? (
                    <Thumb
                        photoUrl={null}
                        mediaKey={mediaKey}
                        className="size-full rounded-none"
                    />
                ) : null}
            </div>
            <p className="text-sm text-ink-muted">
                {file
                    ? `${t('staff_menu.editor.photo_new')}: ${file.name}`
                    : photoUrl
                      ? t('staff_menu.editor.photo_current')
                      : t('staff_menu.editor.photo_none')}
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
                        photoUrl || file
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
