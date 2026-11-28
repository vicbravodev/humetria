import { Link } from '@inertiajs/react';
import { Ellipsis, Landmark, ScrollText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as audit } from '@/routes/audit';
import { show as account } from '@/routes/billing/accounts';

/** «⋯ Más» at the head of a company's page: its audit trail and the account that pays it, for whoever may read them. */
export default function CompanyMoreMenu({
    companyId,
    accountId,
    canAudit,
}: {
    companyId: string;
    accountId: string | null;
    canAudit: boolean;
}) {
    if (!canAudit && accountId === null) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" aria-label="Más acciones">
                    <Ellipsis aria-hidden />
                    Más
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-52">
                {canAudit && (
                    <DropdownMenuItem asChild>
                        <Link
                            href={audit.url({ query: { company: companyId } })}
                        >
                            <ScrollText aria-hidden />
                            Ver su bitácora
                        </Link>
                    </DropdownMenuItem>
                )}
                {accountId !== null && (
                    <DropdownMenuItem asChild>
                        <Link href={account.url(accountId)}>
                            <Landmark aria-hidden />
                            Ver la cuenta que paga
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
