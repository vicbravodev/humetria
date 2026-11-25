import { describeDate } from '@/lib/calendar-date';
import { retroText } from '@/lib/compensation/start';
import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

const LIST = new Intl.ListFormat('es-MX', { type: 'conjunction' });

/** The datum an employer line could not be calculated without. */
function lineDatum(line: string, average: boolean): string {
    if (line === 'IMSS: riesgo de trabajo') {
        return 'tu prima de riesgo de trabajo';
    }

    return line === 'Impuesto sobre nómina'
        ? `el estado ${average ? 'donde trabajan' : 'de las personas'}, para el impuesto sobre nómina`
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
        ...uncalculated.map((line) =>
            lineDatum(line, report.scenario.source === 'average'),
        ),
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

/** "plantilla promedio de 500 personas a $15,000.00 al mes": what an average payroll is made of; null for a scenario of the roster. */
function averageText(
    average: ScenarioReport['scenario']['average'],
): string | null {
    return average === null
        ? null
        : `plantilla promedio de ${countOf(average.workers, 'persona', 'personas')} a ${average.monthly_salary_text} al mes`;
}

/** The notice of an estimate made with the work risk premium of class I, because the company has not captured its own; null otherwise. */
export function premiumNotice(
    report: ScenarioReport,
): { title: string; detail: string } | null {
    return report.warnings.class_one_premium
        ? {
              title: 'Riesgo de trabajo con la prima media de clase I.',
              detail: 'Tu empresa aún no captura la suya: la real puede ser mayor y subir el costo.',
          }
        : null;
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

/** The label and title of the part that weighs the raise against others: the ladder, the other proposals or both; none without them. */
function frontTitle(ladder: boolean, others: boolean): [string, string] | null {
    if (ladder && others) {
        return ['Otros niveles', 'Frente a otros niveles y otras propuestas'];
    }

    if (ladder) {
        return ['Otros niveles', 'Frente a otros niveles de aumento'];
    }

    return others ? ['Otras propuestas', 'Frente a otras propuestas'] : null;
}

export type ReportSectionLink = { id: string; label: string; title: string };

/**
 * The parts of the report for its tabs and their titles, numbered after the summary: who receives the raise only for a payroll of the
 * roster (an average payroll has one row), the other proposals only inside the account.
 */
export function reportSections(options: {
    quien: boolean;
    ladder: boolean;
    others: boolean;
}): ReportSectionLink[] {
    const front = frontTitle(options.ladder, options.others);
    const parts = [
        ['costo', 'De dónde sale', 'De dónde sale el costo'],
        ['calculo', 'El cálculo', 'El cálculo paso a paso'],
        ...(options.quien
            ? [['quien', 'Quién recibe', 'Quién recibe el aumento']]
            : []),
        ...(front === null ? [] : [['frente', front[0], front[1]]]),
        ['anios', 'Próximos años', 'Los próximos años'],
        ['evaluar', 'Qué más evaluar', 'Qué más evaluar'],
        ['supuestos', 'Supuestos', 'Supuestos y salvedades'],
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

/** Where the times of the app are told: the platform's display zone. */
const ZONE = 'hora de la Ciudad de México';

/**
 * What the notice of a shared report says: who shared it, when it ends (with the zone), and how the privacy of the people is kept: a
 * payroll of the roster folds the groups under the company's real minimum into «Otros»; an average payroll is an estimate with no
 * data of any person.
 */
export function sharedNotice(
    report: ScenarioReport,
    company: string | null,
    share: { expiresAt: string | null; minimum: number | null },
): string {
    const { average } = report.scenario;
    const { expiresAt, minimum } = share;
    const head = `${company} compartió este reporte contigo. Vence el ${expiresAt} (${ZONE}).`;

    if (average !== null) {
        return `${head} Es una estimación sobre una plantilla promedio de ${countOf(average.workers, 'persona', 'personas')} y no incluye datos de ninguna persona.`;
    }

    return minimum === null || minimum <= 1
        ? head
        : `${head} Por privacidad, los grupos con menos de ${minimum} personas se muestran juntos como «Otros».`;
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
        averageText(scenario.average),
        since === undefined ? null : `desde el ${since}`,
        scenario.raise_text,
        retroText(scenario.retroactive_months),
    ]
        .filter((part) => part !== null)
        .join(' · ');
}
