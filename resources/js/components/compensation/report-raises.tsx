import ReportFixedTip from '@/components/compensation/report-fixed-tip';
import ReportPart from '@/components/compensation/report-part';
import ReportRaiseGroup from '@/components/compensation/report-raise-group';
import {
    fixedAlternative,
    raiseGroups,
} from '@/lib/compensation/report-charts';
import type { ScenarioReport } from '@/types';

/** «Quién recibe el aumento»: pesos a month by category, sindicalizados against confianza, and what a fixed amount would do. */
export default function ReportRaises({
    report,
    title,
    union,
    canTry,
}: {
    report: ScenarioReport;
    title: string;
    union: boolean;
    canTry: boolean;
}) {
    const { groups, scaleText } = raiseGroups(report, union);
    const alternative = fixedAlternative(report);
    const offsets = groups.map((_, index) =>
        groups
            .slice(0, index)
            .reduce((sum, group) => sum + group.rows.length, 0),
    );

    return (
        <ReportPart
            id="quien"
            title={title}
            description="Lo que recibe de más cada persona al mes, según su categoría del tabulador."
        >
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
                <div className="flex break-inside-avoid flex-col gap-5 card p-5 sm:p-6">
                    {groups.length === 0 ? (
                        <p className="text-muted-foreground">
                            Ninguna categoría se puede mostrar: o no tiene
                            salario en el tabulador, o tiene muy pocas personas
                            para mostrarla sin señalar a alguien.
                        </p>
                    ) : (
                        groups.map((group, index) => (
                            <ReportRaiseGroup
                                key={group.key}
                                group={group}
                                offset={offsets[index] ?? 0}
                            />
                        ))
                    )}
                    <p className="text-caption text-muted-foreground">
                        {scaleText}
                    </p>
                </div>
                {alternative !== null && (
                    <ReportFixedTip
                        alternative={alternative}
                        raise={report.scenario.unionized_raise}
                        canTry={canTry}
                    />
                )}
            </div>
        </ReportPart>
    );
}
