import { Link } from '@inertiajs/react';
import NoticeReviewDialog from '@/components/telework/notice-review-dialog';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/tenant/campaigns/trauma';
import type { NoticeRow } from '@/types';

/** What can be done with a notice: review it, and for a traumatic event go on to Guía I with the person chosen. */
export default function NoticeActions({
    tenantSlug,
    row,
}: {
    tenantSlug: string;
    row: NoticeRow;
}) {
    return (
        <div className="flex flex-wrap gap-2.5 empty:hidden">
            {row.reviewedOn === null && row.canReview && (
                <NoticeReviewDialog
                    tenantSlug={tenantSlug}
                    noticeId={row.id}
                    person={row.person}
                />
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
