import { usePage } from '@inertiajs/react';
import AccountCompaniesEmpty from '@/components/billing/account-companies-empty';
import AccountCompanyItem from '@/components/billing/account-company-item';
import CompanyPickerAction from '@/components/billing/company-picker-action';
import LinkCompanyDialog from '@/components/billing/link-company-dialog';
import Pagination from '@/components/humetria/pagination';
import type { AccountCompanyRow, AccountKind, Paginated } from '@/types';

/** «Empresas que paga» (tablero Cuenta de facturación): a line per company with what it pays a month, the way to link another and to move one to another account. */
export default function AccountCompaniesCard({
    accountId,
    accountKind,
    companies,
    today,
}: {
    accountId: string;
    accountKind: AccountKind;
    companies: Paginated<AccountCompanyRow>;
    today: string;
}) {
    const { auth } = usePage().props;

    return (
        <section aria-labelledby="account-companies" className="card p-0">
            <div className="flex items-center gap-3 border-b px-6 py-4">
                <h2 id="account-companies" className="flex-1 font-semibold">
                    Empresas que paga
                </h2>
                {auth.isSuperadmin && (
                    <LinkCompanyDialog accountId={accountId} />
                )}
            </div>
            {companies.data.length === 0 ? (
                <div className="p-4">
                    <AccountCompaniesEmpty />
                </div>
            ) : (
                <ul className="divide-y">
                    {companies.data.map((company) => (
                        <AccountCompanyItem
                            key={company.company_id}
                            company={company}
                            accountKind={accountKind}
                            today={today}
                        />
                    ))}
                </ul>
            )}
            <div className="border-t px-6 py-3 empty:hidden">
                <Pagination
                    previous="Anterior"
                    next="Siguiente"
                    page={companies}
                    label="Páginas de empresas de la cuenta"
                    pinned={false}
                />
            </div>
            {auth.isSuperadmin && companies.data.length > 0 && (
                <div className="border-t px-4 py-2.5">
                    <CompanyPickerAction
                        kind="transfer"
                        companies={companies.data}
                        accountKind={accountKind}
                        today={today}
                        variant="ghost"
                    >
                        Transferir una empresa a otra cuenta…
                    </CompanyPickerAction>
                </div>
            )}
        </section>
    );
}
