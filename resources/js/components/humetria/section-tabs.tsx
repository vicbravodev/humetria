import type { ReactNode } from 'react';
import { useActiveSection } from '@/hooks/use-active-section';
import { cn } from '@/lib/utils';

/**
 * The parts of a long report as tabs that stay on top while it is read (board V4-Comp-Reporte): each jumps to its part, the one
 * being read is underlined, and the actions go at the right. It scrolls sideways on a phone and is not printed.
 */
export default function SectionTabs({
    label,
    sections,
    className,
    children,
}: {
    label: string;
    sections: { id: string; label: string }[];
    className?: string;
    children?: ReactNode;
}) {
    const active = useActiveSection(sections.map((section) => section.id));

    return (
        <div
            className={cn(
                'sticky z-10 flex items-center gap-3 border-b bg-background print:hidden',
                className,
            )}
        >
            <nav
                aria-label={label}
                className="flex min-w-0 flex-1 [scrollbar-width:none] overflow-x-auto"
            >
                {sections.map((section) => (
                    <a
                        key={section.id}
                        href={`#${section.id}`}
                        aria-current={
                            active === section.id ? 'location' : undefined
                        }
                        className={cn(
                            '-mb-px flex min-h-11 shrink-0 items-center border-b-2 px-3 text-sm whitespace-nowrap transition-colors duration-150 ease-out',
                            active === section.id
                                ? 'border-primary font-medium text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {section.label}
                    </a>
                ))}
            </nav>
            {children !== undefined && (
                <div className="hidden shrink-0 py-1.5 sm:block">
                    {children}
                </div>
            )}
        </div>
    );
}
