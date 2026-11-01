/** The warning for a section's uncovered requirements, or null when it covers them all. */
export function coverageMessage(labels: string[]): string | null {
    if (labels.length === 0) {
        return null;
    }

    return `${labels.length === 1 ? 'Falta una pregunta' : 'Faltan preguntas'} para: ${labels.join('; ')}.`;
}

export function totalMissing(missing: Record<string, string[]>): number {
    return Object.values(missing).reduce((sum, list) => sum + list.length, 0);
}
