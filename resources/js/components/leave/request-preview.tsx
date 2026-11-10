import InputError from '@/components/humetria/input-error';
import ContinuousNotice from '@/components/leave/continuous-notice';
import { workingDaysOf } from '@/lib/leave/format';
import type { LeavePreview } from '@/types';

/** What the picked dates would be as a request, or why they cannot be asked. Nothing while there is no answer. */
export default function RequestPreview({
    answer,
}: {
    answer: { preview: LeavePreview | null; error: string | null } | null;
}) {
    if (answer === null) {
        return null;
    }

    if (answer.preview === null) {
        return <InputError message={answer.error ?? undefined} />;
    }

    return (
        <div role="status" className="grid gap-3">
            <div className="grid gap-1 rounded-xl bg-accent px-4 py-3 text-accent-foreground">
                <p>
                    Son{' '}
                    <strong className="font-semibold">
                        {workingDaysOf(answer.preview.workingDays)}
                    </strong>
                    .
                </p>
                {answer.preview.hadBalance === false && (
                    <p className="text-base">
                        No tiene días disponibles para estas fechas. Puede
                        enviar la solicitud; quien la apruebe lo verá.
                    </p>
                )}
            </div>
            {answer.preview.continuousNotice && <ContinuousNotice />}
        </div>
    );
}
