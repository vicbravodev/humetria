import { router } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/roles';
import type { RoleTemplateOption } from '@/types';

const BLANK = 'blank';

/** Where a new role starts: blank, or with what a template has switched on (the server sends those switches). */
export default function RoleStart({
    templates,
    from,
}: {
    templates: RoleTemplateOption[];
    from: string | null;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div className="grid gap-2">
            <Label htmlFor="role-start">Empezar desde</Label>
            <Select
                value={from ?? BLANK}
                onValueChange={(next) =>
                    router.get(
                        create.url(
                            tenantSlug,
                            next === BLANK ? {} : { query: { from: next } },
                        ),
                        {},
                        { replace: true },
                    )
                }
            >
                <SelectTrigger id="role-start" className="w-full sm:w-80">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={BLANK}>En blanco</SelectItem>
                    {templates.map((template) => (
                        <SelectItem key={template.key} value={template.key}>
                            Copia de {template.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
