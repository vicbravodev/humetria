import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AnnouncementRow } from '@/types';

/** The title the console lists it by and the message the companies read in their banner. */
export default function AnnouncementText({
    announcement,
    errors,
}: {
    announcement: AnnouncementRow | null;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="announcement-title">Título</Label>
                <Input
                    id="announcement-title"
                    name="title"
                    maxLength={120}
                    defaultValue={announcement?.title}
                    aria-invalid={errors['title'] !== undefined}
                />
                <InputError message={errors['title']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="announcement-message">
                    Mensaje para las empresas
                </Label>
                <Textarea
                    id="announcement-message"
                    name="message"
                    maxLength={500}
                    rows={3}
                    defaultValue={announcement?.message}
                    aria-invalid={errors['message'] !== undefined}
                />
                <InputError message={errors['message']} />
            </div>
        </>
    );
}
