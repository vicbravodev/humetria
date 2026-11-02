import InputError from '@/components/humetria/input-error';
import PortalNoticeDates from '@/components/telework/portal/portal-notice-dates';
import WorkplaceFields from '@/components/telework/workplace-fields';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { noticeExtras } from '@/lib/telework/notices';
import type { NoticeKindValue } from '@/types';

/** The fields of a notice (numerals 6.3, 6.7, 6.9): the two dates, the description, and what its kind adds. */
export default function PortalNoticeFields({
    kind,
    today,
    states,
    errors,
}: {
    kind: NoticeKindValue;
    today: string;
    states: Record<string, string>;
    errors: Partial<Record<string, string>>;
}) {
    const extras = noticeExtras(kind);

    return (
        <>
            <PortalNoticeDates kind={kind} today={today} errors={errors} />
            <div className="grid gap-2">
                <Label htmlFor={`${kind}-description`}>Descripción</Label>
                <Textarea
                    className="min-h-28"
                    id={`${kind}-description`}
                    name="description"
                    maxLength={3000}
                    aria-invalid={
                        errors['description'] === undefined ? undefined : true
                    }
                    aria-describedby={`${kind}-description-error`}
                />
                <InputError
                    id={`${kind}-description-error`}
                    message={errors['description']}
                />
            </div>
            {extras.traumatic && (
                <div className="flex items-center gap-3">
                    <Checkbox
                        id="notice-traumatic"
                        name="traumatic_event"
                        value="1"
                    />
                    <Label htmlFor="notice-traumatic">
                        Fue un acontecimiento traumático severo
                    </Label>
                </div>
            )}
            {extras.address && (
                <>
                    <div className="grid gap-4">
                        <p className="text-base font-semibold">
                            Nuevo domicilio
                        </p>
                        <WorkplaceFields
                            index={null}
                            states={states}
                            errors={errors}
                        />
                        <InputError message={errors['address']} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="notice-temporary"
                            name="temporary"
                            value="1"
                        />
                        <Label htmlFor="notice-temporary">
                            Es un cambio temporal
                        </Label>
                    </div>
                </>
            )}
        </>
    );
}
