import { InputError, Label, PhoneInput } from 'humetria';

export const Mexico = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="employee-phone">Celular</Label>
        <PhoneInput id="employee-phone" name="phone" defaultValue="+528112345678" />
    </div>
);

export const Empty = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="contact-phone">Celular para WhatsApp</Label>
        <PhoneInput id="contact-phone" name="phone" />
    </div>
);

export const OtherCountry = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="remote-phone">Celular</Label>
        <PhoneInput id="remote-phone" name="phone" defaultValue="+12145551234" />
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="bad-phone">Celular</Label>
        <PhoneInput id="bad-phone" name="phone" defaultValue="+52551234" invalid describedBy="bad-phone-error" />
        <InputError id="bad-phone-error" message="Escribe los 10 dígitos del celular." />
    </div>
);
