import { Link } from '@inertiajs/react';
import NoticeReviewDialog from '@/components/telework/notice-review-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/campaigns/trauma';
import { show } from '@/routes/tenant/telework/notices';
import type { NoticeRow } from '@/types';

/**
 * What can be done with a notice: review it (an accident on its own page, board V3-Aviso), and for a traumatic event go on
 * to Guía I with the person chosen.
 */
export default function NoticeActions({ row }: { row: NoticeRow }) {
    const tenantSlug = useTenantSlug();
    return (
        <div className="flex flex-wrap gap-2.5 empty:hidden">
            {row.kindValue === 'work_risk' && row.canReview && (
                <Button
                    variant={row.reviewedOn === null ? 'default' : 'outline'}
                    asChild
                >
                    <Link href={show([tenantSlug, row.id])}>
                        {row.reviewedOn === null
                            ? 'Revisar aviso'
                            : 'Ver seguimiento'}
                    </Link>
                </Button>
            )}
            {row.kindValue !== 'work_risk' &&
                row.reviewedOn === null &&
                row.canReview && (
                    <NoticeReviewDialog noticeId={row.id} person={row.person} />
                )}
            {row.trauma !== null && (
                <Button variant="outline" asChild>
                    <Link
                        href={create.url(tenantSlug, {
                            query: {
                                work_center: row.trauma.workCenter,
                                employee: row.trauma.employee,
                            },
                        })}
                    >
                        Lanzar Guía I
                    </Link>
                </Button>
            )}
        </div>
    );
}
