import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * One setting of the account (pantallas: Ajustes, Seguridad): its icon on a soft tile, its name with its state, one
 * sentence of what it does and its action at the right; what it holds goes below.
 */
export default function SettingRow({
    icon: Icon,
    title,
    status,
    description,
    action,
    children,
}: {
    icon: LucideIcon;
    title: string;
    status?: ReactNode;
    description: string;
    action?: ReactNode;
    children?: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3.5 border-t pt-7">
            <div className="flex flex-wrap items-start gap-4">
                <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                    <Icon aria-hidden className="size-5" />
                </span>
                <div className="flex flex-[1_1_280px] flex-col gap-0.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg leading-[26px] font-semibold">
                            {title}
                        </h2>
                        {status}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
