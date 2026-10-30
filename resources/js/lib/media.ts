type SourceList = Array<[number, string]>;

export type MediaImage = {
    alt: string;
    width: number;
    height: number;
    placeholder: string;
    sources: { avif: SourceList; webp: SourceList };
    credit?: string;
};

/** A staff upload, converted server-side to the same shape as a manifest image. */
export type UploadedPhoto = Pick<
    MediaImage,
    'width' | 'height' | 'placeholder' | 'sources'
>;

/** A manifest key, or an uploaded photo which takes precedence over it. */
export type MenuImage = string | UploadedPhoto;

export type MediaVideo = {
    video: Record<'720' | '1080', { webm: string; mp4: string }>;
    credit?: string;
};

type Manifest = Record<string, MediaImage | MediaVideo>;

const modules = import.meta.glob<{ default: Manifest }>('../data/media.json', {
    eager: true,
});

const manifest: Manifest = Object.values(modules)[0]?.default ?? {};

function isImage(
    entry: MediaImage | MediaVideo | undefined,
): entry is MediaImage {
    return entry !== undefined && 'sources' in entry;
}

export function getImage(key: string): MediaImage | null {
    const entry = manifest[key];

    return isImage(entry) ? entry : null;
}

export function resolveImage(image: MenuImage): MediaImage | null {
    if (typeof image !== 'string') {
        return { ...image, alt: '' };
    }

    return getImage(image);
}

export function getVideo(key: string): MediaVideo | null {
    const entry = manifest[key];

    return entry !== undefined && 'video' in entry ? entry : null;
}

export function srcSet(list: SourceList): string {
    return list.map(([width, src]) => `${src} ${width}w`).join(', ');
}
