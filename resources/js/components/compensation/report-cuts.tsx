import CutTable from '@/components/compensation/report-cut-table';
import ScenarioSection from '@/components/compensation/scenario-section';
import type { ReportCut } from '@/types';

/** The cost cut by area, by category and level, and by staff group. */
export default function ReportCuts({
    cuts,
}: {
    cuts: { areas: ReportCut[]; grades: ReportCut[]; groups: ReportCut[] };
}) {
    return (
        <ScenarioSection
            title="Dónde pesa"
            description="El mismo costo del primer año partido por área, por categoría y nivel y por grupo."
        >
            <CutTable caption="Por área" label="Área" cuts={cuts.areas} />
            <CutTable
                caption="Por categoría y nivel"
                label="Categoría y nivel"
                cuts={cuts.grades}
            />
            <CutTable caption="Por grupo" label="Grupo" cuts={cuts.groups} />
        </ScenarioSection>
    );
}
