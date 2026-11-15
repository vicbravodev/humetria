import type { ReactNode } from 'react';
import { countOf } from '@/lib/plural';
import type { StatementSheetData } from '@/types';

/**
 * The annual vacation statement (LFT art. 81) as it is read and printed on a letter sheet: who it is for, their seniority,
 * the days they are given, when to enjoy them and the premium as a percent.
 */
export default function StatementSheet({
    sheet,
    children,
}: {
    sheet: StatementSheetData;
    /** What RH reads of the acknowledgment, inside the document and not on paper. */
    children?: ReactNode;
}) {
    return (
        <article className="mx-auto flex w-full max-w-[760px] flex-col gap-7 card p-6 [page:certificate] md:p-12 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none">
            <header className="flex flex-col gap-2">
                <p className="text-sm font-semibold text-primary">
                    {sheet.company}
                </p>
                <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                    Constancia de vacaciones
                </h1>
            </header>
            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-[160px_minmax(0,1fr)]">
                <dt className="text-sm text-muted-foreground">Persona</dt>
                <dd>{sheet.name}</dd>
                <dt className="text-sm text-muted-foreground">Antigüedad</dt>
                <dd>
                    {countOf(sheet.years, 'año', 'años')}
                    <span className="block text-sm text-muted-foreground">
                        Desde el {sheet.startedOn}
                    </span>
                </dd>
                <dt className="text-sm text-muted-foreground">Vacaciones</dt>
                <dd>{countOf(sheet.days, 'día', 'días')}</dd>
                <dt className="text-sm text-muted-foreground">Disfrute</dt>
                <dd>
                    Desde el {sheet.enjoyFrom}; dentro de los seis meses
                    siguientes, antes del {sheet.enjoyBy}
                </dd>
                <dt className="text-sm text-muted-foreground">Prima</dt>
                <dd>{sheet.premiumPercent} % sobre los días de vacaciones</dd>
                <dt className="text-sm text-muted-foreground">Emitida el</dt>
                <dd>{sheet.issuedOn}</dd>
            </dl>
            <p className="text-sm text-muted-foreground">
                Esta constancia la entrega la empresa cada año, como lo pide el
                artículo 81 de la Ley Federal del Trabajo.
            </p>
            {children !== undefined && (
                <div className="rounded-xl bg-muted px-4 py-3 print:hidden">
                    {children}
                </div>
            )}
            <div className="mt-6 grid gap-1 border-t pt-3 sm:w-80">
                <p className="text-sm text-muted-foreground">
                    {sheet.acknowledgedOn === null
                        ? 'Nombre y firma de quien recibe'
                        : `Recibida por ${sheet.name} el ${sheet.acknowledgedOn}`}
                </p>
            </div>
        </article>
    );
}
