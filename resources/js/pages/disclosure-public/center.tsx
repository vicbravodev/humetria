import { Head, Link } from '@inertiajs/react';
import PublicComplaintCard from '@/components/disclosure/public-complaint-card';
import PublicBlocks from '@/components/disclosure/public-blocks';
import PublicPolicyCard from '@/components/disclosure/public-policy-card';
import PublicResultsCard from '@/components/disclosure/public-results-card';
import WorkersShell from '@/components/disclosure/workers-shell';
import { show } from '@/routes/workers';
import type { PublicCenterProps } from '@/types';

/**
 * The page of the people of a center, on their phone (board V4-Pagina-Personas): the complaint first, the policy, the results in
 * plain words and the rest of 5.7 folded. In «usted»; it keeps nothing of who opens it (criterio C-55).
 */
export default function PublicCenter({
    token,
    company,
    center,
    others,
    blocks,
    measures,
    channel,
    results,
    policy,
}: PublicCenterProps) {
    const complaint = blocks['d'] ?? null;
    const later =
        policy === null || results === null || results.published === null;

    return (
        <WorkersShell
            company={company}
            aside={
                others && (
                    <Link
                        href={show(token)}
                        className="touch-hit shrink-0 text-sm font-medium text-primary"
                    >
                        Otro centro
                    </Link>
                )
            }
        >
            <Head title={`${center.name} · ${company}`} />
            <div className="flex w-full max-w-xl flex-col gap-4">
                <div className="flex flex-col gap-1.5 pb-1">
                    <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                        Para las personas trabajadoras
                    </p>
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        {center.name}
                    </h1>
                    <p className="text-muted-foreground">
                        Lo que la empresa le informa sobre su bienestar en el
                        trabajo.
                    </p>
                </div>
                {channel !== null && (
                    <PublicComplaintCard channel={channel} block={complaint} />
                )}
                {policy === null ? (
                    <section className="card p-5 text-muted-foreground">
                        Este centro de trabajo todavía no publica su política de
                        prevención.
                    </section>
                ) : (
                    <PublicPolicyCard policy={policy} />
                )}
                {results !== null && <PublicResultsCard results={results} />}
                <PublicBlocks
                    blocks={blocks}
                    measures={measures}
                    channelOpen={channel !== null}
                />
                {later && (
                    <p className="pt-1 text-sm text-muted-foreground">
                        La empresa publicará aquí más información: los
                        resultados de la evaluación y las medidas que toma.
                    </p>
                )}
            </div>
        </WorkersShell>
    );
}
