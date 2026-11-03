import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Info } from 'lucide-react';
import PersonShell from '@/components/disclosure/person-shell';
import PolicySections from '@/components/disclosure/policy-sections';
import PublicBlock from '@/components/disclosure/public-block';
import { show } from '@/routes/workers';
import type { PublicCenterProps } from '@/types';

export default function PublicCenter({
    token,
    company,
    center,
    others,
    policy,
}: PublicCenterProps) {
    return (
        <PersonShell
            company={company}
            label="Para las personas trabajadoras"
            icon={Info}
        >
            <Head title={`${center.name} · ${company}`} />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2">
                    {others && (
                        <Link
                            href={show(token)}
                            className="touch-hit inline-flex w-fit items-center gap-1 text-base font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ChevronLeft aria-hidden className="size-4" />
                            Otro centro de trabajo
                        </Link>
                    )}
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        {center.name}
                    </h1>
                    <p className="text-muted-foreground">
                        Lo que {company} le informa a quienes trabajan en este
                        centro: su política de prevención de riesgos
                        psicosociales y de violencia laboral.
                    </p>
                </div>
                <PublicBlock
                    id="block-a"
                    inciso="a"
                    title="Política de prevención"
                >
                    {policy === null ? (
                        <p className="text-muted-foreground">
                            Este centro de trabajo todavía no publica su
                            política de prevención.
                        </p>
                    ) : (
                        <>
                            <p className="text-base text-muted-foreground">
                                Versión {policy.version}, publicada el{' '}
                                {policy.publishedOn}.
                            </p>
                            <PolicySections
                                sections={policy.sections}
                                tone="person"
                            />
                        </>
                    )}
                </PublicBlock>
            </div>
        </PersonShell>
    );
}
