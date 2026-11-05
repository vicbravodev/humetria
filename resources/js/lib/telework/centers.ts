import type { CenterCard, TeleworkSummary } from '@/types';

/** The line under «Teletrabajo en tus centros»: how many people, in how many centers. */
export function centersIntro(summary: TeleworkSummary): string {
    const people =
        summary.teleworkers === 1
            ? '1 persona'
            : `${summary.teleworkers} personas`;
    const centers =
        summary.centers === 1 ? '1 centro' : `${summary.centers} centros`;
    const verb = summary.teleworkers === 1 ? 'trabaja' : 'trabajan';

    return `${people} en ${centers} ${verb} desde casa más del 40 % del tiempo. Algunas cosas se hacen una vez para toda la empresa; otras, en cada centro.`;
}

function lists(count: number): string {
    return count === 1 ? '1 lista de lugares' : `${count} listas de lugares`;
}

/** «Lo siguiente» of a center: its accident first, then the lists the Comisión has to review, then its stage. */
export function nextInCenter(card: CenterCard): string {
    if (!card.route.answered) {
        return 'Contesta si alguien teletrabaja en este centro';
    }

    if (card.accident !== null) {
        const accident = `Revisar el aviso de accidente de ${card.accident}`;

        return card.pendingReviews === 0
            ? accident
            : `${accident} y ${lists(card.pendingReviews)}`;
    }

    if (card.route.applies && card.pendingReviews > 0) {
        return `Que la Comisión revise ${lists(card.pendingReviews)}`;
    }

    return (
        card.route.status.charAt(0).toUpperCase() + card.route.status.slice(1)
    );
}

/** "2 de 6 etapas". */
export function stagesDone(card: CenterCard): string {
    return `${card.route.done.filter(Boolean).length} de 6 etapas`;
}
