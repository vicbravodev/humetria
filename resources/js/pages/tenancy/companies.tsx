import { Head } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import PageHeader from '@/components/page-header';
import CompanyCard from '@/components/tenancy/company-card';
import NoCompanies from '@/components/tenancy/no-companies';
import type { CompanyLink, Paginated } from '@/types';

export default function Companies({
    companies,
}: {
    companies: Paginated<CompanyLink>;
}) {
    const title = companies.total === 0 ? 'Tus empresas' : 'Elige una empresa';

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title={title} />
                {companies.total === 0 ? (
                    <NoCompanies />
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2">
                        {companies.data.map((company) => (
                            <li key={company.slug} className="relative">
                                <CompanyCard company={company} />
                            </li>
                        ))}
                    </ul>
                )}
                <ConsolePagination
                    page={companies}
                    label="Páginas de empresas"
                    previous="Anteriores"
                    next="Siguientes"
                />
            </div>
        </>
    );
}
