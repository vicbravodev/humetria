import { Input, InputError, Label, FormSheet } from 'humetria';

export const AddEmployee = () => (
    <FormSheet
        open
        onOpenChange={() => {}}
        title="Agregar empleado"
        description="Le llega la evaluación por correo o WhatsApp. Puedes completar sus datos después."
        form={{ action: '/admin/demo/employees', method: 'post' }}
        submit="Agregar empleado"
    >
        {() => (
            <>
                <div className="grid gap-2">
                    <Label htmlFor="employee-name">Nombre completo</Label>
                    <Input id="employee-name" name="name" defaultValue="Ana Pérez Garza" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="employee-email">Correo</Label>
                    <Input id="employee-email" name="email" type="email" defaultValue="ana.perez@empresa.mx" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="employee-number">Número de empleado</Label>
                    <Input id="employee-number" name="number" defaultValue="A-1042" />
                </div>
            </>
        )}
    </FormSheet>
);

export const EditWithError = () => (
    <FormSheet
        open
        onOpenChange={() => {}}
        title="Editar empleado"
        description="Corrige los datos de Ana Pérez Garza."
        form={{ action: '/admin/demo/employees/42', method: 'put' }}
        submit="Guardar cambios"
    >
        {() => (
            <>
                <div className="grid gap-2">
                    <Label htmlFor="edit-name">Nombre completo</Label>
                    <Input id="edit-name" name="name" defaultValue="Ana Pérez Garza" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="edit-email">Correo</Label>
                    <Input id="edit-email" name="email" defaultValue="ana.perez@" aria-invalid />
                    <InputError message="Escribe un correo válido." />
                </div>
            </>
        )}
    </FormSheet>
);
