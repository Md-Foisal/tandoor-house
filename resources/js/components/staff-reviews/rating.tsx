import { Star } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export function Rating({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    const t = useT();

    return (
        <span
            role="img"
            aria-label={t('staff_reviews.rating', { rating: value })}
            className={cn('inline-flex items-center gap-0.5', className)}
        >
            {Array.from({ length: 5 }, (_, i) => (
                <Star
                    key={i}
                    aria-hidden="true"
                    className={cn(
                        'size-5',
                        i < value
                            ? 'fill-turmeric text-turmeric'
                            : 'text-line-strong',
                    )}
                />
            ))}
        </span>
    );
}
