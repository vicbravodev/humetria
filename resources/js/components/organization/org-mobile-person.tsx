import Initials from '@/components/humetria/initials';
import { placeText, teamText } from '@/lib/org-chart/labels';
import type { OrgNode } from '@/types';

/** The person the phone is centered on: a larger card outlined in the accent, with where they work and their team. */
export default function OrgMobilePerson({ node }: { node: OrgNode }) {
    return (
        <section
            aria-label={`Persona: ${node.name}`}
            data-slot="org-mobile-person"
            className="flex items-center gap-4 rounded-xl border-2 border-primary bg-card p-4 shadow-raised"
        >
            <Initials name={node.name} className="size-12 text-base" />
            <div className="min-w-0 flex-1">
                <h2 className="text-lg leading-6 font-semibold text-balance">
                    {node.name}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {[node.job_title, placeText(node)]
                        .filter((part) => part !== '')
                        .join(' · ')}
                </p>
                <p className="text-sm">
                    {node.direct_reports > 0
                        ? teamText(node)
                        : 'No tiene un equipo directo'}
                </p>
            </div>
        </section>
    );
}
