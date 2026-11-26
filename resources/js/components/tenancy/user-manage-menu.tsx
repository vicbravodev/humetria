import type { RowMenuItem } from '@/components/humetria/row-menu';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/** «Gestionar» of a person of the team (tablero Equipo): a text button that opens what the console may do with them. */
export default function UserManageMenu({
    name,
    items,
}: {
    name: string;
    items: RowMenuItem[];
}) {
    const regular = items.filter((item) => item.destructive !== true);
    const destructive = items.filter((item) => item.destructive === true);
    const entry = (item: RowMenuItem) => {
        const Icon = item.icon;

        return (
            <DropdownMenuItem
                key={item.label}
                variant={item.destructive === true ? 'destructive' : 'default'}
                onSelect={item.onSelect}
            >
                {Icon !== undefined && <Icon aria-hidden />}
                {item.label}
            </DropdownMenuItem>
        );
    };

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`Gestionar a ${name}`}
                    className="text-primary data-[state=open]:bg-accent"
                >
                    Gestionar
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
