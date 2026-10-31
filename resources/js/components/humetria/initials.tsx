import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

/**
 * A person or a company before any picture: their initials on a soft fill. People are round, companies square
 * (pantallas: Empleados, Consola-Empresas, the tenant card of the sidebar).
 */
export default function Initials({
    name,
    kind = 'person',
    className,
}: {
    name: string;
    kind?: 'person' | 'company';
    className?: string;
}) {
    const initials = useInitials();

    return (
        <span
            aria-hidden
            className={cn(
                'inline-flex size-8 shrink-0 items-center justify-center text-xs font-semibold',
                kind === 'person'
                    ? 'rounded-full bg-secondary text-foreground'
                    : 'rounded-lg bg-accent text-primary',
                className,
            )}
        >
            {initials(name)}
        </span>
    );
}
