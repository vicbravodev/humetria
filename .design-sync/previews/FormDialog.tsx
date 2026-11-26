import { DatePicker, FormDialog, Label, Textarea } from 'humetria';

export const LinkExpiry = () => (
    <FormDialog
        open
        onOpenChange={() => {}}
        title="Vencimiento del enlace"
        description="Después de ese día el enlace ya no abre. Sin día, nunca vence. Te avisamos en el resumen 30 días antes."
        form={{ action: '/admin/demo/disclosure/page/expiry', method: 'post' }}
        submit="Guardar"
    >
        {() => (
            <div className="grid gap-2">
                <Label htmlFor="expiry-day">Último día que abre (déjalo vacío para que no venza)</Label>
                <DatePicker id="expiry-day" name="expires_on" defaultValue="2027-01-31" />
            </div>
        )}
    </FormDialog>
);

export const Destructive = () => (
    <FormDialog
        open
        onOpenChange={() => {}}
        tone="destructive"
        title="Marcar como incorrecta"
        description="La marca sin conexión de Ana Pérez del 18 de noviembre deja de contar en su asistencia."
        form={{ action: '/admin/demo/attendance/offline/91/incorrect', method: 'post' }}
        submit="Marcar como incorrecta"
    >
        {() => (
            <div className="grid gap-2">
                <Label htmlFor="incorrect-reason">Motivo</Label>
                <Textarea
                    id="incorrect-reason"
                    name="reason"
                    defaultValue="El reloj del teléfono estaba adelantado una hora."
                />
            </div>
        )}
    </FormDialog>
);
