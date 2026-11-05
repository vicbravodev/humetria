import type { ReactNode } from 'react';

export type CertificateCenter = {
    name: string;
    legalName: string;
    rfc: string;
    address: string;
};

/**
 * A certificate that a standard does not apply to a work center (rediseño v2, decisiones A2 y D6), as it is printed on a
 * letter sheet: the standard, the company and the center, what was declared, when and by whom.
 */
export default function CertificateSheet({
    standard,
    center,
    declaredOn,
    declaredBy,
    children,
}: {
    standard: string;
    center: CertificateCenter;
    declaredOn: string;
    declaredBy: string | null;
    /** What was declared, in one paragraph. */
    children: ReactNode;
}) {
    return (
        <article className="mx-auto flex w-full max-w-[760px] flex-col gap-7 card p-6 [page:certificate] md:p-12 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none">
            <header className="flex flex-col gap-2">
                <p className="text-sm font-semibold text-primary">{standard}</p>
                <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                    Constancia de que la norma no aplica
                </h1>
            </header>
            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-[160px_minmax(0,1fr)]">
                <dt className="text-sm text-muted-foreground">Empresa</dt>
                <dd>
                    {center.legalName} · RFC {center.rfc}
                </dd>
                <dt className="text-sm text-muted-foreground">
                    Centro de trabajo
                </dt>
                <dd>
                    {center.name}
                    <span className="block text-sm text-muted-foreground">
                        {center.address}
                    </span>
                </dd>
                <dt className="text-sm text-muted-foreground">Declarado el</dt>
                <dd>{declaredOn}</dd>
                <dt className="text-sm text-muted-foreground">Declaró</dt>
                <dd>{declaredBy ?? 'Una persona que ya no tiene cuenta'}</dd>
            </dl>
            <p className="text-lg leading-7 text-pretty">{children}</p>
            <p className="text-sm text-muted-foreground">
                Si esto cambia, se contesta de nuevo en Humetría y la ruta de la
                norma empieza en ese centro. Esta constancia guarda la
                declaración tal como se hizo, con su fecha.
            </p>
            <div className="mt-6 grid gap-1 border-t pt-3 sm:w-80">
                <p className="text-sm text-muted-foreground">
                    Nombre y firma de quien declara
                </p>
            </div>
        </article>
    );
}
