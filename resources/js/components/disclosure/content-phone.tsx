import type { FlowSection } from '@/lib/question-flow';

/**
 * «Así lo verán en su celular» (board V4-Difusion-Contenidos): the block as the page of the people shows it on a phone, each
 * part with its text or «…» while its question waits, and the way to complain under it.
 */
export default function ContentPhone({
    company,
    title,
    sections,
}: {
    company: string;
    title: string;
    sections: FlowSection[];
}) {
    return (
        <aside
            aria-labelledby="content-phone"
            className="flex flex-col gap-3 min-[1100px]:sticky min-[1100px]:top-6"
        >
            <h2
                id="content-phone"
                className="text-sm font-medium text-muted-foreground"
            >
                Así lo verán en su celular
            </h2>
            <div className="overflow-hidden rounded-[32px] border-[6px] border-foreground bg-card shadow-float">
                <div className="flex items-center justify-between gap-3 border-b px-4 py-3 text-caption">
                    <span className="truncate font-semibold">{company}</span>
                    <span className="text-muted-foreground">
                        Todos los centros
                    </span>
                </div>
                <div className="flex min-h-[420px] flex-col gap-3.5 px-4 py-5">
                    <p className="font-semibold">{title}</p>
                    {sections.map((section) => (
                        <div
                            key={section.key}
                            className="flex flex-col gap-0.5 text-sm"
                        >
                            <p className="font-semibold">{section.heading}</p>
                            <p className="whitespace-pre-line">
                                {section.text ?? '…'}
                            </p>
                        </div>
                    ))}
                    <span
                        aria-hidden
                        className="mt-auto inline-flex h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                    >
                        Presentar una queja o denuncia
                    </span>
                </div>
            </div>
        </aside>
    );
}
