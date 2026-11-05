import { countOf, plural } from '@/lib/plural';
import type { SeatingCenterCard } from '@/types';

const EACH =
    'Cada centro lleva su ruta: puestos, asientos, evaluaciones y reglamento.';

/** The line under «Trabajo de pie en tus centros»: how many people work standing, in how many centers. */
export function centersIntro(summary: {
    people: number;
    centers: number;
}): string {
    if (summary.people === 0) {
        return `Todavía nadie trabaja de pie en tus centros. ${EACH}`;
    }

    return `${countOf(summary.people, 'persona', 'personas')} en ${countOf(summary.centers, 'centro', 'centros')} ${plural(summary.people, 'trabaja', 'trabajan')} de pie. ${EACH}`;
}

/** «Lo siguiente» of a center: answer «¿Te aplica?», what its route says next, or why it does not apply. */
export function nextInCenter(card: SeatingCenterCard): string {
    if (!card.route.answered) {
        return 'Contesta si alguien trabaja de pie en este centro';
    }

    return (
        card.route.status.charAt(0).toUpperCase() + card.route.status.slice(1)
    );
}
