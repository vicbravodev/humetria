import { Search } from 'lucide-react';
import Combobox from '@/components/humetria/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useEmployeeFilters } from '@/hooks/use-employee-filters';
import { serverSource } from '@/lib/server-options';
import { options as areaOptions } from '@/routes/tenant/areas';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { EmployeeFilters } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };
const ALL_AREAS = { value: '', label: 'Todas las áreas' };

/** Narrows the list: text in the name, email or number; a work center; an area; active or deactivated. */
export default function EmployeesToolbar({
    tenantSlug,
    filters,
}: {
    tenantSlug: string;
    filters: EmployeeFilters;
}) {
    const { text, type, filter } = useEmployeeFilters(tenantSlug, filters);

    return (
        <div
            role="search"
            className="grid gap-3 md:grid-cols-[1fr_14rem_14rem_11rem]"
        >
            <div className="relative">
                <Search
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    aria-label="Buscar por nombre, correo o número"
                    placeholder="Buscar por nombre, correo o número"
                    value={text}
                    onChange={(event) => type(event.target.value)}
                    className="pl-9"
                />
            </div>
            <Combobox
                id="filter-work-center"
                name="work_center"
                source={serverSource(workCenterOptions.url(tenantSlug), [
                    ALL_CENTERS,
                ])}
                defaultValue={filters.work_center ?? ALL_CENTERS}
                placeholder="Centro de trabajo"
                onChange={(option) =>
                    filter({
                        work_center: option.value === '' ? null : option,
                    })
                }
            />
            <Combobox
                id="filter-area"
                name="area"
                source={serverSource(areaOptions.url(tenantSlug), [ALL_AREAS])}
                defaultValue={filters.area ?? ALL_AREAS}
                placeholder="Área"
                onChange={(option) =>
                    filter({ area: option.value === '' ? null : option })
                }
            />
            <Select
                value={filters.status}
                onValueChange={(status) =>
                    filter({
                        status: status === 'inactive' ? 'inactive' : 'active',
                    })
                }
            >
                <SelectTrigger aria-label="Estado" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="active">Activos</SelectItem>
                    <SelectItem value="inactive">Dados de baja</SelectItem>
                </SelectContent>
            </Select>
        </div>
    );
}
