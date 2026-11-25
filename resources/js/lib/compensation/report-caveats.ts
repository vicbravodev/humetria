import { premiumNotice, reportAccuracy } from '@/lib/compensation/report-notes';
import type { ScenarioReport } from '@/types';

const LIST = new Intl.ListFormat('es-MX', { type: 'conjunction' });

/** What a reader must know before trusting the number; `action` is the way to fix it for whoever manages the contract. */
export type Caveat = {
    key: string;
    title: string;
    detail: string;
    action: 'complete' | 'premium' | null;
};

/** What this report does not calculate, always said. */
const ALWAYS: Caveat[] = [
    {
        key: 'rates',
        title: 'Tasas y valores del año.',
        detail: 'Las cuotas, la UMA y el salario mínimo son los vigentes en la fecha en que entra el aumento, y rigen los doce meses del cálculo.',
        action: null,
    },
    {
        key: 'rounding',
        title: 'Redondeo.',
        detail: 'Cada monto se calcula al centavo por persona y por mes, y el SBC se trunca al centavo como lo hace el IMSS: la suma de las partes es el total.',
        action: null,
    },
    {
        key: 'scope',
        title: 'Lo que no se calcula.',
        detail: 'Horas extra, bonos variables, nuevas contrataciones del año y prestaciones fuera del escenario. Tampoco sustituye una valuación actuarial (NIF D-3) ni es asesoría legal o fiscal.',
        action: null,
    },
];

/** «Supuestos y salvedades»: the data that is missing, the premium of class I, the values not yet published and what is never calculated. */
export function reportCaveats(report: ScenarioReport): Caveat[] {
    const accuracy = reportAccuracy(report);
    const premium = premiumNotice(report);
    const { stale } = report.warnings;

    return [
        ...(accuracy === null
            ? []
            : [
                  {
                      key: 'accuracy',
                      title: accuracy.title,
                      detail: accuracy.detail,
                      action: 'complete' as const,
                  },
              ]),
        ...(premium === null
            ? []
            : [{ key: 'premium', ...premium, action: 'premium' as const }]),
        ...(stale.length === 0
            ? []
            : [
                  {
                      key: 'stale',
                      title: 'Valores fiscales sin publicar.',
                      detail: `Aún no se publican ${LIST.format(stale)}; se usa el último que se conoce.`,
                      action: null,
                  },
              ]),
        ...ALWAYS,
    ];
}

/** How many of the caveats change the number (not the ones that are always there): for the pointer under the summary. */
export function openCaveats(report: ScenarioReport): number {
    return reportCaveats(report).filter((caveat) => caveat.action !== null)
        .length;
}
