import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <section
            data-slot="danger-zone"
            aria-labelledby="delete-account"
            className="flex flex-wrap items-center gap-4 rounded-xl border border-risk-alto-bg bg-card px-5 py-[18px]"
        >
            <div className="flex flex-[1_1_260px] flex-col">
                <h2 id="delete-account" className="font-medium">
                    Eliminar cuenta
                </h2>
                <p className="text-sm text-muted-foreground">
                    Pierdes el acceso a tus empresas y no se puede deshacer. Las
                    evaluaciones se conservan.
                </p>
            </div>
            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="outline"
                        data-test="delete-user-button"
                        className="border-destructive text-destructive hover:bg-destructive/10 hover:text-destructive"
                    >
                        Eliminar cuenta
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>¿Eliminar tu cuenta?</DialogTitle>
                    <DialogDescription>
                        Tu cuenta y todos sus datos se eliminarán de forma
                        permanente. Escribe tu contraseña para confirmar.
                    </DialogDescription>

                    <Form
                        {...ProfileController.destroy.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="password"
                                        className="sr-only"
                                    >
                                        Contraseña
                                    </Label>

                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        ref={passwordInput}
                                        placeholder="Contraseña"
                                        autoComplete="current-password"
                                    />

                                    <InputError message={errors['password']} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                resetAndClearErrors()
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                        asChild
                                    >
                                        <button
                                            type="submit"
                                            data-test="confirm-delete-user-button"
                                        >
                                            Eliminar cuenta
                                        </button>
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
