import type { Option } from '@/lib/option-search';
import type { AgreedWeek, WorkSchedule } from '@/types';

type Copy = { option: Option; schedule: AgreedWeek };

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const isShift = (value: unknown): value is WorkSchedule['shiftType'] =>
    value === 'day' || value === 'night' || value === 'mixed';

const isMode = (value: unknown): value is WorkSchedule['workMode'] =>
    value === 'onsite' || value === 'remote';

const numbers = (value: unknown, orNull: boolean) =>
    isRecord(value) &&
    Object.values(value).every(
        (one) => typeof one === 'number' || (orNull && one === null),
    );

/** A schedule as the server sends it, read without trusting it. */
function scheduleOf(value: unknown): AgreedWeek | null {
    if (
        !isRecord(value) ||
        !isShift(value['shiftType']) ||
        !isMode(value['workMode'])
    ) {
        return null;
    }

    const { minutes, starts } = value;

    if (!isRecord(minutes) || !numbers(minutes, false)) {
        return null;
    }

    const schedule = {
        shiftType: value['shiftType'],
        workMode: value['workMode'],
        minutes: Object.fromEntries(
            Object.entries(minutes).flatMap(([day, one]) =>
                typeof one === 'number' ? [[day, one]] : [],
            ),
        ),
    };

    if (starts === null) {
        return { ...schedule, starts: null };
    }

    return isRecord(starts) && numbers(starts, true)
        ? {
              ...schedule,
              starts: Object.fromEntries(
                  Object.entries(starts).map(([day, one]) => [
                      day,
                      typeof one === 'number' ? one : null,
                  ]),
              ),
          }
        : null;
}

/** The people the server found to copy from: each an option, with their schedule. */
export function copiesOf(body: unknown): Copy[] {
    return (Array.isArray(body) ? body : []).flatMap((item: unknown) => {
        if (
            !isRecord(item) ||
            typeof item['value'] !== 'string' ||
            typeof item['label'] !== 'string'
        ) {
            return [];
        }

        const schedule = scheduleOf(item['schedule']);

        return schedule === null
            ? []
            : [
                  {
                      option: { value: item['value'], label: item['label'] },
                      schedule,
                  },
              ];
    });
}

/**
 * Where «Igual que …» finds the people to copy from, on the server (`?q=`), keeping the schedule of each one found so
 * choosing them fills the form with it.
 */
export function scheduleCopies(url: string) {
    const found = new Map<string, AgreedWeek>();

    return {
        scheduleOf: (id: string) => found.get(id) ?? null,
        source: {
            initial: [],
            minLength: 0,
            load: async (query: string, signal: AbortSignal) => {
                const response = await fetch(
                    `${url}?${new URLSearchParams({ q: query }).toString()}`,
                    { headers: { Accept: 'application/json' }, signal },
                );

                if (!response.ok) {
                    throw new Error(`Options request failed: ${url}`);
                }

                const copies = copiesOf(await response.json());
                copies.forEach(({ option, schedule }) =>
                    found.set(option.value, schedule),
                );

                return copies.map(({ option }) => option);
            },
        },
    };
}
