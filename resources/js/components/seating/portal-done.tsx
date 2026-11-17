import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * What a Ley Silla screen says once its task is done (boards SillaEnviado and SillaAcuseHecho): an 80 px icon that comes in
 * (fade and a scale from 0.96 in 200 ms; only the fade with reduced motion), the title and the text, centered.
 */
export default function PortalDone({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            role="status"
            className="flex flex-col items-center gap-4 text-center"
        >
            <span
                aria-hidden
                className="flex size-20 shrink-0 animate-in items-center justify-center rounded-2xl bg-accent text-primary duration-200 ease-out fade-in zoom-in-96"
            >
                <Icon className="size-10" />
            </span>
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em] text-balance">
                {title}
            </h1>
            <p className="max-w-xs text-[17px] leading-[26px] text-muted-foreground">
                {children}
            </p>
        </section>
    );
}
