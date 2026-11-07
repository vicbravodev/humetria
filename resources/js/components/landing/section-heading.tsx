import type { ReactNode } from 'react';

type Props = {
    id: string;
    title: string;
    children?: ReactNode;
};

export default function SectionHeading({ id, title, children }: Props) {
    return (
        <div className="flex max-w-[640px] flex-col gap-2.5">
            <h2
                id={id}
                className="scroll-mt-24 text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] sm:text-5xl"
            >
                {title}
            </h2>
            {children === undefined ? null : (
                <p className="text-lg leading-7 text-muted-foreground">
                    {children}
                </p>
            )}
        </div>
    );
}
