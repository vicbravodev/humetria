import {
    highRisk,
    percentText,
    rankBySeverity,
    riskVerdict,
    total,
} from '@/lib/results';
import type {
    AreaResults,
    NomReport,
    ReportInterventionState,
    RiskGroup,
    RiskResults,
} from '@/types';

/** A finding of the summary: where the risk is, or what holds up. */
export type Finding = {
    tone: 'risk' | 'strength';
    title: string;
    detail: string;
};

/**
 * What HR still has to fill in before handing the report over, or nothing when it is complete. The recommendations are
 * missing only while the Programa de intervención is too: when it is there, or the norm asks for none, incise g does not wait.
 */
export function pendingText(
    report: Pick<NomReport, 'conclusions' | 'recommendations' | 'responsible'>,
    intervention: ReportInterventionState = 'missing',
): string | null {
    const missing = [
        report.conclusions === null ? 'conclusiones' : null,
        report.recommendations === null && intervention === 'missing'
            ? 'recomendaciones'
            : null,
        report.responsible === null ? 'responsable de la evaluación' : null,
    ].filter((part) => part !== null);

    if (missing.length === 0) {
        return null;
    }

    const list = new Intl.ListFormat('es-MX').format(missing);
    const parts =
        missing.length === 1
            ? 'Falta 1 dato'
            : `Faltan ${missing.length} datos`;

    return `${parts} para entregar el informe: ${list}.`;
}

function focusFinding(domain: RiskGroup): Finding {
    const answered = total(domain.levels);
    const high = highRisk(domain.levels);
    const veryHigh = domain.levels[4] ?? 0;
    const clause = veryHigh > 0 ? `; ${veryHigh} de ellos en muy alto` : '';

    return {
        tone: 'risk',
        title: `${domain.label} es el foco principal`,
        detail: `${high} de ${answered} cuestionarios (${percentText(high, answered)}) quedan en alto o muy alto${clause}.`,
    };
}

function areaFinding(areas: AreaResults): Finding | null {
    const [worst] = rankBySeverity(
        areas.visible.map((area) => ({ ...area, levels: area.final })),
    );

    if (areas.visible.length < 2 || worst === undefined) {
        return null;
    }

    return {
        tone: 'risk',
        title: `${worst.name} concentra el riesgo`,
        detail: `${highRisk(worst.final)} de ${worst.answered} cuestionarios del área quedan en alto o muy alto.`,
    };
}

function strengthFinding(domain: RiskGroup): Finding {
    const high = highRisk(domain.levels);

    // A strength has nobody in Alto or Muy alto; with someone there it is only the lowest risk of the lot.
    if (high > 0) {
        return {
            tone: 'strength',
            title: `${domain.label} es el dominio con menor riesgo`,
            detail: `Solo ${percentText(high, total(domain.levels))} en alto o muy alto: es el que menos riesgo concentra.`,
        };
    }

    return {
        tone: 'strength',
        title: `${domain.label} es la fortaleza`,
        detail: 'Ningún cuestionario en alto o muy alto. Es la base para construir el programa.',
    };
}

/** The three things to read first: the worst domain, the area that concentrates the risk, and the strength. */
export function keyFindings(
    results: RiskResults,
    areas: AreaResults,
): Finding[] {
    const ranked = rankBySeverity(results.domains);
    const worst = ranked[0];
    const best = ranked.at(-1);

    if (
        worst === undefined ||
        best === undefined ||
        highRisk(worst.levels) === 0
    ) {
        return [
            {
                tone: 'strength',
                title: 'Ningún dominio en riesgo alto o muy alto',
                detail: riskVerdict(results.final),
            },
        ];
    }

    return [
        focusFinding(worst),
        areaFinding(areas),
        best === worst ? null : strengthFinding(best),
    ].filter((finding) => finding !== null);
}
