import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import SearchField from '@/components/humetria/search-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { auditQuery } from '@/lib/audit';
import { index } from '@/routes/audit';
import type { AuditFamily, AuditFilters } from '@/types';

const ALL = 'all';

/** Narrows the trail: who acted, what kind of event, which company and which days. */
export default function AuditToolbar({
    filters,
    families,
    company,
}: {
    filters: AuditFilters;
    families: AuditFamily[];
    company: string | null;
}) {
    const { text, type, filter } = useQueryFilters(
        index.url(),
        filters,
        auditQuery,
    );

    return (
        <div role="search" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end gap-3">
                <SearchField
                    label="Buscar por correo de quien actuó"
                    value={text}
                    onChange={type}
                    className="w-full md:w-[320px]"
                />
                <div className="grid gap-1.5">
                    <Label htmlFor="audit-family">Tipo de evento</Label>
                    <Select
                        value={filters.family === '' ? ALL : filters.family}
                        onValueChange={(family) =>
                            filter({ family: family === ALL ? '' : family })
                        }
                    >
                        <SelectTrigger
                            id="audit-family"
                            className="w-full md:w-60"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                Todos los eventos
                            </SelectItem>
                            {families.map((family) => (
                                <SelectItem
                                    key={family.value}
                                    value={family.value}
                                >
                                    {family.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="audit-from">Desde</Label>
                    <Input
                        id="audit-from"
                        type="date"
                        value={filters.from}
                        max={filters.to === '' ? undefined : filters.to}
                        onChange={(event) =>
                            filter({ from: event.target.value })
                        }
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="audit-to">Hasta</Label>
                    <Input
                        id="audit-to"
                        type="date"
                        value={filters.to}
                        min={filters.from === '' ? undefined : filters.from}
                        onChange={(event) => filter({ to: event.target.value })}
                    />
                </div>
            </div>
            {company !== null && (
                <p className="flex flex-wrap items-center gap-2 text-meta text-muted-foreground">
                    Solo lo de{' '}
                    <strong className="text-foreground">{company}</strong>
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="touch-hit"
                    >
                        <Link
                            href={index.url({
                                query: auditQuery({ ...filters, company: '' }),
                            })}
                        >
                            <X aria-hidden />
                            Quitar empresa
                        </Link>
                    </Button>
                </p>
            )}
        </div>
    );
}
