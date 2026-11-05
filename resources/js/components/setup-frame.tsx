import type { ReactNode } from 'react';
import { ThemeSwitch } from '@/components/theme-switch';

/**
 * The frame of a first-time configuration (boards V2-Aplica, V2-Aplica-Otras, V3-Onboarding): no sidebar, a thin bar that
 * says where it is ("NOM-037 · Planta Querétaro · configuración inicial") with the way out at its right, and the page.
 */
export default function SetupFrame({
    caption,
    aside,
    children,
}: {
    caption: string;
    aside: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="min-h-svh bg-sidebar p-2 text-foreground">
            <div className="flex min-h-[calc(100svh-16px)] flex-col rounded-xl bg-background shadow-raised">
                <div className="flex min-h-14 flex-wrap items-center gap-3 border-b px-4 py-2 md:px-5">
                    <p className="w-full min-w-0 text-sm text-muted-foreground sm:w-auto sm:flex-1">
                        {caption}
                    </p>
                    <ThemeSwitch />
                    {aside}
                </div>
                <main className="flex flex-1 flex-col px-4 py-8 md:px-12 md:py-12">
                    {children}
                </main>
            </div>
        </div>
    );
}
