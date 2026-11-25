import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
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
        <section className="card">
            <header className="border-b px-5 py-4">
                <h2 className="text-base font-semibold">
                    Empresas con más rechazos
                </h2>
                <p className="text-sm text-muted-foreground">
                    Últimos 7 días, desde 5 rechazos.
                </p>
            </header>
            {companies.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">
                    Ninguna empresa llegó a 5 rechazos en los últimos 7 días.
                </p>
            ) : (
                <ul className="divide-y">
                    {companies.map((company) => (
                        <li
                            key={company.id}
                            className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3"
                        >
                            <span className="min-w-0 font-medium">
                                {company.name}
                            </span>
                            <span className="flex items-center gap-4">
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {count(company.rejected)} rechazos
                                </span>
                                <Link
                                    href={show(company.id)}
                                    className="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Ver empresa
                                    <ArrowRight
                                        aria-hidden
                                        className="size-4"
                                    />
                                </Link>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
