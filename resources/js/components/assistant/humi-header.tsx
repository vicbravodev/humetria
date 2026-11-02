import { Link } from '@inertiajs/react';
import { Ellipsis, Maximize2, Plus, X } from 'lucide-react';
import HumiAvatar from '@/components/assistant/humi-avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

/**
 * Humi's name and what it is looking at, with the menu (new conversation, open in full page) and the button to close.
 * Inside a sheet the sheet brings its own close button, so none is drawn here.
 */
export default function HumiHeader({
    context,
    fullPageHref,
    onNew,
    onClose,
}: {
    context: string;
    fullPageHref: string | null;
    onNew: () => void;
    onClose: (() => void) | null;
}) {
    return (
        <header className="flex items-center gap-3 border-b px-4 py-3 pr-14">
            <HumiAvatar size={40} />
            <div className="min-w-0 flex-1">
                <h2 className="text-base leading-5 font-semibold">Humi</h2>
                <p className="truncate text-sm text-muted-foreground">
                    {context}
                </p>
            </div>
            {fullPageHref !== null && (
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground xl:size-9"
                >
                    <Link
                        href={fullPageHref}
                        aria-label="Abrir en página completa"
                    >
                        <Maximize2 aria-hidden />
                    </Link>
                </Button>
            )}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Más opciones"
                        className="text-muted-foreground xl:size-9"
                    >
                        <Ellipsis aria-hidden />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={onNew}>
                        <Plus aria-hidden />
                        Nueva conversación
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            {onClose !== null && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Cerrar"
                    onClick={onClose}
                    className="text-muted-foreground xl:size-9"
                >
                    <X aria-hidden />
                </Button>
            )}
        </header>
    );
}
