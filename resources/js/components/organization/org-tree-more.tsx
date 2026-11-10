import { Button } from '@/components/ui/button';
import { moreText } from '@/lib/org-chart/labels';
import type { Branch } from '@/lib/org-chart/tree-state';
import type { OrgNode } from '@/types';

const NOTE = 'text-meta text-muted-foreground print:hidden';

/**
 * The end of a person's reports on screen: the ones still on their way, the way to try again when they could not be
 * loaded, or «Ver más» for the next page.
 */
export default function OrgTreeMore({
    manager,
    branch,
    filtered,
    onLoad,
}: {
    manager: OrgNode;
    branch: Branch;
    /** A filter hides some reports: how many are left is not known. */
    filtered: boolean;
    onLoad: () => void;
}) {
    if (branch.status === 'loading') {
        return (
            <li role="none" aria-live="polite" className={NOTE}>
                Cargando reportes…
            </li>
        );
    }

    if (branch.status === 'error') {
        return (
            <li role="none" className={NOTE}>
                <span role="alert">
                    No se pudieron cargar los reportes.{' '}
                    <button
                        type="button"
                        onClick={onLoad}
                        className="font-medium text-primary underline underline-offset-4"
                    >
                        Reintentar
                    </button>
                </span>
            </li>
        );
    }

    if (branch.nextPage === null) {
        return null;
    }

    const text = moreText(
        manager.direct_reports,
        branch.nodes.filter((report) => report.active).length,
        filtered,
    );

    return (
        <li role="none" className="print:hidden">
            <Button
                type="button"
                variant="outline"
                aria-label={`${text}: reportes de ${manager.name}`}
                onClick={onLoad}
            >
                {text}
            </Button>
        </li>
    );
}
