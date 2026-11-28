import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AnnouncementRow } from '@/types';

/** The title the console lists it by and the message the companies read in their banner, which the preview follows. */
export default function AnnouncementText({
    announcement,
    message,
    errors,
}: {
    announcement: AnnouncementRow | null;
    message: { value: string; onChange: (value: string) => void };
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
                    value={message.value}
                    onChange={(event) => message.onChange(event.target.value)}
                    aria-invalid={errors['message'] !== undefined}
                />
                <InputError message={errors['message']} />
            </div>
        </>
    );
}
