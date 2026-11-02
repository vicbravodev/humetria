import { useEffect, useRef } from 'react';
import HumiAvatar from '@/components/assistant/humi-avatar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * The round button at the bottom right that opens Humi (Ctrl or Cmd + J does the same). When the panel closes the button
 * comes back and takes the focus, so a keyboard does not lose its place.
 */
export default function HumiLauncher({
    onOpen,
    takeFocus,
}: {
    onOpen: () => void;
    takeFocus: boolean;
}) {
    const button = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (takeFocus) {
            button.current?.focus();
        }
    }, [takeFocus]);

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    ref={button}
                    type="button"
                    onClick={onOpen}
                    aria-label="Abrir a Humi"
                    aria-keyshortcuts="Control+J Meta+J"
                    className="humi-pop fixed right-4 bottom-4 z-40 flex size-12 items-center justify-center rounded-full border bg-card shadow-float transition-transform duration-150 ease-out hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none print:hidden"
                >
                    <HumiAvatar size={40} animated />
                </button>
            </TooltipTrigger>
            <TooltipContent side="left">
                Pregúntale a Humi{' '}
                <span className="text-background/70">Ctrl J</span>
            </TooltipContent>
        </Tooltip>
    );
}
