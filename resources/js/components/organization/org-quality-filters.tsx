import Combobox from '@/components/humetria/combobox';
import SearchField from '@/components/humetria/search-field';
import { useQualityFilters } from '@/hooks/use-quality-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { QualityFilters } from '@/lib/org-chart/quality';
import { serverSource } from '@/lib/server-options';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { ReportingIssue } from '@/types';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };

/** Search by name and the work center of the list, both on the server: the list is paged. */
export default function OrgQualityFilters({
    filters,
    issue,
}: {
    filters: QualityFilters;
    issue: ReportingIssue['value'];
}) {
    const tenantSlug = useTenantSlug();
    const { text, type, filter } = useQualityFilters(
        tenantSlug,
        filters,
        issue,
    );

    return (
        <div role="search" className="flex flex-col gap-3 sm:flex-row">
            <SearchField
                label="Busca a una persona"
                value={text}
                onChange={type}
                className="sm:w-[400px]"
            />
            <div className="sm:w-52">
                <Combobox
                    id="quality-work-center"
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
            </div>
        </div>
    );
}
