import type { ReactNode } from 'react';

/** The top of every panel page (PanelRH): caption, h1 title and, at the right, its one action or a note. */
export default function PageHeader({
    caption,
    title,
    children,
}: {
    caption?: string;
    title: string;
    children?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-4">
            <div className="flex flex-col gap-1">
                {caption !== undefined && (
                    <p className="text-[13px] leading-[18px] font-medium tracking-[0.02em] text-muted-foreground uppercase">
                        {caption}
                    </p>
                )}
                <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em]">
                    {title}
                </h1>
            </div>
            {children}
        </header>
    );
}
