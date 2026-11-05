import { Link } from '@inertiajs/react';
import { Check, UserPlus } from 'lucide-react';
import CoverageReceivers from '@/components/complaints/coverage-receivers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as handlers } from '@/routes/tenant/complaints/handlers';
import type { CoverageByKind } from '@/types';

/**
 * «A quién llega cada queja» (board V4-Quejas-Canal, criterio C-45): for each «contra quién», who of the whole company receives
 * it, «Cubierto», or nobody yet with its button to designate someone.
 */
export default function CoverageTable({
    coverage,
    handlersCount,
    onDesignate,
}: {
    coverage: CoverageByKind[];
    handlersCount: number;
    onDesignate: (row: CoverageByKind | null) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="coverage"
            className="@container overflow-hidden card"
        >
            <header className="flex flex-col gap-1 px-6 pt-6 pb-5">
                <h2 id="coverage" className="text-xl leading-7 font-semibold">
                    A quién llega cada queja
                </h2>
                <p className="text-muted-foreground">
                    Según contra quién es la queja, la recibe alguien distinto.
                    Nadie recibe una queja contra sí mismo ni contra su propio
                    rol.
                </p>
            </header>
            <div className="hidden grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] gap-4 border-y bg-muted px-6 py-2.5 text-sm text-muted-foreground @xl:grid">
                <span>Si la queja es contra</span>
                <span>La atiende</span>
            </div>
            <ul className="max-@xl:border-t divide-y">
                {coverage.map((row) => (
                    <li
                        key={row.kind}
                        className="grid items-center gap-x-4 gap-y-2 px-6 py-3.5 @xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto]"
                    >
                        <span className="font-medium @xl:font-normal">
                            {row.label}
                        </span>
                        <CoverageReceivers row={row} />
                        {row.handlers.length === 0 ? (
                            <Button
                                size="sm"
                                className="justify-self-start"
                                onClick={() => onDesignate(row)}
                            >
                                Designar
                            </Button>
                        ) : (
                            <Badge variant="success" className="before:hidden">
                                <Check aria-hidden />
                                Cubierto
                            </Badge>
                        )}
                    </li>
                ))}
            </ul>
            <footer className="flex flex-wrap gap-2 border-t px-6 py-3">
                <Button variant="ghost" className="text-primary" asChild>
                    <Link href={handlers(tenantSlug)}>
                        Quiénes atienden · {handlersCount}
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    className="text-primary"
                    onClick={() => onDesignate(null)}
                >
                    <UserPlus aria-hidden />
                    Designar a alguien más
                </Button>
            </footer>
        </section>
    );
}
