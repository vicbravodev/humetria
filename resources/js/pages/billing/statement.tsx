import { Head, setLayoutProps } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import AdjustStatementDialog from '@/components/billing/adjust-statement-dialog';
import IssueStatementDialog from '@/components/billing/issue-statement-dialog';
import StatementApplications from '@/components/billing/statement-applications';
import StatementLines from '@/components/billing/statement-lines';
import StatementStateBadge from '@/components/billing/statement-state-badge';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { index, show as account } from '@/routes/billing/accounts';
import { print } from '@/routes/billing/statements';
import type {
    AppliedPayment,
    StatementAdjustment,
    StatementDetail,
    StatementLine,
} from '@/types';

/** One statement of account: its lines with how each amount was reached, what was paid of it, and the way to print it. */
export default function Statement({
    statement,
    lines,
    payments,
    adjustments,
    can_manage: canManage,
}: {
    statement: StatementDetail;
    lines: StatementLine[];
    payments: AppliedPayment[];
    adjustments: StatementAdjustment[];
    can_manage: boolean;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cuentas', href: index() },
            {
                title: statement.account_name,
                href: account(statement.account_id),
            },
            { title: `Estado de cuenta ${statement.period}` },
        ],
    });

    const draft = statement.status === 'draft';

    return (
        <>
            <Head title={`Estado de cuenta ${statement.period}`} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption={
                        statement.is_adjustment
                            ? 'Ajuste a un estado de cuenta'
                            : 'Estado de cuenta'
                    }
                    title={`${statement.trade_name} · ${statement.period}`}
                    status={
                        <StatementStateBadge
                            state={statement.state}
                            label={statement.state_label}
                        />
                    }
                    description={`${statement.legal_name} (${statement.company_rfc}) · paga ${statement.account_name} · del ${statement.period_range}`}
                >
                    <Button variant="outline" asChild>
                        <a
                            href={print.url(statement.id)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Printer aria-hidden />
                            Ver para imprimir
                        </a>
                    </Button>
                    {canManage && draft && (
                        <IssueStatementDialog statement={statement} />
                    )}
                    {canManage && !draft && !statement.is_adjustment && (
                        <AdjustStatementDialog statement={statement} />
                    )}
                </PageHeader>
                <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
                    <StatementLines statement={statement} lines={lines} />
                    <div className="grid gap-6">
                        <StatementApplications
                            payments={payments}
                            adjustments={adjustments}
                        />
                        <p className="text-caption text-muted-foreground">
                            Catálogo {statement.catalog_version}, IVA de{' '}
                            {statement.vat_percent}%. Hecho por{' '}
                            {statement.created_by}
                            {statement.issued_at !== null &&
                                `; emitido el ${statement.issued_at} por ${statement.issued_by ?? ''}`}
                            .
                            {statement.reason !== null &&
                                ` Motivo del ajuste: ${statement.reason}.`}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
