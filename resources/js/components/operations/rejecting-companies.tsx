import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { count } from '@/lib/operations';
import { show } from '@/routes/companies';
import type { CompanyRejections } from '@/types';

/** The companies with the most rejected messages in 7 days, from five up: only how many, never which ones nor to whom. */
export default function RejectingCompanies({
    companies,
}: {
    companies: CompanyRejections[];
}) {
    return (
        <section className="card p-0">
            <header className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b px-6 py-4">
                <h2 className="flex-1 text-base font-semibold">
                    Empresas con más rechazos
                </h2>
                <p className="text-meta text-muted-foreground">
                    Últimos 7 días · desde 5 rechazos
                </p>
            </header>
            {companies.length === 0 ? (
                <p className="px-6 py-4 text-sm text-muted-foreground">
                    Ninguna empresa llegó a 5 rechazos en los últimos 7 días.
                </p>
            ) : (
                <ul className="divide-y">
                    {companies.map((company) => (
                        <li
                            key={company.id}
                            className="flex flex-wrap items-center gap-x-3 gap-y-1 px-6 py-3.5"
                        >
                            <span className="min-w-0 flex-1 font-medium max-sm:basis-full">
                                {company.name}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {count(company.rejected)} rechazos
                            </span>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={show(company.id)}>
                                    Abrir Empresa 360
                                    <span className="sr-only">
                                        {' '}
                                        de {company.name}
                                    </span>
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
