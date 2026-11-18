import { ChevronDown, Menu, X } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import ToolsMenuPanel from '@/components/landing/tools-menu-panel';
import { Button } from '@/components/ui/button';
import { useDismiss } from '@/hooks/use-dismiss';
import { cn } from '@/lib/utils';

type Props = {
    /** `bar`: the word «Herramientas» in a wide header; `icon`: the menu button of a phone. */
    variant: 'bar' | 'icon';
    className?: string;
};

/** The nine tools by family, each to its own page, and the guide: a disclosure that closes with Escape or a click outside. */
export default function ToolsMenu({ variant, className }: Props) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);

    const close = useCallback(() => setOpen(false), []);
    useDismiss(open, root, close);

    return (
        <div ref={root} className={cn('relative', className)}>
            {variant === 'bar' ? (
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls="tools-menu"
                    onClick={() => setOpen(!open)}
                    data-test="tools-menu"
                    className="inline-flex min-h-11 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold"
                >
                    Herramientas
                    <ChevronDown
                        aria-hidden
                        className={cn(
                            'size-3.5 transition-transform duration-150 ease-out',
                            open && 'rotate-180',
                        )}
                    />
                </button>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-expanded={open}
                    aria-controls="tools-menu"
                    aria-label={open ? 'Cerrar el menú' : 'Abrir el menú'}
                    onClick={() => setOpen(!open)}
                    data-test="tools-menu-mobile"
                    className="size-11 rounded-full"
                >
                    {open ? <X aria-hidden /> : <Menu aria-hidden />}
                </Button>
            )}
            {open && <ToolsMenuPanel floating={variant === 'bar'} />}
        </div>
    );
}
