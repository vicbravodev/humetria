import type { RaiseUnit } from '@/lib/compensation/scenario-draft';
import { stepRaise } from '@/lib/compensation/scenario-draft';
import { countOf } from '@/lib/plural';

/** The values of «El aumento al salario» while they are moved. */
export type RaiseDraft = {
    unit: RaiseUnit;
    unionized: string;
    trusted: string;
    months: number;
    horizon: string;
    effectiveOn: string;
};

/** The longest retroactivity the editor offers (board V4-Comp-Escenario: «Hasta 36»), within the horizon. */
export function monthsLimit(horizon: string): number {
    const years = Number(horizon);

    return Math.min(36, Number.isInteger(years) && years > 0 ? years * 12 : 36);
}

/** "%" or "al mes": what the raise is written in. */
export function raiseSuffix(unit: RaiseUnit): string {
    return unit === 'percent' ? '%' : 'al mes';
}

/** "68 personas", or nothing while the first numbers arrive. */
export function peopleHint(
    people: number | undefined,
    extra = '',
): string | undefined {
    return people === undefined
        ? undefined
        : `${countOf(people, 'persona', 'personas')}${extra}`;
}

/** The raise of a scenario as the editor starts it: a new one at 0 %, the trusted copying the unionized (empty). */
export function initialRaiseDraft(
    scenario: {
        raise_unit: RaiseUnit;
        unionized_raise: string;
        trusted_raise: string;
        retroactive_months: number;
        horizon_years: number;
        effective_on: string;
    } | null,
    defaults: { effective_on: string; horizon_years: number },
): RaiseDraft {
    if (scenario === null) {
        return {
            unit: 'percent',
            unionized: '0',
            trusted: '',
            months: 0,
            horizon: String(defaults.horizon_years),
            effectiveOn: defaults.effective_on,
        };
    }

    return {
        unit: scenario.raise_unit,
        unionized: scenario.unionized_raise,
        trusted: scenario.trusted_raise,
        months: scenario.retroactive_months,
        horizon: String(scenario.horizon_years),
        effectiveOn: scenario.effective_on,
    };
}

/** The trusted raise one step up or down: an empty one, which copies the unionized raise, starts from it. */
export function stepTrusted(draft: RaiseDraft, direction: 1 | -1): string {
    return stepRaise(
        draft.trusted === '' ? draft.unionized : draft.trusted,
        direction,
        draft.unit,
    );
}

/** Under the trusted raise: whether it copies the unionized one, how many people and that it reaches individual salaries. */
export function trustedHint(
    draft: RaiseDraft,
    people: number | undefined,
): string {
    const copies = draft.trusted === '' ? 'Igual que sindicalizados · ' : '';

    return `${copies}${peopleHint(people, ' · ') ?? ''}también a quien tiene sueldo individual`;
}
