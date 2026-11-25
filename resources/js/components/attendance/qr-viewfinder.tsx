import { Check } from 'lucide-react';
import type { ViewfinderState } from '@/lib/pwa/qr-scan-view';
import { cn } from '@/lib/utils';

const CORNERS = [
    'top-0 left-0 rounded-tl-3xl border-t-4 border-l-4',
    'top-0 right-0 rounded-tr-3xl border-t-4 border-r-4',
    'bottom-0 left-0 rounded-bl-3xl border-b-4 border-l-4',
    'bottom-0 right-0 rounded-br-3xl border-r-4 border-b-4',
];

/**
 * The window of the QR reader, over the camera: the scrim around it, four corners that change color with the state, a line that
 * sweeps while it looks and a check that pops in when the code is read. It only draws; it says nothing a screen reader
 * needs (the line of text below it does), and the sweep stands still with reduced motion.
 */
export default function QrViewfinder({ state }: { state: ViewfinderState }) {
    return (
        <div
            aria-hidden
            data-state={state}
            className={cn(
                'scanner-window pointer-events-none relative aspect-square w-[min(72vw,20rem)] rounded-3xl transition-colors duration-200',
                state === 'found' && 'bg-primary/10',
            )}
        >
            {CORNERS.map((corner) => (
                <span
                    key={corner}
                    className={cn('scanner-corner absolute size-12', corner)}
                />
            ))}
            {state === 'looking' && (
                <span className="scanner-sweep absolute inset-x-3 h-0.5 rounded-full" />
            )}
            {state === 'found' && (
                <span className="scanner-found absolute inset-0 flex items-center justify-center">
                    <span className="flex size-20 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg">
                        <Check className="size-10" strokeWidth={3} />
                    </span>
                </span>
            )}
        </div>
    );
}
