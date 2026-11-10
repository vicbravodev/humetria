import ModuleRowView from '@/components/tenancy/module-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ModuleRow } from '@/types';

/**
 * The modules of a company: whether it has each and where that comes from (its plan, what it had before one, a grant or a
 * revocation by hand). Switching one off hides it and closes its pages and jobs; nothing is deleted, so it comes back whole.
 */
export default function CompanyModules({
    companyId,
    modules,
    canManage,
}: {
    companyId: string;
    modules: ModuleRow[];
    canManage: boolean;
}) {
    return (
        <section aria-labelledby="company-modules" className="grid gap-4">
            <div className="grid gap-1">
                <h2
                    id="company-modules"
                    className="text-xl font-semibold tracking-[-0.01em]"
                >
                    Módulos
                </h2>
                <p className="max-w-[620px] text-sm text-muted-foreground">
                    Quitar un módulo lo esconde y detiene sus pantallas y
                    envíos; sus datos se conservan y vuelven si se habilita.
                </p>
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Módulos de la empresa, con su origen
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Módulo</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead>Origen</TableHead>
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {modules.map((module) => (
                        <ModuleRowView
                            key={module.key}
                            companyId={companyId}
                            module={module}
                            canManage={canManage}
                        />
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}
