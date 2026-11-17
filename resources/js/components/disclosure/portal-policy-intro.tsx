/** What the person's policy page opens with: the version and day in one line, the title and what they are asked to do. */
export default function PortalPolicyIntro({
    company,
    version,
    publishedOn,
    pending,
}: {
    company: string;
    /** The version in force; null while the center has none. */
    version: number | null;
    publishedOn: string | undefined;
    pending: boolean;
}) {
    return (
        <div className="flex flex-col gap-2">
            {version !== null && (
                <p className="text-sm font-medium tracking-wide text-primary uppercase">
                    Política · versión {version} · {publishedOn}
                </p>
            )}
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Política de prevención de riesgos psicosociales
            </h1>
            <p className="text-muted-foreground">
                {version === null
                    ? 'Su centro de trabajo todavía no publica su política de prevención de riesgos psicosociales.'
                    : `${company} se compromete a prevenir los riesgos psicosociales, la violencia laboral y a promover un entorno organizacional favorable. Léala completa${pending ? ' y confirme que la recibió' : ''}.`}
            </p>
        </div>
    );
}
