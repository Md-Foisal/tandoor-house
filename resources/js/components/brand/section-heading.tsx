import type { ReactNode } from 'react';
import { EmberLine } from '@/components/brand/ember-line';
import { cn } from '@/lib/utils';

type Props = {
    eyebrow?: string;
    title: ReactNode;
    description?: ReactNode;
    as?: 'h1' | 'h2' | 'h3';
    size?: 'xl' | 'lg' | 'md';
    align?: 'start' | 'center';
    className?: string;
    id?: string;
};

const sizes = {
    xl: 'text-display-xl',
    lg: 'text-display-lg',
    md: 'text-display-md',
} as const;

export function SectionHeading({
    eyebrow,
    title,
    description,
    as: Tag = 'h2',
    size = 'lg',
    align = 'start',
    className,
    id,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3',
                align === 'center' && 'items-center text-center',
                className,
            )}
        >
            {eyebrow && <p className="text-label text-ember">{eyebrow}</p>}
            <Tag id={id} className={cn('font-display text-ink', sizes[size])}>
                {title}
            </Tag>
            <EmberLine draw />
            {description && (
                <p className="max-w-[56ch] text-body-lg text-ink-muted">
                    {description}
                </p>
            )}
        </div>
    );
}
