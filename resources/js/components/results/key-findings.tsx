import ReportCaption from '@/components/results/report-caption';
import type { Finding } from '@/lib/report-findings';
import { cn } from '@/lib/utils';

/** The three things to read first, numbered: where the risk is, in terracotta, and what holds up, in jade. */
export default function KeyFindings({ findings }: { findings: Finding[] }) {
    return (
        <div className="flex flex-col gap-4">
            <ReportCaption>Hallazgos clave</ReportCaption>
            <ol className="flex flex-col gap-4">
                {findings.map((finding, index) => (
                    <li key={finding.title} className="flex gap-3.5">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'inline-flex size-7 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold',
                                finding.tone === 'risk'
                                    ? 'bg-risk-muy-alto-bg text-risk-muy-alto-fg'
                                    : 'bg-risk-bajo-bg text-risk-bajo-fg',
                            )}
                        >
                            {index + 1}
                        </span>
                        <div className="flex flex-col gap-0.5">
                            <p className="font-semibold">{finding.title}</p>
                            <p className="text-sm text-muted-foreground">
                                {finding.detail}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}
