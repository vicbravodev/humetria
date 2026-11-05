import type { TenureFormRow } from '@/lib/compensation/benefits';
import { countOf, plural } from '@/lib/plural';

/** How the scenarios list is narrowed, as the server sends it back. */
export type ScenarioFilters = { status: 'active' | 'archived' };

/** Every scenario of the account at a glance. */
export type ScenarioCounts = { active: number; archived: number };

/** A benefit whose terms a scenario changes, as the editor types it. */
export type TermChangeForm = {
    benefit_id: string;
    name: string;
    /** What the value is of: "días de salario", "% del salario", "pesos", "UMA". */
    unit: string;
    value: string;
    /** The terms in force on the scenario's date; none when the benefit is new to the contract. */
    current_text: string | null;
    tenure_table: TenureFormRow[];
};

type BenefitOption = { value: string; label: string; unit: string };

/** The longest horizon a scenario can have, which is also how long its retroactivity can be. */
const LONGEST_HORIZON = 5;

/** The query string for the tab: the active tab has a plain address. */
export function scenarioQuery(
    status: ScenarioFilters['status'],
): Partial<Record<'status', string>> {
    return status === 'archived' ? { status } : {};
}

/** "2 escenarios activos y 1 archivado." */
export function scenariosHeadline({
    active,
    archived,
}: ScenarioCounts): string {
    if (active === 0 && archived === 0) {
        return 'Todavía no hay escenarios.';
    }

    const actives = `${countOf(active, 'escenario', 'escenarios')} ${plural(active, 'activo', 'activos')}`;

    return archived === 0
        ? `${actives}.`
        : `${actives} y ${countOf(archived, 'archivado', 'archivados')}.`;
}

/** "5 % sindicalizados · 3 % confianza", or one raise for both when the trusted staff copy it. */
export function raiseSummary(
    unionized: string,
    trusted: string | null,
): string {
    return trusted === null
        ? `${unionized} % sindicalizados y confianza`
        : `${unionized} % sindicalizados · ${trusted} % confianza`;
}

/** The most months of retroactivity a horizon (a number of years as typed) allows. */
export function retroactiveLimit(horizonYears: string): number {
    const years = Number(horizonYears);

    return (
        (Number.isInteger(years) && years > 0 ? years : LONGEST_HORIZON) * 12
    );
}

/** The changes with one more benefit; one already there, or the empty option, changes nothing. */
export function addTermChange(
    changes: TermChangeForm[],
    option: BenefitOption,
): TermChangeForm[] {
    return option.value === '' ||
        changes.some((change) => change.benefit_id === option.value)
        ? changes
        : [
              ...changes,
              {
                  benefit_id: option.value,
                  name: option.label,
                  unit: option.unit,
                  value: '',
                  current_text: null,
                  tenure_table: [],
              },
          ];
}

export function removeTermChange(
    changes: TermChangeForm[],
    benefitId: string,
): TermChangeForm[] {
    return changes.filter((change) => change.benefit_id !== benefitId);
}

export function setTermChange(
    changes: TermChangeForm[],
    benefitId: string,
    patch: Partial<TermChangeForm>,
): TermChangeForm[] {
    return changes.map((change) =>
        change.benefit_id === benefitId ? { ...change, ...patch } : change,
    );
}
