import PolicySections from '@/components/disclosure/policy-sections';
import { policyHighlights } from '@/lib/disclosure-public';
import type { PublicCenterProps } from '@/types';

/**
 * «Nuestra política» (board V4-Pagina-Personas): the version in force, its first commitments and the whole text to unfold. The
 * page keeps nobody's visit, so whoever confirms that they read it does so from the personal link of their email (criterio
 * C-53); this card says where.
 */
export default function PublicPolicyCard({
    policy,
}: {
    policy: NonNullable<PublicCenterProps['policy']>;
}) {
    const highlights = policyHighlights(policy.sections);

    return (
        <section
            aria-labelledby="policy"
            className="flex flex-col gap-3 card p-5"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                <h2 id="policy" className="text-lg leading-7 font-semibold">
                    Nuestra política
                </h2>
                <span className="text-caption text-muted-foreground">
                    versión {policy.version} · {policy.publishedOn}
                </span>
            </div>
            {highlights.length > 0 && (
                <ul className="ml-5 flex list-disc flex-col gap-1 text-sm">
                    {highlights.map((highlight) => (
                        <li key={highlight}>{highlight}</li>
                    ))}
                </ul>
            )}
            <details className="group">
                <summary className="touch-hit w-fit cursor-pointer text-sm font-medium text-primary">
                    Leer completa
                </summary>
                <div className="pt-3">
                    <PolicySections sections={policy.sections} tone="person" />
                </div>
            </details>
            <p className="rounded-lg bg-muted px-3.5 py-2.5 text-sm text-muted-foreground">
                Para confirmar que la leyó, use el enlace personal que le llegó
                por correo. Esta página no guarda quién la visita.
            </p>
        </section>
    );
}
