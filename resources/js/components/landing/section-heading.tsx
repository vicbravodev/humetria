import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    title: string;
    children?: ReactNode;
    className?: string;
};

export default function SectionHeading({
    id,
    title,
    children,
    className,
}: Props) {
    return (
        <div className={cn('max-w-2xl', className)}>
            <h2
                id={id}
                className="scroll-mt-24 text-2xl leading-8 font-semibold tracking-[-0.01em] sm:text-3xl sm:leading-[38px]"
            >
                {title}
            </h2>
            {children === undefined ? null : (
                <p className="mt-3 text-lg leading-7 text-muted-foreground">
                    {children}
                </p>
            )}
        </div>
    );
}
