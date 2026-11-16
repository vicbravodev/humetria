import { Head } from '@inertiajs/react';
import PortalShell from '@/components/organization/portal-shell';
import PortalTeamList from '@/components/organization/portal-team-list';
import PortalTeamManager from '@/components/organization/portal-team-manager';
import WorkerHomeLink from '@/components/worker-home-link';
import type {
    Paginated,
    PortalTeamManager as Manager,
    PortalTeamPerson,
} from '@/types';

/**
 * «Mi equipo» in the person's portal, in usted: whom they report to, who reports to the same person and who reports
 * to them. Names, job titles and areas only.
 */
export default function PortalTeam({
    slug,
    company,
    manager,
    peers,
    reports,
}: {
    slug: string;
    company: string;
    name: string;
    manager: Manager | null;
    peers: Paginated<PortalTeamPerson>;
    reports: Paginated<PortalTeamPerson>;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Mi equipo" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <WorkerHomeLink slug={slug} />
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Mi equipo
                    </h1>
                    <p className="text-muted-foreground">
                        Su jefe directo, sus compañeros y quienes le reportan,
                        como los tiene registrados su empresa.
                    </p>
                </div>
                <PortalTeamManager manager={manager} />
                {(manager !== null || peers.total > 0) && (
                    <PortalTeamList
                        id="team-peers"
                        title="Sus compañeros"
                        people={peers}
                        empty="Nadie más reporta a la misma persona."
                    />
                )}
                {reports.total > 0 && (
                    <PortalTeamList
                        id="team-reports"
                        title="Quienes le reportan"
                        people={reports}
                        empty="Nadie le reporta."
                    />
                )}
            </div>
        </PortalShell>
    );
}
