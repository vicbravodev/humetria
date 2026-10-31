import { Factory, ShieldCheck } from 'lucide-react';
import FocusCard from '@/components/results/focus-card';
import ReportCallout from '@/components/results/report-callout';
import ReportSection from '@/components/results/report-section';
import { focusDomains, rankByHighRisk } from '@/lib/report';
import { highRisk, percentText } from '@/lib/results';
import type { NomReport, RiskLevelInfo } from '@/types';

/** Where to act first (section 1): the worst domains, the area most exposed and the domain that holds up best. */
export default function ReportFocus({
    report,
    levels,
}: {
    report: NomReport;
    levels: RiskLevelInfo[];
}) {
    const { results, areas } = report;
    const focus = focusDomains(results.domains);
    const strength = rankByHighRisk(results.domains).at(-1);
    const [exposed] = rankByHighRisk(
        areas.visible.map((area) => ({ ...area, levels: area.final })),
    );
    const categoryOf = (key: string | null): string =>
        results.categories.find((category) => category.key === key)?.label ??
        '';

    return (
        <ReportSection
            id="focos"
            title="1. Dónde actuar primero"
            aside="Ordenado por % en alto o muy alto"
            description="Los dominios con más cuestionarios en riesgo alto o muy alto. Son el punto de partida del Programa de intervención."
            pageBreak
        >
            {focus.length === 0 ? (
                <p className="text-muted-foreground">
                    Ningún dominio tiene cuestionarios en riesgo alto o muy
                    alto: no hay un foco que atender primero.
                </p>
            ) : (
                <div className="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-4">
                    {focus.map((domain, index) => (
                        <FocusCard
                            key={domain.key}
                            domain={domain}
                            category={categoryOf(domain.category)}
                            rank={index + 1}
                            levels={levels}
                        />
                    ))}
                </div>
            )}
            <div className="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-4">
                {exposed !== undefined && highRisk(exposed.final) > 0 && (
                    <ReportCallout
                        icon={Factory}
                        caption="Área más expuesta"
                        tone="risk"
                    >
                        <b className="font-semibold">{exposed.name}</b> ·{' '}
                        {highRisk(exposed.final)} de {exposed.answered} en alto
                        o muy alto
                    </ReportCallout>
                )}
                {strength !== undefined && !focus.includes(strength) && (
                    <ReportCallout
                        icon={ShieldCheck}
                        caption="Fortaleza"
                        tone="strength"
                    >
                        <b className="font-semibold">{strength.label}</b> · solo{' '}
                        {percentText(
                            highRisk(strength.levels),
                            results.answered,
                        )}{' '}
                        en alto o muy alto
                    </ReportCallout>
                )}
            </div>
        </ReportSection>
    );
}
