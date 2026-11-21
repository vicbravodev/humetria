import { useState } from 'react';
import ReportMarkField from '@/components/attendance/report-mark-field';
import FormSheet from '@/components/humetria/form-sheet';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SheetTrigger } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dayTitle } from '@/lib/attendance/week-text';
import { correct } from '@/routes/tenant/attendance/marks';
import type { ReportMark } from '@/types';

/**
 * RH corrects a mark of a day with no request first (criterio 5.4), as with an approved one: another time the same day, or
 * voided, always with a note. The original stays and the answered week reopens.
 */
export default function ReportCorrectSheet({
    date,
    marks,
}: {
    date: string;
    marks: ReportMark[];
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);
    const [chosen, setChosen] = useState(marks[0]?.id ?? '');
    const [time, setTime] = useState(marks[0]?.time ?? '');
    const [voided, setVoided] = useState(false);
    const mark = marks.find(({ id }) => id === chosen) ?? marks[0];

    if (mark === undefined) {
        return null;
    }

    return (
        <FormSheet
            open={open}
            onOpenChange={setOpen}
            title={`Corregir una marca del ${dayTitle(date).toLowerCase()}`}
            description="La marca original se conserva y las dos se ven en el reporte. Si su semana ya tenía respuesta, se reabre."
            form={correct.form({ tenant: tenantSlug, mark: mark.id })}
            submit="Corregir marca"
            trigger={
                <SheetTrigger asChild>
                    <Button size="sm" variant="outline">
                        Corregir
                    </Button>
                </SheetTrigger>
            }
        >
            {(errors) => (
                <>
                    <ReportMarkField
                        marks={marks}
                        value={mark.id}
                        onChange={(id) => {
                            setChosen(id);
                            setTime(
                                marks.find((each) => each.id === id)?.time ??
                                    '',
                            );
                        }}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="mark-time">Nueva hora</Label>
                        <Input
                            id="mark-time"
                            type="time"
                            value={time}
                            disabled={voided}
                            onChange={(event) => setTime(event.target.value)}
                            className="w-36"
                        />
                        <input
                            type="hidden"
                            name="at"
                            value={voided ? '' : `${mark.date} ${time}`}
                        />
                        <InputError message={errors['at']} />
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Checkbox
                            id="mark-void"
                            name="void"
                            value="1"
                            checked={voided}
                            onCheckedChange={(checked) =>
                                setVoided(checked === true)
                            }
                        />
                        <Label htmlFor="mark-void">
                            Anular esta marca (no debió existir)
                        </Label>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="mark-note">Nota</Label>
                        <Textarea
                            id="mark-note"
                            name="note"
                            required
                            minLength={10}
                            maxLength={500}
                            rows={3}
                            aria-invalid={
                                errors['note'] === undefined ? undefined : true
                            }
                            placeholder="Por ejemplo: Llegó a las 9:00; el lector de QR falló ese día."
                        />
                        <InputError message={errors['note']} />
                    </div>
                </>
            )}
        </FormSheet>
    );
}
