/** Who answered, as each person reads it (board V2-N035-Compartir): below the minimum, never the count (criterio C-54). */
export function answeredLine(
    answered: number | null,
    minimum: number,
    center: string,
): string {
    if (answered === null) {
        return `Contestaron menos de ${minimum} personas de ${center}: con tan pocas no se muestran cifras.`;
    }

    return `Contestaron ${answered === 1 ? '1 persona' : `${answered} personas`} de ${center}. Gracias por su tiempo.`;
}

/** The themes that most need attention in one sentence: «violencia, carga de trabajo y jornada de trabajo». */
export function themesLine(themes: string[]): string | null {
    if (themes.length === 0) {
        return null;
    }

    const lower = themes.map((theme, index) =>
        index === 0 ? theme : theme.toLowerCase(),
    );
    const last = lower.at(-1) ?? '';

    return lower.length === 1
        ? `${last}.`
        : `${lower.slice(0, -1).join(', ')} y ${last}.`;
}
