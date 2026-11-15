import OrgMiniPerson from '@/components/organization/org-mini-person';
import type { OrgNode } from '@/types';

/** «Su jefe» of the panel: a small card that opens their manager, or a line saying they are at the top. */
export default function OrgPanelManager({
    manager,
    onSelect,
}: {
    manager: OrgNode | null;
    onSelect: (id: string) => void;
}) {
    return (
        <section aria-labelledby="org-panel-manager" className="grid gap-2">
            <h3 id="org-panel-manager" className="text-sm font-semibold">
                Su jefe
            </h3>
            {manager === null ? (
                <p className="text-sm text-muted-foreground">
                    Nadie: está hasta arriba.
                </p>
            ) : (
                <OrgMiniPerson
                    node={manager}
                    direction="up"
                    onSelect={onSelect}
                />
            )}
        </section>
    );
}
