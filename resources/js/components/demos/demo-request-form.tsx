import { Form } from '@inertiajs/react';
import { Save } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { DEMO_CHOICES } from '@/lib/demo-requests';
import { update } from '@/routes/demo-requests';
import type { DemoRequestRow } from '@/types';

/** The status and the internal note of a request, saved together. One already converted keeps its status. */
export default function DemoRequestForm({
    request,
}: {
    request: DemoRequestRow;
}) {
    const converted = request.status === 'converted';

    return (
        <Form
            {...update.form(request.id)}
            options={{ preserveScroll: true }}
            key={`${request.id}-${request.status}-${request.note}`}
            className="grid gap-4"
        >
            {({ processing, errors }) => (
                <>
                    {converted ? (
                        <input type="hidden" name="status" value="converted" />
                    ) : (
                        <div className="grid max-w-64 gap-2">
                            <Label htmlFor={`status-${request.id}`}>
                                Estado
                            </Label>
                            <Select name="status" defaultValue={request.status}>
                                <SelectTrigger id={`status-${request.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {DEMO_CHOICES.map((choice) => (
                                        <SelectItem
                                            key={choice.value}
                                            value={choice.value}
                                        >
                                            {choice.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors['status']} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor={`note-${request.id}`}>
                            Nota interna
                        </Label>
                        <Textarea
                            id={`note-${request.id}`}
                            name="note"
                            maxLength={2000}
                            defaultValue={request.note ?? ''}
                            placeholder="Qué se habló, qué sigue, quién la llevó…"
                        />
                        <InputError message={errors['note']} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        className="w-fit"
                        disabled={processing}
                    >
                        {processing ? <Spinner /> : <Save aria-hidden />}
                        Guardar
                    </Button>
                </>
            )}
        </Form>
    );
}
