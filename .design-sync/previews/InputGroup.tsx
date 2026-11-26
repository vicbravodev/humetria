import { InputError, InputGroup, InputGroupInput, InputGroupText, Label } from 'humetria';

export const Pesos = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="monthly-salary">Salario mensual</Label>
        <InputGroup>
            <InputGroupText>$</InputGroupText>
            <InputGroupInput id="monthly-salary" inputMode="decimal" defaultValue="18,500.00" />
            <InputGroupText className="pr-4">MXN</InputGroupText>
        </InputGroup>
    </div>
);

export const AddressPrefix = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="tenant-slug">Dirección de tu empresa</Label>
        <InputGroup>
            <InputGroupText>/admin/</InputGroupText>
            <InputGroupInput id="tenant-slug" defaultValue="grupo-norteno" />
        </InputGroup>
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="payment-amount">Monto del pago</Label>
        <InputGroup>
            <InputGroupText>$</InputGroupText>
            <InputGroupInput id="payment-amount" defaultValue="-250" aria-invalid aria-describedby="payment-amount-error" />
            <InputGroupText className="pr-4">MXN</InputGroupText>
        </InputGroup>
        <InputError id="payment-amount-error" message="El monto debe ser mayor a cero." />
    </div>
);

export const Disabled = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="seat-price">Precio por persona</Label>
        <InputGroup>
            <InputGroupText>$</InputGroupText>
            <InputGroupInput id="seat-price" defaultValue="45.00" disabled />
            <InputGroupText className="pr-4">MXN</InputGroupText>
        </InputGroup>
    </div>
);
