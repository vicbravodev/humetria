import { Link } from '@inertiajs/react';
import { Ellipsis, Maximize2, Plus, X } from 'lucide-react';
import HumiAvatar from '@/components/assistant/humi-avatar';
import type { HumiExpression } from '@/lib/assistant/humi-expressions';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/**
 * Humi's name and what it is looking at, with the menu (new conversation, open in full page) and the button to close.
 * Inside a sheet the sheet brings its own close button, so none is drawn here and the header leaves room for it.
 */
export default function HumiHeader({
    expression,
    context,
    fullPageHref,
    onNew,
    onClose,
    sheetCloses = false,
}: {
    expression: HumiExpression;
    context: string;
    fullPageHref: string | null;
    onNew: () => void;
    onClose: (() => void) | null;
    /** The sheet draws its close button over the header's right end. */
    sheetCloses?: boolean;
}) {
    return (
        <header
            className={cn(
                'flex items-center gap-3 border-b px-4 py-3',
                sheetCloses && 'pr-14',
            )}
        >
            <HumiAvatar size={40} expression={expression} animated />
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
