import { Form, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { discover } from '@/routes/sso';

/**
 * The other way in of the login: the work email decides which company's provider to go to. Whatever goes wrong (here or on
 * the way back) says the same thing, so nothing tells who has single sign-on.
 */
export default function SsoEntry() {
    const refused = usePage().props.errors['sso'];

    return (
        <Form
            {...discover.form()}
            className="flex flex-col gap-3 border-t pt-6"
        >
            {({ processing }) => (
                <>
                    <div className="grid gap-1.5">
                        <Label htmlFor="sso-email">
                            ¿Tu empresa usa inicio de sesión único?
                        </Label>
                        <Input
                            id="sso-email"
                            type="email"
                            name="email"
                            required
                            autoComplete="email"
                            placeholder="nombre@empresa.mx"
                        />
                        <InputError message={refused} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={processing}
                    >
                        {processing ? <Spinner /> : <KeyRound aria-hidden />}
                        Continuar con SSO
                    </Button>
                </>
            )}
        </Form>
    );
}
