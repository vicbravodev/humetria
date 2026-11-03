import { Link } from '@inertiajs/react';
import { Building2, FileText } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import RequirementRow from '@/components/disclosure/requirement-row';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { requirementVariant } from '@/lib/disclosure';
import { dossier } from '@/routes/tenant/disclosure';
import type { BoardCenter } from '@/types';

/** A work center with where each requirement stands (pantalla NOM035: the center, its state, what it needs). */
export default function CenterCard({
    center,
    canManage,
}: {
    center: BoardCenter;
    /** Whoever manages may open the printable dossier of the center. */
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby={`center-${center.id}`}
            className="grid gap-2 card p-5"
        >
            <header className="flex items-center gap-3">
                <IconTile icon={Building2} />
                <h3
                    id={`center-${center.id}`}
                    className="min-w-0 flex-1 text-lg font-semibold"
                >
                    {center.name}
                </h3>
                <Badge variant={requirementVariant(center.state.value)}>
                    {center.state.label}
                </Badge>
                {canManage && (
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={dossier({
                                tenant: tenantSlug,
                                workCenter: center.id,
                            })}
                        >
                            <FileText aria-hidden />
                            Expediente
                            <span className="sr-only"> de {center.name}</span>
                        </Link>
                    </Button>
                )}
            </header>
            <ul>
                {center.requirements.map((requirement) => (
                    <RequirementRow
                        key={requirement.code}
                        requirement={requirement}
                    />
                ))}
            </ul>
        </section>
    );
}
