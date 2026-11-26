import {
    Button,
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    Label,
    Textarea,
} from 'humetria';

export const RejectRequest = () => (
    <Dialog open onOpenChange={() => {}}>
        <DialogContent onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader>
                <DialogTitle>¿Rechazar las vacaciones de Mariana López?</DialogTitle>
                <DialogDescription>
                    Pidió 5 días, del 22 al 26 de diciembre. Le llegará el motivo y sus días vuelven a su saldo.
                </DialogDescription>
            </DialogHeader>
            <div className="flex flex-col gap-2">
                <Label htmlFor="reject-reason">Motivo</Label>
                <Textarea
                    id="reject-reason"
                    defaultValue="Ese periodo coincide con el cierre de inventario de Planta Apodaca."
                />
            </div>
            <DialogFooter>
                <Button variant="outline">Cancelar</Button>
                <Button variant="destructive">Rechazar solicitud</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
);
