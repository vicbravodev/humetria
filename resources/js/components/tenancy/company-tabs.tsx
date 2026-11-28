import { Link } from '@inertiajs/react';
import { COMPANY_TABS } from '@/lib/company-360';
import type { CompanyTab } from '@/lib/company-360';
import { cn } from '@/lib/utils';
import { show } from '@/routes/companies';

/** The tabs of a company's page (tablero Empresa 360), with how many people and invitations it has; the open one is underlined. */
export default function CompanyTabs({
    companyId,
    active,
    counts,
    hidden,
}: {
    companyId: string;
    active: CompanyTab;
    counts: Partial<Record<CompanyTab, number>>;
    /** Tabs this person may not open (the activity, without the audit trail). */
    hidden: CompanyTab[];
}) {
    return (
        <nav
            aria-label="Partes de la empresa"
            className="flex [scrollbar-width:none] gap-1 overflow-x-auto border-b"
        >
            {COMPANY_TABS.filter((tab) => !hidden.includes(tab.id)).map(
                (tab) => {
                    const on = tab.id === active;
                    const count = counts[tab.id];

                    return (
                        <Link
                            key={tab.id}
                            href={show.url(companyId, {
                                query: { tab: tab.id },
                            })}
                            preserveScroll
                            preserveState
                            aria-current={on ? 'page' : undefined}
                            className={cn(
                                '-mb-px flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3.5 font-medium whitespace-nowrap transition-colors duration-150',
                                on
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                            {count !== undefined && (
                                <span
                                    className={cn(
                                        'rounded-full px-[7px] text-xs leading-5',
                                        on
                                            ? 'bg-accent text-primary'
                                            : 'bg-secondary text-muted-foreground',
                                    )}
                                >
                                    {count}
                                </span>
                            )}
                        </Link>
                    );
                },
            )}
        </nav>
    );
}
