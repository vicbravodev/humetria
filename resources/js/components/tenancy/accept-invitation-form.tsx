import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accept } from '@/routes/invitations';

type Props = { token: string; email: string; name: string };

/** Creates the invited owner's account: the email is the invitation's and cannot change. */
export default function AcceptInvitationForm({ token, email, name }: Props) {
    return (
        <Form
            {...accept.form(token)}
            resetOnSuccess={['password', 'password_confirmation']}
            className="grid gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={email}
                            readOnly
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Nombre</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={name}
                            required
                            maxLength={255}
                            autoComplete="name"
                        />
                        <InputError message={errors['name']} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Contraseña</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autoFocus
                            autoComplete="new-password"
                        />
                        <InputError message={errors['password']} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirmar contraseña
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autoComplete="new-password"
                        />
                        <InputError message={errors['password_confirmation']} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Aceptar invitación
                    </Button>
                </>
            )}
        </Form>
    );
}
