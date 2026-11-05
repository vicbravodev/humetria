import { Head } from '@inertiajs/react';
import Pagination from '@/components/humetria/pagination';
import PageHeader from '@/components/humetria/page-header';
import CompanyRow from '@/components/tenancy/company-row';
import NoCompanies from '@/components/tenancy/no-companies';
import type { CompanyLink, Paginated } from '@/types';

/** The company chooser (C2 of the rediseño v2): with none or several companies, /admin asks which one to open. */
export default function Companies({
    companies,
}: {
    companies: Paginated<CompanyLink>;
}) {
    const title = companies.total === 0 ? 'Tus empresas' : 'Elige una empresa';

    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-9 px-4 py-6 md:px-12 md:py-10">
                <PageHeader
                    title={title}
                    description={
                        companies.total === 0
                            ? undefined
                            : 'Tienes acceso a varias empresas. Abre aquella en la que vas a trabajar.'
                    }
                />
                {companies.total === 0 ? (
                    <NoCompanies />
                ) : (
                    <section
                        aria-labelledby="companies"
                        className="flex flex-col gap-3"
                    >
                        <h2
                            id="companies"
                            className="text-xl leading-7 font-semibold"
                        >
                            {companies.total === 1
                                ? 'Tu empresa'
                                : `Tus ${companies.total} empresas`}
                        </h2>
                        <ul className="@container overflow-hidden card">
                            {companies.data.map((company) => (
                                <CompanyRow
                                    key={company.slug}
                                    company={company}
                                />
                            ))}
                        </ul>
                    </section>
                )}
                <Pagination page={companies} label="Páginas de empresas" />
            </div>
        </>
    );
}
