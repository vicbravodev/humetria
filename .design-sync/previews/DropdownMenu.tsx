import {
    Button,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'humetria';
import { Ellipsis, KeyRound, Pencil, UserMinus } from 'lucide-react';

export const RowActions = () => (
    <div className="flex" style={{ justifyContent: 'flex-end', paddingRight: 160 }}>
        <DropdownMenu open modal={false} onOpenChange={() => {}}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Acciones de Mariana López"
                    className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                >
                    <Ellipsis className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-44">
                <DropdownMenuItem>
                    <Pencil />
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem>
                    <KeyRound />
                    Ver su acceso
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive">
                    <UserMinus />
                    Dar de baja
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
);
