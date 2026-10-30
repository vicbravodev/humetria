import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** One step of a paginator: a link to the page, or the same button disabled when there is no such page. */
export default function PageButton({
    href,
    label,
    icon: Icon,
    iconAfter = false,
    preserveState = false,
}: {
    href: string | null;
    label: string;
    icon: LucideIcon;
    iconAfter?: boolean;
    preserveState?: boolean;
}) {
    const content = (
        <>
            {!iconAfter && <Icon aria-hidden="true" />}
            {label}
            {iconAfter && <Icon aria-hidden="true" />}
        </>
    );

    if (href === null) {
        return (
            <Button variant="outline" disabled>
                {content}
            </Button>
        );
    }

    return (
        <Button variant="outline" asChild>
            <Link href={href} preserveState={preserveState}>
                {content}
            </Link>
        </Button>
    );
}
