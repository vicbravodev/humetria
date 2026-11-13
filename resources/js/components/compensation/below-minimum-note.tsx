import { TriangleAlert } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A salary under the general minimum wage, as a one-line chip in its cell (the row keeps its height). The whole reason is
 * in a tooltip for the mouse and the keyboard, and in the chip itself for a screen reader.
 */
export default function BelowMinimumNote({ reason }: { reason: string }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    tabIndex={0}
                    data-slot="below-minimum"
                    className="mt-1 flex w-fit items-center gap-1 rounded-md bg-warning px-2 py-0.5 text-caption font-medium whitespace-nowrap text-warning-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <TriangleAlert
                        aria-hidden="true"
                        className="size-3.5 shrink-0"
                    />
                    Bajo el salario mínimo.
                    <span className="sr-only"> {reason}</span>
                </span>
            </TooltipTrigger>
            <TooltipContent className="max-w-72">{reason}</TooltipContent>
        </Tooltip>
    );
}
