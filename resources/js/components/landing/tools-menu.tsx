import { ChevronDown, Menu, X } from 'lucide-react';
import { AnimatePresence } from 'motion/react';
import { useCallback, useRef, useState } from 'react';
import ToolsMenuPanel from '@/components/landing/tools-menu-panel';
import { Button } from '@/components/ui/button';
import { useDismiss } from '@/hooks/use-dismiss';
import { cn } from '@/lib/utils';

type Props = {
    /** `bar`: «Herramientas» inside the header's pill (styled by `triggerClassName`); `icon`: the menu button of a phone. */
    variant: 'bar' | 'icon';
    className?: string;
    triggerClassName?: string;
};

/** The nine tools by family, each to its own page: a disclosure that glides in, and closes with Escape or a click outside. */
export default function ToolsMenu({
    variant,
    className,
    triggerClassName,
}: Props) {
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
                    className={cn('gap-1.5', triggerClassName)}
                >
                    Herramientas
                    <ChevronDown
                        aria-hidden
                        className={cn(
                            'size-3.5 transition-transform duration-200 ease-out motion-reduce:transition-none',
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
            <AnimatePresence>
                {open && (
                    <ToolsMenuPanel
                        key="panel"
                        floating={variant === 'bar'}
                        onPick={close}
                    />
                )}
            </AnimatePresence>
        </div>
    );
}
