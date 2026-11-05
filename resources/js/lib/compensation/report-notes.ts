import { describeDate } from '@/lib/calendar-date';
import { retroText } from '@/lib/compensation/start';
import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

const LIST = new Intl.ListFormat('es-MX', { type: 'conjunction' });

/** The datum an employer line could not be calculated without. */
function lineDatum(line: string): string {
    if (line === 'IMSS: riesgo de trabajo') {
        return 'tu prima de riesgo de trabajo';
    }

    return line === 'Impuesto sobre nómina'
        ? 'el estado de las personas, para el impuesto sobre nómina'
        : line.charAt(0).toLocaleLowerCase('es-MX') + line.slice(1);
}

/** The one notice of accuracy of the report (C6): how many data keep the number from being exact, and which; none when all are there. */
export function reportAccuracy(
    report: ScenarioReport,
): { missing: number; title: string; detail: string } | null {
    const left = report.coverage.total - report.coverage.costed;
    const { uncosted, uncalculated } = report.warnings;
    const items = [
        ...(left > 0
            ? [
                  countOf(
                      left,
                      'persona sin datos completos en el padrón',
                      'personas sin datos completos en el padrón',
                  ),
              ]
            : []),
        ...(uncosted.length > 0
            ? [
                  countOf(
                      uncosted.length,
                      'categoría sin salario en el tabulador',
                      'categorías sin salario en el tabulador',
                  ),
              ]
            : []),
        ...uncalculated.map(lineDatum),
    ];
    const missing = left + uncosted.length + uncalculated.length;

    if (missing === 0) {
        return null;
    }

    return {
        missing,
        title: `${missing === 1 ? 'Falta' : 'Faltan'} ${countOf(missing, 'dato', 'datos')} para que el número sea exacto:`,
        detail: `${LIST.format(items)}.`,
    };
}

/** What goes under the annex: who is costed, the fiscal values not yet published and the individual salaries. */
export function annexNote(report: ScenarioReport): string {
    const { coverage, warnings } = report;
    const { individual } = warnings;
    const parts = [
        `${coverage.costed} de ${countOf(coverage.total, 'persona costeada', 'personas costeadas')}.`,
    ];

    if (warnings.stale.length > 0) {
        parts.push(
            `Aún no se publican algunos valores fiscales (${warnings.stale.join(', ')}); se usa el último que se conoce.`,
        );
    }

    if (individual.count > 0) {
        parts.push(
            `${countOf(individual.count, 'persona tiene', 'personas tienen')} sueldo individual (${individual.percent} % de la plantilla costeada).`,
        );
    }

    if (individual.count > 0 && individual.over_tenth) {
        parts.push(
            'Son más de la décima parte: es probable que el tabulador no describa la realidad.',
        );
    }

    return parts.join(' ');
}

export type ReportSectionLink = { id: string; label: string; title: string };

/** The parts of the report for its tabs and their titles, numbered after the summary; the other proposals only inside the account. */
export function reportSections(withOthers: boolean): ReportSectionLink[] {
    const parts = [
        ['costo', 'De dónde sale', 'De dónde sale el costo'],
        ['quien', 'Quién recibe', 'Quién recibe el aumento'],
        ['anios', 'Próximos años', 'Los próximos años'],
        ...(withOthers
            ? [['frente', 'Frente a otras', 'Frente a las otras propuestas']]
            : []),
        ['anexo', 'Anexo', 'Anexo: detalle por concepto'],
    ];

    return [
        { id: 'resumen', label: 'Resumen', title: 'Resumen' },
        ...parts.map(([id = '', label = '', title = ''], index) => ({
            id,
            label,
            title: `${index + 1}. ${title}`,
        })),
    ];
}

/** "Autopartes Demo · desde el 1 de enero de 2027 · 5 % sindicalizados · 3 % confianza · 2 meses de retroactivo". */
export function reportSubtitle(
    report: ScenarioReport,
    company: string | null,
): string {
    const { scenario } = report;
    const since = describeDate(scenario.effective_on);

    return [
        company,
        since === undefined ? null : `desde el ${since}`,
        scenario.raise_text,
        retroText(scenario.retroactive_months),
    ]
        .filter((part) => part !== null)
        .join(' · ');
}
