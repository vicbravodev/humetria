import { cn } from '@/lib/utils';
import type { StageState } from '@/types';

const TONES: Record<StageState, string> = {
    done: 'bg-primary',
    current: 'bg-card ring-2 ring-primary ring-inset',
    pending: 'bg-track',
};

/**
 * The six stages of a route as a short bar (board V2-Panel): filled when done, outlined where the center is, empty
 * ahead. The line beside it says the same in words, so the bar is not read aloud.
 */
export default function StageBar({ states }: { states: StageState[] }) {
    return (
        <div
            aria-hidden
            data-slot="stage-bar"
            className="grid w-28 grid-cols-6 gap-[3px]"
        >
            {states.map((state, index) => (
                <span
                    // The stages never move: their place is who they are.
                    key={`stage-${index + 1}`}
                    data-state={state}
                    className={cn('h-2 rounded-full', TONES[state])}
                />
            ))}
        </div>
    );
}
