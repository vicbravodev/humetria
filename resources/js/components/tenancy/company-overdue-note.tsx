import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { overdueLine } from '@/lib/company-360';
import { show as account } from '@/routes/billing/accounts';
import type { CompanyPayer } from '@/types';

/** The notice under the tabs while the account that pays the company owes past due, with the way to its statement. */
export default function CompanyOverdueNote({
    payer,
}: {
    payer: CompanyPayer | null;
}) {
    const line = overdueLine(payer);

    if (payer === null || line === null) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex flex-wrap items-center gap-3 rounded-[10px] bg-risk-alto-bg px-4 py-3.5 text-risk-alto-fg"
        >
            <TriangleAlert aria-hidden className="size-[18px] shrink-0" />
            <p className="min-w-0 flex-[1_1_220px]">
                <span className="font-semibold">{line}</span> Registra el pago o
                revisa el estado de cuenta antes de pasarla a solo lectura.
            </p>
            <Button asChild variant="outline" size="sm">
                <Link href={account.url(payer.account_id)}>
                    Ver estado de cuenta
                </Link>
            </Button>
        </div>
    );
}
