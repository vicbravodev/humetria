import CompanyActivity from '@/components/tenancy/company-activity';
import CompanyLimitFigure from '@/components/tenancy/company-limit-figure';
import CompanyModuleGrid from '@/components/tenancy/company-module-grid';
import { limitFigures } from '@/lib/company-360';
import type { CompanyPage } from '@/types';

/** The summary tab (tablero Empresa 360): its limits in three figures, its modules as switches and its latest activity. */
export default function CompanyOverview({ page }: { page: CompanyPage }) {
    const figures = limitFigures(page.limits, page.other_usage);

    return (
        <div className="grid min-w-0 gap-6">
            {figures.length > 0 && (
                <div className="grid gap-4 md:grid-cols-3">
                    {figures.map((figure) => (
                        <CompanyLimitFigure key={figure.key} figure={figure} />
                    ))}
                </div>
            )}
            <CompanyModuleGrid
                companyId={page.company.id}
                modules={page.modules}
                canManage={page.can_manage}
            />
            {page.activity !== null && (
                <CompanyActivity
                    companyId={page.company.id}
                    activity={page.activity}
                    summary
                />
            )}
        </div>
    );
}
