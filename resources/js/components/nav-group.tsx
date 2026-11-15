import { ChevronRight, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel } from '@/components/ui/sidebar';

/**
 * A family of tools in the sidebar (Cumple la norma…): folded until its name is pressed, so the sidebar shows the
 * families and not every tool at once. It starts open on one of its pages. Folded, it carries its tools' count (the
 * evaluations in course) so the signal is not lost. It is the parent of its tools and reads as one: a row as big as a
 * tool, in semibold, and its tools hang indented under it along a guide line.
 */
export function NavGroup({
    label,
    icon: Icon,
    badge,
    holdsPage,
    children,
}: {
    label: string;
    icon: LucideIcon | undefined;
    badge: number;
    holdsPage: boolean;
    children: ReactNode;
}) {
    return (
        <Collapsible asChild defaultOpen={holdsPage}>
            <SidebarGroup className="group/family py-0">
                <SidebarGroupLabel
                    asChild
                    className="h-11 w-full gap-2.5 rounded-lg text-left text-base font-semibold tracking-normal text-sidebar-foreground normal-case transition-colors hover:bg-secondary [&>svg]:size-5 [&>svg:first-child]:text-muted-foreground [&>svg:last-child]:size-4"
                >
                    <CollapsibleTrigger>
                        {Icon !== undefined && <Icon aria-hidden />}
                        <span className="truncate">{label}</span>
                        {badge > 0 && (
                            <span
                                data-sidebar="group-badge"
                                className="rounded-full bg-sidebar-accent px-[7px] text-xs leading-5 font-medium tracking-normal text-sidebar-foreground tabular-nums group-data-[state=open]/family:hidden"
                            >
                                {badge}
                            </span>
                        )}
                        <ChevronRight className="ml-auto transition-transform duration-200 ease-out group-data-[state=open]/family:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                {/* Under the family's icon, a guide line its tools hang from. */}
                <CollapsibleContent className="ml-[21px] unfold border-l border-sidebar-border pl-1.5">
                    {children}
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}
