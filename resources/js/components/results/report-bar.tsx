import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** The bar on top of the report (it stays there from tablets up): where to go back, the parts to jump to, and what to do with it. */
export default function ReportBar({
    start,
    sections,
    children,
    className,
}: {
    start: ReactNode;
    sections: { id: string; label: string }[];
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'z-10 border-b bg-background md:sticky print:hidden',
                className,
            )}
        >
            <div className="mx-auto flex max-w-[1120px] flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-6">
                {start}
                <nav
                    aria-label="Secciones del informe"
                    className="hidden flex-1 flex-wrap justify-center gap-1 lg:flex"
                >
                    {sections.map((section) => (
                        <a
                            key={section.id}
                            href={`#${section.id}`}
                            className="rounded-md px-3 py-2.5 text-sm text-muted-foreground transition-colors duration-150 ease-out hover:bg-accent hover:text-foreground"
                        >
                            {section.label}
                        </a>
                    ))}
                </nav>
                <div className="flex flex-wrap gap-2">{children}</div>
            </div>
        </div>
    );
}
