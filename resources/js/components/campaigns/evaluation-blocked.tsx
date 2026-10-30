import { Link } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import { show } from '@/routes/tenant/campaigns';
import type { WorkCenterEvaluation } from '@/types';

/** Why a work center cannot start an evaluation now: one is already open, or nobody works there yet. */
export default function EvaluationBlocked({
    tenantSlug,
    center,
}: {
    tenantSlug: string;
    center: WorkCenterEvaluation;
}) {
    const open = center.openCampaign;

    if (open === null) {
        return (
            <Notice>
                Da de alta a su personal antes de evaluarlo: con ese número se
                decide qué guías aplican.
            </Notice>
        );
    }

    return (
        <Notice
            action={
                <Link
                    href={show([tenantSlug, open.id])}
                    className="font-medium underline underline-offset-4"
                >
                    {open.status === 'draft'
                        ? 'Continuar borrador'
                        : 'Ver avance'}
                </Link>
            }
        >
            Este centro ya tiene una evaluación{' '}
            {open.status === 'draft' ? 'en borrador' : 'en curso'}.
        </Notice>
    );
}
