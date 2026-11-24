import { Link } from '@inertiajs/react';
import { Copy, Lock } from 'lucide-react';
import StackedTable from '@/components/humetria/stacked-table';
import { Badge } from '@/components/ui/badge';
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
import { countOf } from '@/lib/plural';
import { create } from '@/routes/tenant/roles';
import type { RoleTemplateRow } from '@/types';

/**
 * The templates of Humetría the company hands out (spec: «plantillas del sistema con candado»): fixed, so the only thing to do
 * with one is to copy it into a role of the company's own and adjust it there.
 */
export default function RoleTemplates({
    templates,
    canCopy,
}: {
    templates: RoleTemplateRow[];
    /** Off for support, and at the limit of roles of the company's own. */
    canCopy: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="role-templates"
            className="flex flex-col gap-3"
        >
            <div className="flex flex-col gap-0.5">
                <h2 id="role-templates" className="text-lg font-semibold">
                    Plantillas de Humetría
                </h2>
                <p className="text-sm text-muted-foreground">
                    Fijas: no se editan. Cópialas para crear un rol propio a
                    partir de ellas.
                </p>
            </div>
            <StackedTable>
                <TableCaption className="sr-only">
                    Plantillas de roles de Humetría
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Rol</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead>Personas</TableHead>
                        <TableHead>Permisos</TableHead>
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
                            <TableCell className="font-medium max-md:w-full">
                                <span className="flex items-center gap-2">
                                    <Lock
                                        aria-hidden
                                        className="size-4 shrink-0 text-muted-foreground"
                                    />
                                    {template.label}
                                </span>
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">Plantilla</Badge>
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground tabular-nums">
                                {countOf(
                                    template.people,
                                    'persona',
                                    'personas',
                                )}
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground tabular-nums">
                                {countOf(
                                    template.permissions,
                                    'permiso',
                                    'permisos',
                                )}
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
        </section>
    );
}
