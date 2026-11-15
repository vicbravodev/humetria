import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Ellipsis } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type RowMenuItem = {
    label: string;
    icon?: LucideIcon;
    /** A link, or a handler when the action is not a page. */
    href?: NonNullable<InertiaLinkProps['href']>;
    onSelect?: () => void;
    /** The one that cannot be undone: apart, in the destructive color. */
    destructive?: boolean;
};

/**
 * «Más acciones» of a table row: a 44 px icon button that opens what the row can do besides its main link (the whole
 * row is the link; at most one text button stays beside it).
 */
export default function RowMenu({
    label,
    items,
}: {
    /** Says whose row it is, for screen readers: "Acciones de Ana Pérez". */
    label: string;
    items: RowMenuItem[];
}) {
    const regular = items.filter((item) => item.destructive !== true);
    const destructive = items.filter((item) => item.destructive === true);
    const body = (item: RowMenuItem) => {
        const Icon = item.icon;

        return (
            <>
                {Icon !== undefined && <Icon aria-hidden />}
                {item.label}
            </>
        );
    };
    const entry = (item: RowMenuItem) => (
        <DropdownMenuItem
            key={item.label}
            asChild={item.href !== undefined}
            variant={item.destructive === true ? 'destructive' : 'default'}
            onSelect={item.onSelect}
        >
            {item.href === undefined ? (
                body(item)
            ) : (
                <Link href={item.href}>{body(item)}</Link>
            )}
        </DropdownMenuItem>
    );

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={label}
                    data-slot="row-menu"
                    className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                >
                    <Ellipsis className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-44">
                {regular.map(entry)}
                {regular.length > 0 && destructive.length > 0 && (
                    <DropdownMenuSeparator />
                )}
                {destructive.map(entry)}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
