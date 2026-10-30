import { LockKeyhole } from 'lucide-react';
import AreaCard from '@/components/results/area-card';
import ReportSection from '@/components/results/report-section';
import { hiddenQuestionnaires } from '@/lib/report';
import type { NomReport } from '@/types';

/** Results by area (section 3): each area shown, and how many stay protected so nobody's answers can be told. */
export default function ReportAreas({ report }: { report: NomReport }) {
    const { areas, results } = report;
    const protectedCount = hiddenQuestionnaires(results.answered, areas);

    return (
        <ReportSection
            id="areas"
            title="3. Resultados por área"
            description="Calificación final por área de trabajo, agrupada en tres niveles para proteger el anonimato."
        >
            {areas.visible.length === 0 && (
                <p className="text-muted-foreground">
                    Ninguna área se muestra: se necesitan al menos 5
                    cuestionarios por área, y que el resto no se pueda deducir.
                </p>
            )}
            <div className="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-4">
                {areas.visible.map((area, index) => (
                    <AreaCard key={area.id} area={area} index={index} />
                ))}
                {areas.hidden > 0 && (
                    <div className="flex break-inside-avoid flex-col justify-center gap-2.5 rounded-lg border border-dashed border-input p-6">
                        <p className="flex items-center gap-2.5 font-semibold">
                            <LockKeyhole
                                aria-hidden="true"
                                className="size-5 text-primary"
                            />
                            {areas.hidden === 1
                                ? '1 área protegida'
                                : `${areas.hidden} áreas protegidas`}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {protectedCount} cuestionarios de áreas con menos de
                            5 respuestas, o que permitirían deducirlas. No se
                            muestran para que nadie pueda deducir resultados
                            individuales; sí cuentan en el total.
                        </p>
                    </div>
                )}
            </div>
        </ReportSection>
    );
}
