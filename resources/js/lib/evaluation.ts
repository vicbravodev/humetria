import { fromCalendarDate, toCalendarDate } from '@/lib/calendar-date';
import { instrumentsText } from '@/lib/campaigns';
import type {
    AnswerDay,
    AreaParticipation,
    CampaignDetail,
    CampaignProgress,
    StandingCount,
} from '@/types';

/**
 * Two days as the design system writes them ("27 oct 2026", "10 nov 2026") joined as a span, without repeating what
 * they share: "27 oct al 10 nov 2026", "10 al 29 oct 2026".
 */
export function dateRange(from: string, to: string): string {
    const [day, month, year] = from.split(' ');
    const [, toMonth, toYear] = to.split(' ');

    if (year !== toYear) {
        return `${from} al ${to}`;
    }

    return month === toMonth ? `${day} al ${to}` : `${day} ${month} al ${to}`;
}

/** Why a work center takes the guides it takes (num. 4.4): how many people it has, as the norm groups them. */
export function staffBand(workers: number): string {
    if (workers <= 15) {
        return 'hasta 15 personas';
    }

    return workers <= 50 ? 'de 16 a 50 personas' : 'más de 50 personas';
}

/** A calendar date (Y-m-d) moved by some days; the same text when it is not a date. */
export function addDays(date: string, days: number): string {
    const day = fromCalendarDate(date);

    if (day === undefined) {
        return date;
    }

    day.setDate(day.getDate() + days);

    return toCalendarDate(day);
}

/** The deadlines offered next to the calendar: one, two and three weeks, the suggested one being two. */
export function weekChoices(
    suggested: string,
): { label: string; date: string }[] {
    return [1, 2, 3].map((weeks) => ({
        label: weeks === 1 ? '1 semana' : `${weeks} semanas`,
        date: addDays(suggested, (weeks - 2) * 7),
    }));
}

/** A share as a whole percent, 0 when there is nothing to divide. */
export function percent(part: number, whole: number): number {
    return whole <= 0 ? 0 : Math.round((part / whole) * 100);
}

/** The area with the smallest share answered that still has someone to remind; null when none has. */
export function laggingArea(
    areas: AreaParticipation[],
): AreaParticipation | null {
    return areas
        .filter((area) => area.pending > 0)
        .reduce<AreaParticipation | null>(
            (lowest, area) =>
                lowest === null ||
                area.answered / area.total < lowest.answered / lowest.total
                    ? area
                    : lowest,
            null,
        );
}

/** The words under the arc's figure: "de 60 contestaron", or against the sample "de 53 de la muestra". */
export function arcCaption(progress: CampaignProgress): string {
    return progress.againstSample
        ? `de ${progress.target} de la muestra`
        : `de ${progress.target} contestaron`;
}

/** How many days are left to answer, after today: "Quedan 11 días", "Queda 1 día", "Hoy es el último día". */
export function daysLeft(days: AnswerDay[]): string {
    const left = days.filter((day) => day.answers === null).length;

    if (left === 0) {
        return 'Hoy es el último día';
    }

    return left === 1 ? 'Queda 1 día' : `Quedan ${left} días`;
}

const monthName = new Intl.DateTimeFormat('es-MX', { month: 'long' });

/** The month a calendar date falls in, "octubre"; nothing when it is not a date. */
export function monthOf(date: string): string {
    const day = fromCalendarDate(date);

    return day === undefined ? '' : monthName.format(day);
}

/** The day of the month of a calendar date: "2026-11-03" → 3. */
export function dayOf(date: string): number {
    return Number(date.slice(8, 10));
}

/** The answers of the busiest day, at least 1 so the bars can be drawn against it. */
export function busiestDay(days: AnswerDay[]): number {
    return Math.max(1, ...days.map((day) => day.answers ?? 0));
}

/** A day's bar in pixels, of 98 for the busiest day: a hairline for none, never thinner than 4 for some. */
export function barHeight(answers: number, busiest: number): number {
    return answers === 0
        ? 2
        : Math.max(4, Math.round((answers / busiest) * 98));
}

/** What a day of the chart says aloud: "3 personas terminaron", or that it is still to come. */
export function dayAnswersText(answers: number | null): string {
    if (answers === null) {
        return 'por venir';
    }

    return answers === 1
        ? '1 persona terminó'
        : `${answers} personas terminaron`;
}

/** Pendientes, Contestaron and Todas always; the other standings only when someone is there or they are the view. */
export function visibleStandings(
    standings: StandingCount[],
    current: string,
): StandingCount[] {
    const always = ['pending', 'answered', 'all'];

    return standings.filter(
        (standing) =>
            always.includes(standing.value) ||
            standing.count > 0 ||
            standing.value === current,
    );
}

export type HeaderFact = { kind: 'people' | 'guides' | 'period'; text: string };

/** The facts under a launched evaluation's name: "60 personas", "Guías I y III (muestra de 52)", "27 oct al 10 nov 2026". */
export function headerFacts(campaign: CampaignDetail): HeaderFact[] {
    const guides = instrumentsText(campaign.instruments);
    const end = campaign.closedOn ?? campaign.closesOn;
    const facts: HeaderFact[] = [
        {
            kind: 'guides',
            text:
                campaign.sampleSize === null
                    ? guides
                    : `${guides} (muestra de ${campaign.sampleSize})`,
        },
    ];

    if (campaign.workers !== null) {
        facts.unshift({ kind: 'people', text: peopleText(campaign.workers) });
    }

    if (campaign.launchedOn !== null && end !== null) {
        facts.push({
            kind: 'period',
            text: dateRange(campaign.launchedOn, end),
        });
    }

    return facts;
}

/** "1 persona", "30 personas". */
export function peopleText(count: number): string {
    return count === 1 ? '1 persona' : `${count} personas`;
}

/** Whether a reminder can go to the people a list shows: only pending ones, or those whose link failed, and someone. */
export function remindable(status: string, count: number): boolean {
    return ['pending', 'failed'].includes(status) && count > 0;
}

export type HistoryItem = { title: string; date: string; done: boolean };

/**
 * What happened to an evaluation, from what is recorded: its draft, launch and close, and by when the next one is due
 * (num. 7.9). A Guía I campaign after an event starts launched and has no next one.
 */
export function historyItems(campaign: CampaignDetail): HistoryItem[] {
    const evaluation = campaign.kind === 'nom035_evaluation';
    const progress = campaign.progress;
    const items: (HistoryItem | false)[] = [
        evaluation && {
            title: 'Borrador creado',
            date: campaign.createdOn,
            done: true,
        },
        campaign.launchedOn !== null && {
            title: 'Lanzada',
            date: campaign.launchedOn,
            done: true,
        },
        campaign.closedOn !== null && {
            title:
                progress === null
                    ? 'Cerrada'
                    : `Cerrada · ${progress.completed} de ${progress.target}`,
            date: campaign.closedOn,
            done: true,
        },
        evaluation &&
            campaign.nextDueBy !== null && {
                title: 'Siguiente evaluación',
                date: `antes del ${campaign.nextDueBy}`,
                done: false,
            },
    ];

    return items.filter((item) => item !== false);
}

/** Everyone waiting with a link sent, added up from the areas; 0 for whoever does not see them. */
export function pendingTotal(areas: AreaParticipation[] | null): number {
    return (areas ?? []).reduce((sum, area) => sum + area.pending, 0);
}
