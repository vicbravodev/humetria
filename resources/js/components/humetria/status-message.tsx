import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * A whole screen that says something stopped the way (pantallas: Error-403, Error-404, Enlace-Vencido): the empty
 * arch of the logo's "h" around an icon, a small caption, the title, what to do, and the way out below.
 */
export default function StatusMessage({
    icon: Icon,
    caption,
    title,
    children,
    actions,
}: {
    icon: LucideIcon;
    caption: string;
    title: string;
    children: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <section
            data-slot="status-message"
            className="flex max-w-[520px] flex-col items-center gap-5 text-center"
        >
            <span aria-hidden className="relative h-[100px] w-[120px]">
                <svg
                    viewBox="0 5 64 46"
                    className="block h-[86px] w-[120px] overflow-visible"
                >
                    <path
                        d="M10 49V33A22 22 0 0 1 54 33V49"
                        fill="none"
                        className="stroke-track"
                        strokeWidth={6.4}
                        strokeLinecap="round"
                    />
                </svg>
                <Icon className="absolute bottom-1 left-1/2 size-[30px] -translate-x-1/2 text-primary" />
            </span>
            <p className="text-base font-medium text-muted-foreground">
                {caption}
            </p>
            <h1 className="text-[30px] leading-[38px] font-semibold tracking-[-0.01em] text-balance">
                {title}
            </h1>
            <p className="text-[17px] leading-[26px] text-pretty text-muted-foreground">
                {children}
            </p>
            {actions !== undefined && (
                <div className="flex flex-wrap justify-center gap-2.5">
                    {actions}
                </div>
            )}
        </section>
    );
}
