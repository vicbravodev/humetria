import {
    Button,
    Input,
    Label,
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from 'humetria';

export const AddEmployee = () => (
    <Sheet open onOpenChange={() => {}}>
        <SheetContent onOpenAutoFocus={(e) => e.preventDefault()} className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[520px] sm:rounded-xl sm:border-0 sm:shadow-float">
            <SheetHeader className="gap-0.5 border-b px-6 pt-5 pb-4">
                <SheetTitle className="text-lg leading-[26px]">Agregar empleado</SheetTitle>
                <SheetDescription>Le llegará la evaluación por correo o WhatsApp.</SheetDescription>
            </SheetHeader>
            <div className="grid flex-1 content-start gap-6 overflow-y-auto px-6 py-5">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="sheet-name">Nombre completo</Label>
                    <Input id="sheet-name" defaultValue="Jorge Salinas Ruiz" />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="sheet-email">Correo</Label>
                    <Input id="sheet-email" type="email" placeholder="nombre@empresa.com" />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="sheet-phone">Celular</Label>
                    <Input id="sheet-phone" type="tel" defaultValue="81 1234 5678" />
                </div>
            </div>
            <SheetFooter className="flex-row justify-end gap-2 border-t px-6 py-4">
                <Button variant="outline">Cancelar</Button>
                <Button>Agregar empleado</Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
);
