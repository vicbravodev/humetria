import ReviewStep from '@/components/engagement/review-step';
import UnreachableList from '@/components/engagement/unreachable-list';
import Notice from '@/components/humetria/notice';
import { climateReached, climateUnreached } from '@/lib/climate-contact';
import { countOf } from '@/lib/plural';
import type { ClimateDraft, ClimateUnreachable, Paginated } from '@/types';

/**
 * The first two things to review on a draft (pantalla Eval-Borrador): who will be invited, by which channel, and who cannot
 * be reached for lack of an email and a phone. RH may know who has none; the survey never knows who answers.
 */
export default function AudienceReview({
    draft,
    unreachable,
}: {
    draft: ClimateDraft;
    unreachable: Paginated<ClimateUnreachable>;
}) {
    return (
        <>
            <ReviewStep title="Personas">
                <p className="text-sm text-muted-foreground">
                    <strong className="font-semibold text-foreground">
                        {countOf(draft.people, 'persona', 'personas')}
                    </strong>{' '}
                    activas, de todos tus centros de trabajo, recibirán la
                    encuesta.
                    {draft.companyOnly > 0 &&
                        ` ${countOf(draft.companyOnly, 'persona', 'personas')} se reportarán solo con toda la empresa: sus grupos son de menos de ${draft.minGroup} y así nadie queda señalado.`}
                </p>
            </ReviewStep>
            <ReviewStep title="Contacto">
                {draft.withoutContact === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {climateReached(draft)}
                    </p>
                ) : (
                    <>
                        <Notice>
                            <strong>
                                {climateUnreached(draft.withoutContact)}
                            </strong>{' '}
                            No recibirán su enlace.
                        </Notice>
                        <UnreachableList people={unreachable} />
                    </>
                )}
            </ReviewStep>
        </>
    );
}
