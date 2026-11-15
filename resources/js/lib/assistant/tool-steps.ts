const WHAT: Record<string, string> = {
    GetComplianceOverview: 'el estado de cumplimiento',
    GetCampaignProgress: 'el avance de campañas',
    GetRiskResults: 'los resultados de riesgo',
    ListPendingActions: 'los pendientes',
    SearchRegulations: 'la normativa',
    GetFirstSteps: 'tus primeros pasos',
};

/** What Humi is looking at, in a line over the answer: «Consultando…» while it runs, «Consulté…» once it is done. */
export function stepLabel(tool: string, done: boolean): string {
    const what = WHAT[tool] ?? 'la información';

    return done ? `Consulté ${what}` : `Consultando ${what}`;
}

/** The line that says the minimum of people per group was applied to results. */
export function minGroupStep(minGroup: number): string {
    return `Apliqué el mínimo de ${minGroup} personas por grupo`;
}
