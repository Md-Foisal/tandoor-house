import { Picture } from '@/components/brand/picture';
import { cn } from '@/lib/utils';

/**
 * Dish photo at a fixed size: the uploaded file when there is one, the
 * media-manifest picture otherwise.
 */
export function Thumb({
    photoUrl,
    mediaKey,
    className,
}: {
    photoUrl: string | null;
    mediaKey: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative size-14 shrink-0 overflow-hidden rounded-md bg-clay',
                className,
            )}
        >
            {photoUrl ? (
                <img
                    src={photoUrl}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <Picture mediaKey={mediaKey} alt="" sizes="96px" />
            )}
        </div>
    );
}
