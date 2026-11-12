import type { Tone } from '@/lib/org-chart/chart-view';

/** What the card under a dragged person says about the drop. */
export type DropTone = 'valid' | 'invalid';

const BASE =
    'flex h-[72px] w-full items-center gap-3 card p-3 text-left text-card-foreground transition-[border-color,opacity] duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

const TONES: Record<Tone, string> = {
    normal: 'border-border hover:border-input',
    selected: 'border-2 border-primary shadow-float',
    path: 'border-primary bg-accent',
    dimmed: 'border-border opacity-45',
};

/** The classes of a person's card: how it is lit, whether it asks for attention (amber), and what a drop on it would do. */
export function cardClasses({
    tone,
    attention,
    drop,
}: {
    tone: Tone;
    attention: boolean;
    drop: DropTone | null;
}): string {
    if (drop === 'valid') {
        return `${BASE} border-2 border-dashed border-primary bg-accent`;
    }

    if (drop === 'invalid') {
        return `${BASE} cursor-not-allowed border-dashed border-input opacity-60`;
    }

    const amber =
        attention && (tone === 'normal' || tone === 'dimmed')
            ? 'bg-warning text-warning-foreground'
            : '';

    return `${BASE} ${TONES[tone]} ${amber}`;
}

/** The tag over the card that is about to receive a person. */
export function dropLabel(drop: DropTone | null, name: string): string | null {
    if (drop === null) {
        return null;
    }

    return drop === 'valid'
        ? `Soltar para que ${name} sea su jefe`
        : 'No se puede soltar aquí';
}
