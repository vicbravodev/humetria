import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type PageFact = { icon: LucideIcon; text: string };

/**
 * The top of every panel page (pantallas: Panel, Eval-Activa, Empleados): an optional caption, the title with its
 * state, then one sentence that says where things stand or a row of facts with icons; its actions go at the right,
 * the primary one last.
 */
export default function PageHeader({
    caption,
    title,
    status,
    description,
    facts,
    children,
}: {
    caption?: string;
    title: string;
    status?: ReactNode;
    description?: ReactNode;
    facts?: PageFact[];
    children?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div className="flex min-w-0 flex-col gap-1.5">
                {caption !== undefined && (
                    <p className="text-[15px] leading-[22px] text-muted-foreground">
                        {caption}
                    </p>
                )}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em] text-balance">
                        {title}
                    </h1>
                    {status}
                </div>
                {description !== undefined && (
                    <p className="max-w-[620px] text-[17px] leading-6 text-pretty text-muted-foreground">
                        {description}
                    </p>
                )}
                {facts !== undefined && facts.length > 0 && (
                    <ul className="flex flex-wrap gap-x-5 gap-y-1 text-[15px] leading-[22px] text-muted-foreground">
                        {facts.map(({ icon: Icon, text }) => (
                            <li
                                key={text}
                                className="flex items-center gap-1.5"
                            >
                                <Icon aria-hidden className="size-4" />
                                {text}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            {children !== undefined && (
                // On a phone the actions take a row of their own, so the ones that grow share it or stack whole.
                <div className="flex flex-wrap items-center gap-2 max-sm:w-full">
                    {children}
                </div>
            )}
        </header>
    );
}
