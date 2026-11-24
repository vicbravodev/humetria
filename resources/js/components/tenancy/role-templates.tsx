import { Link } from '@inertiajs/react';
import { Copy, Lock } from 'lucide-react';
import StackedTable from '@/components/humetria/stacked-table';
import { Button } from '@/components/ui/button';
import {
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { summarize } from '@/lib/permission-areas';
import type { PermissionArea } from '@/lib/permission-areas';
import { countOf } from '@/lib/plural';
import { create } from '@/routes/tenant/roles';
import type { RoleTemplateRow } from '@/types';

/**
 * The templates of Humetría the company hands out (spec: «plantillas del sistema con candado»), with what each lets do in one
 * sentence: fixed, so the only thing to do with one is to copy it into a role of the company's own and adjust it there.
 */
export default function RoleTemplates({
    templates,
    areas,
    canCopy,
}: {
    templates: RoleTemplateRow[];
    /** The areas the plan has: the sentence speaks only of those. */
    areas: PermissionArea[];
    /** Off for support, and at the limit of roles of the company's own. */
    canCopy: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <StackedTable>
            <TableCaption className="sr-only">
                Plantillas de roles de Humetría
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Rol</TableHead>
                    <TableHead>Qué puede hacer</TableHead>
                    <TableHead>Personas</TableHead>
                    {canCopy && (
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {templates.map((template) => (
                    <TableRow key={template.key}>
                        <TableCell className="font-medium whitespace-normal max-md:w-full md:w-1/4">
                            <span className="flex items-center gap-2">
                                <Lock
                                    aria-hidden
                                    className="size-4 shrink-0 text-muted-foreground"
                                />
                                {template.label}
                            </span>
                        </TableCell>
                        <TableCell className="text-sm text-pretty whitespace-normal">
                            {summarize({
                                areas,
                                held: template.permissions,
                                subject: '',
                            })}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground tabular-nums">
                            {countOf(template.people, 'persona', 'personas')}
                        </TableCell>
                        {canCopy && (
                            <TableCell className="text-right md:w-px">
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={create(tenantSlug, {
                                            query: { from: template.key },
                                        })}
                                        aria-label={`Copiar y ajustar ${template.label}`}
                                    >
                                        <Copy aria-hidden />
                                        Copiar y ajustar
                                    </Link>
                                </Button>
                            </TableCell>
                        )}
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    );
}
