export type Delta = {
    points: number;
    /** Null when the groups are too small for an arrow: the points alone. */
    direction: 'up' | 'down' | 'same' | null;
};

/** A favorable share as the results write it: "72 %" (a non-breaking space keeps the sign with its number). */
export function favorableText(value: number | null): string {
    return value === null ? 'Sin respuestas suficientes' : `${value} %`;
}

/** Signed points of difference: "+7 pts", "−3 pts" (a real minus), "0 pts". */
export function pointsText(points: number): string {
    const sign = points > 0 ? '+' : points < 0 ? '−' : '';
    const unit = Math.abs(points) === 1 ? 'pt' : 'pts';

    return `${sign}${Math.abs(points)} ${unit}`;
}

/** How a result moved since the previous survey, in words that do not depend on the arrow's color. */
export function deltaText(delta: Delta): string {
    switch (delta.direction) {
        case 'up':
            return `Sube ${pointsText(delta.points)}`;
        case 'down':
            return `Baja ${pointsText(delta.points)}`;
        case 'same':
            return 'Igual';
        case null:
            return pointsText(delta.points);
    }
}

/** How a group stands against the whole company; nothing when either is hidden. */
export function versusCompanyText(points: number | null): string {
    if (points === null) {
        return '';
    }

    return points === 0
        ? 'Igual que la empresa'
        : `${pointsText(points)} frente a la empresa`;
}

/** The width of a bar, in percent, held between nothing and the whole track. */
export function shareWidth(value: number | null): number {
    return value === null ? 0 : Math.min(100, Math.max(0, value));
}
