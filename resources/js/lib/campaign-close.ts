import type { CampaignProgress } from '@/types';

/**
 * What closing now leaves unanswered (C-57): the measuring guide against its target, always, and the people whose Guía I is
 * still open. Nothing when the evaluation has no progress yet.
 */
export function closeShortfall(progress: CampaignProgress | null): {
    main: number;
    screening: number;
    againstSample: boolean;
} {
    if (progress === null) {
        return { main: 0, screening: 0, againstSample: false };
    }

    return {
        main: Math.max(progress.target - progress.completed, 0),
        screening: progress.screeningPending,
        againstSample: progress.againstSample,
    };
}

/** The lines the close dialog warns with: who is left, and whose Guía I is open, each on its own (C-57). */
export function closeWarnings(progress: CampaignProgress | null): string[] {
    const { main, screening, againstSample } = closeShortfall(progress);
    const lines: string[] = [];

    if (main > 0) {
        lines.push(
            againstSample
                ? `Faltan ${main} respuestas para el mínimo de la muestra que pide la norma. Considera extender la fecha o sortear reemplazos antes de cerrar.`
                : `${main === 1 ? 'Falta 1 persona' : `Faltan ${main} personas`} por contestar.`,
        );
    }

    if (screening > 0) {
        lines.push(
            `${screening === 1 ? '1 persona no ha' : `${screening} personas no han`} contestado la Guía I.`,
        );
    }

    return lines;
}
