import { Link } from '@inertiajs/react';
import CompanyModuleSwitch from '@/components/tenancy/company-module-switch';
import { Button } from '@/components/ui/button';
import { moduleSummary } from '@/lib/company-360';
import { show } from '@/routes/companies';
import type { ModuleRow } from '@/types';

/** The modules of the summary as a grid of switches, with how many are on and where they come from; the detail is a tab away. */
export default function CompanyModuleGrid({
    companyId,
    modules,
    canManage,
}: {
    companyId: string;
    modules: ModuleRow[];
    canManage: boolean;
}) {
    return (
        <section aria-labelledby="company-module-grid" className="card">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 px-6 py-4">
                <h2
                    id="company-module-grid"
                    className="flex-1 text-base font-semibold sm:flex-none"
                >
                    Módulos
                </h2>
                <p className="order-last flex-[1_1_100%] text-sm text-muted-foreground sm:order-none sm:flex-1">
                    {moduleSummary(modules)}
                </p>
                <Button asChild variant="outline" size="sm">
                    <Link
                        href={show.url(companyId, {
                            query: { tab: 'modulos' },
                        })}
                        preserveScroll
                    >
                        Administrar
                    </Link>
                </Button>
            </div>
            <ul className="grid sm:grid-cols-2 xl:grid-cols-3">
                {modules.map((module) => (
                    <li key={module.key} className="border-t">
                        <CompanyModuleSwitch
                            companyId={companyId}
                            module={module}
                            canManage={canManage}
                        />
                    </li>
                ))}
            </ul>
        </section>
    );
}
