import { describeDate } from '@/lib/calendar-date';
import { signedText } from '@/lib/engagement/trend';
import type {
    EngagementBands,
    EngagementDriver,
    EngagementParticipation,
    EngagementPriority,
} from '@/types';

/** The sentence over the results: how many of every ten answers are favorable and, when there is one, where to act. */
export function resultsReading(
    index: number | null,
    priorities: EngagementPriority[],
): string | null {
    if (index === null) {
        return null;
    }

    const share = `${Math.round(index / 10)} de cada 10 respuestas sobre compromiso son favorables.`;
    const first = priorities[0];

    return first === undefined
        ? share
        : `${share} Donde más puedes mover la aguja es ${first.label}.`;
}

/** A theme with its priority, when it has one. */
export type Topic = {
    driver: EngagementDriver;
    priority: EngagementPriority | null;
};

/**
 * One list of themes in the order of action: the prioritized first, in their order; the rest folded below. Without
 * priorities, every theme in its own order.
 */
export function topicOrder(
    drivers: EngagementDriver[],
    priorities: EngagementPriority[],
): { main: Topic[]; rest: Topic[] } {
    const main = priorities.flatMap((priority) => {
        const found = drivers.find((one) => one.key === priority.driver);

        return found === undefined ? [] : [{ driver: found, priority }];
    });

    if (main.length === 0) {
        return {
            main: drivers.map((one) => ({ driver: one, priority: null })),
            rest: [],
        };
    }

    const chosen = new Set(main.map((topic) => topic.driver.key));

    return {
        main,
        rest: drivers
            .filter((one) => !chosen.has(one.key))
            .map((one) => ({ driver: one, priority: null })),
    };
}

/** The columns of the map by group: the first five themes in the order of action. */
export function mapColumns(
    drivers: EngagementDriver[],
    priorities: EngagementPriority[],
): EngagementDriver[] {
    const { main, rest } = topicOrder(drivers, priorities);

    return [...main, ...rest].slice(0, 5).map((topic) => topic.driver);
}

/** The tier of a figure on the map, in one hue: under 50, 50 to 69, 70 or more; none when it is hidden. */
export function tierOf(score: number | null): 'low' | 'mid' | 'high' | null {
    if (score === null) {
        return null;
    }

    if (score >= 70) {
        return 'high';
    }

    return score >= 50 ? 'mid' : 'low';
}

/**
 * "Se calcula restando: 19 − 46 = −27.": how the recommendation index comes out, only when the rounded shares add up
 * to it (otherwise the sum would confuse).
 */
export function subtractionText(
    bands: EngagementBands | null,
    index: number | null,
): string | null {
    if (
        bands === null ||
        index === null ||
        bands.promoters - bands.detractors !== index
    ) {
        return null;
    }

    return `Se calcula restando: ${bands.promoters} − ${bands.detractors} = ${signedText(index)}.`;
}

/** "168 de 212 contestaron · 79 %". */
export function participationText(of: EngagementParticipation): string {
    return `${of.responses} de ${of.invited} contestaron · ${of.percent} %`;
}

/** "Cerró el 22 de junio de 2026"; nothing while it is open. */
export function closedText(closedOn: string | null): string | null {
    return closedOn === null
        ? null
        : `Cerró el ${describeDate(closedOn) ?? closedOn}`;
}
