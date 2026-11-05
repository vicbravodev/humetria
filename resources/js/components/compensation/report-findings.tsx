import type { Finding } from '@/lib/compensation/report';

/** «Hallazgos clave» (board V4-Comp-Reporte): what to read first, each a figure and its meaning in plain words. */
export default function ReportFindings({ findings }: { findings: Finding[] }) {
    if (findings.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="report-findings"
            className="flex break-inside-avoid flex-col gap-4 card p-5"
        >
            <h3
                id="report-findings"
                className="text-caption font-semibold tracking-[0.06em] text-primary uppercase"
            >
                Hallazgos clave
            </h3>
            <ul className="grid gap-5 md:grid-cols-3">
                {findings.map((finding) => (
                    <li key={finding.title} className="flex flex-col gap-1">
                        <span className="text-2xl leading-8 font-semibold text-primary tabular-nums">
                            {finding.figure}
                        </span>
                        <p className="text-sm">
                            <strong className="font-semibold">
                                {finding.title}
                            </strong>{' '}
                            <span className="text-muted-foreground">
                                {finding.detail}
                            </span>
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}
