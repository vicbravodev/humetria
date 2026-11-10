import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CircleCheck } from 'lucide-react';
import PrintButton from '@/components/humetria/print-button';
import PortalShell from '@/components/leave/portal-shell';
import StatementSheet from '@/components/leave/statement-sheet';
import { Button } from '@/components/ui/button';
import { acknowledge } from '@/routes/portal/vacations/statements';
import { show } from '@/routes/portal/vacations';
import type { StatementSheetData } from '@/types';
import { Form } from '@inertiajs/react';

/** The annual statement of one period in the person's portal, in usted: to read, to print and to confirm they received it. */
export default function LeavePortalStatement({
    token,
    company,
    statement,
    sheet,
}: {
    token: string;
    company: string;
    statement: { id: string };
    sheet: StatementSheetData;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Constancia de vacaciones" />
            <div className="flex w-full max-w-[760px] flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link
                        href={show.url({ token })}
                        className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
                    >
                        <ArrowLeft aria-hidden className="size-4" />
                        Volver a mis vacaciones
                    </Link>
                    <PrintButton variant="outline" />
                </div>
                <StatementSheet sheet={sheet} />
                <div className="flex flex-col gap-2 print:hidden">
                    {sheet.acknowledgedOn === null ? (
                        <Form
                            {...acknowledge.form({
                                token,
                                statement: statement.id,
                            })}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full sm:w-auto"
                                    disabled={processing}
                                >
                                    Confirmo que recibí esta constancia
                                </Button>
                            )}
                        </Form>
                    ) : (
                        <p className="flex items-center gap-2 text-base font-medium text-primary">
                            <CircleCheck aria-hidden className="size-5" />
                            Recibida el {sheet.acknowledgedOn}
                        </p>
                    )}
                </div>
            </div>
        </PortalShell>
    );
}
