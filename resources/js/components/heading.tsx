import { cn } from '@/lib/utils';

export default function Heading({
    title,
    description,
    variant = 'default',
    id,
    className,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    id?: string;
    className?: string;
}) {
    return (
        <header className={cn('space-y-1', className)}>
            <h2
                id={id}
                className={
                    variant === 'small'
                        ? 'text-title font-semibold text-ink'
                        : 'font-display text-display-md text-ink'
                }
            >
                {title}
            </h2>
            {description && <p className="text-ink-muted">{description}</p>}
        </header>
    );
}
