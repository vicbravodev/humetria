import { Link } from '@inertiajs/react';
import { Maximize2, SquarePen, X } from 'lucide-react';
import HumiAvatar from '@/components/assistant/humi-avatar';
import type { HumiExpression } from '@/lib/assistant/humi-expressions';
import { Button } from '@/components/ui/button';

const ACTION = 'size-11 text-muted-foreground xl:size-10';

/**
 * Humi's name and what it is looking at, with its actions together at the right: a new conversation, open in full page
 * (not on a phone) and close. It keeps its height whichever of them it shows.
 */
export default function HumiHeader({
    expression,
    context,
    fullPageHref,
    onNew,
    onClose,
}: {
    expression: HumiExpression;
    context: string;
    fullPageHref: string | null;
    onNew: (() => void) | null;
    onClose: (() => void) | null;
}) {
    return (
        <header className="flex h-[68px] shrink-0 items-center gap-1 border-b px-4">
            <HumiAvatar size={40} expression={expression} animated />
            <div className="ml-2 min-w-0 flex-1">
                <h2 className="text-base leading-5 font-semibold">Humi</h2>
                <p className="truncate text-sm text-muted-foreground">
                    {context}
                </p>
            </div>
            {onNew !== null && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Nueva conversación"
                    onClick={onNew}
                    className={ACTION}
                >
                    <SquarePen aria-hidden />
                </Button>
            )}
            {fullPageHref !== null && (
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className={`${ACTION} hidden sm:inline-flex`}
                >
                    <Link
                        href={fullPageHref}
                        aria-label="Abrir en página completa"
                    >
                        <Maximize2 aria-hidden />
                    </Link>
                </Button>
            )}
            {onClose !== null && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Cerrar"
                    onClick={onClose}
                    className={ACTION}
                >
                    <X aria-hidden />
                </Button>
            )}
        </header>
    );
}
