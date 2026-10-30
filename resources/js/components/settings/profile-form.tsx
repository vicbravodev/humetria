import { Form, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Initials from '@/components/humetria/initials';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { send } from '@/routes/verification';

/** Your name and email, beside your initials (pantallas: Ajustes, Perfil). */
export default function ProfileForm({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string | undefined;
}) {
    const { user } = usePage().props.auth;
    const unverified = mustVerifyEmail && user.email_verified_at === null;

    return (
        <Form
            {...ProfileController.update.form()}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-[22px]"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex items-center gap-4">
                        <Initials
                            name={user.name}
                            className="size-16 bg-accent text-xl text-primary"
                        />
                        <div className="flex flex-col">
                            <h2 className="font-medium">{user.name}</h2>
                            <p className="text-sm text-muted-foreground">
                                {user.email}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="name">Nombre</Label>
                            <Input
                                id="name"
                                defaultValue={user.name}
                                name="name"
                                required
                                autoComplete="name"
                                placeholder="Nombre completo"
                            />
                            <InputError message={errors['name']} />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                defaultValue={user.email}
                                name="email"
                                required
                                autoComplete="username"
                                placeholder="nombre@empresa.mx"
                                aria-describedby={
                                    mustVerifyEmail ? 'email-help' : undefined
                                }
                            />
                            {mustVerifyEmail && (
                                <p
                                    id="email-help"
                                    className="text-[13px] leading-[18px] text-muted-foreground"
                                >
                                    Si lo cambias, te pediremos confirmarlo.
                                </p>
                            )}
                            <InputError message={errors['email']} />
                        </div>
                    </div>
                    {unverified && (
                        <p className="text-sm text-muted-foreground">
                            Tu correo no está verificado.{' '}
                            <Link
                                href={send()}
                                as="button"
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Reenviar verificación.
                            </Link>
                            {status === 'verification-link-sent' && (
                                <span className="mt-2 block font-medium text-primary">
                                    Te enviamos un nuevo enlace de verificación.
                                </span>
                            )}
                        </p>
                    )}
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button
                            disabled={processing}
                            data-test="update-profile-button"
                        >
                            Guardar cambios
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
