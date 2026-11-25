import { Link } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { premiumNotice } from '@/lib/compensation/report-notes';
import { index as benefits } from '@/routes/tenant/compensation/benefits';
import type { ScenarioReport } from '@/types';

/**
 * The estimate of an average payroll counts the work risk with the premium of class I while the company has not captured its own: the
 * report says so, with the way to capture it (in Prestaciones) for whoever manages the contract.
 */
export default function ReportPremiumNotice({
    report,
    canCapture,
}: {
    report: ScenarioReport;
    canCapture: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const notice = premiumNotice(report);

    if (notice === null) {
        return null;
    }

    return (
        <Notice
            action={
                canCapture ? (
                    <Button
                        variant="outline"
                        asChild
                        className="max-sm:w-full print:hidden"
                    >
                        <Link href={benefits(tenantSlug)}>
                            Capturar mi prima
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <strong>{notice.title}</strong> {notice.detail}
        </Notice>
    );
}
