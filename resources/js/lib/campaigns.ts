import type {
    CampaignPerson,
    CampaignProgress,
    CampaignSummary,
    GenderQuota,
} from '@/types';

/** The guides of a campaign, written for display: "Guía I", "Guías I y III". */
export function instrumentsText(titles: string[]): string {
    if (titles.length <= 1) {
        return titles.join('');
    }

    const numerals = titles.map((title) => title.replace('Guía ', ''));

    return `Guías ${numerals.slice(0, -1).join(', ')} y ${numerals.at(-1) ?? ''}`;
}

/**
 * The Guía III sample of NOM-035 (III.1), rounded up: ⌈9604·N / (25·(N − 1) + 9604)⌉, in integers as the server
 * computes it. Null for 50 people or fewer, where Guía III does not apply.
 */
export function sampleSize(workers: number): number | null {
    if (workers <= 50) {
        return null;
    }

    const divisor = 25 * (workers - 1) + 9604;

    return Math.floor((9604 * workers + divisor - 1) / divisor);
}

export function progressCount(progress: CampaignProgress): string {
    return `${progress.completed} de ${progress.target}`;
}

export function progressCaption(progress: CampaignProgress): string {
    return progress.againstSample
        ? 'respuestas de la muestra requerida'
        : 'personas contestaron';
}

/** What is left to reach the target, or that it was reached. */
export function progressNote(progress: CampaignProgress): string {
    const left = progress.target - progress.completed;

    if (left <= 0) {
        return progress.againstSample
            ? 'Muestra completa'
            : 'Todas las personas contestaron';
    }

    return left === 1 ? 'Falta 1 respuesta' : `Faltan ${left} respuestas`;
}

/** The line under a campaign's name: its guides and its deadline, or when it closed. */
export function campaignMeta(campaign: CampaignSummary): string {
    const guides = instrumentsText(campaign.instruments);

    if (campaign.status === 'closed') {
        return `${guides} · cerró el ${campaign.closedOn ?? ''}`;
    }

    return campaign.closesOn === null
        ? `${guides} · borrador`
        : `${guides} · cierra el ${campaign.closesOn}`;
}

/** How many people of each gender the sample takes: "Mujeres 45 · Hombres 34 · Otro 1". */
export function quotasText(quotas: GenderQuota[]): string {
    return quotas.map((quota) => `${quota.label} ${quota.count}`).join(' · ');
}

/** Whether a person matches a search by name or area, ignoring case and accents: "jose" finds "José". */
export function matchesPerson(person: CampaignPerson, search: string): boolean {
    const normalize = (text: string) =>
        text
            .normalize('NFD')
            .replace(/\p{Diacritic}/gu, '')
            .toLowerCase()
            .trim();
    const wanted = normalize(search);

    return (
        wanted === '' ||
        normalize(`${person.name} ${person.area}`).includes(wanted)
    );
}
