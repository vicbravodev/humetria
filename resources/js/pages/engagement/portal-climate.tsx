import { Head } from '@inertiajs/react';
import { Target } from 'lucide-react';
import HeardWillDo from '@/components/engagement/portal/heard-will-do';
import PortalParticipation from '@/components/engagement/portal/portal-participation';
import SurveyShell from '@/components/engagement/survey/survey-shell';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import WorkerHomeLink from '@/components/worker-home-link';
import type { PortalClimate } from '@/types';

/**
 * «Nos dijeron / Haremos» in the person's portal, in usted: what the company heard in the climate survey and what it
 * will do about it, for the whole company and for the groups the person belongs to.
 */
export default function PortalClimatePage({
    slug,
    company,
    survey,
    participation,
    index,
    commitments,
}: PortalClimate) {
    return (
        <SurveyShell company={company} label="Personal">
            <Head title="Lo que dijeron y lo que haremos" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <WorkerHomeLink slug={slug} />
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Lo que dijeron y lo que haremos
                    </h1>
                    <p className="text-muted-foreground">
                        {company} escuchó a su gente en «{survey.name}». Esto es
                        lo que hará: quién responde y para cuándo. Los
                        resultados de cada grupo no se publican.
                    </p>
                </div>
                {participation !== null && (
                    <PortalParticipation
                        percent={participation.percent}
                        index={index}
                    />
                )}
                {commitments.total === 0 ? (
                    <EmptyState
                        icon={Target}
                        title="Sin compromisos para usted"
                    >
                        Por ahora la empresa no registró compromisos para su
                        centro, su área o su equipo.
                    </EmptyState>
                ) : (
                    <ul className="flex flex-col gap-4">
                        {commitments.data.map((commitment) => (
                            <HeardWillDo
                                key={commitment.id}
                                commitment={commitment}
                            />
                        ))}
                    </ul>
                )}
                <Pagination
                    page={commitments}
                    label="Páginas de compromisos"
                    pinned={false}
                />
            </div>
        </SurveyShell>
    );
}
