import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** The title of an access screen, what happens here below it and, when it helps, an icon in a jade tile above. */
export default function AuthHeading({
    icon: Icon,
    title,
    children,
}: {
    icon?: LucideIcon;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-start gap-4">
            {Icon && (
                <span className="flex size-[52px] items-center justify-center rounded-[14px] bg-accent text-primary">
                    <Icon aria-hidden className="size-6" />
                </span>
            )}
            <div className="flex flex-col gap-2">
                <h1 className="text-[28px] leading-9 font-semibold tracking-[-0.01em]">
                    {title}
                </h1>
                {children !== undefined && (
                    <p className="text-muted-foreground">{children}</p>
                )}
            </div>
        </div>
    );
}
