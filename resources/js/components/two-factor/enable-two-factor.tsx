import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { enable } from '@/routes/two-factor';

type Props = {
    hasSetupData: boolean;
    onShowSetup: () => void;
};

export default function EnableTwoFactor({ hasSetupData, onShowSetup }: Props) {
    return (
        <div className="flex flex-col items-start justify-start space-y-4">
            <p className="text-sm text-muted-foreground">
                When you enable two-factor authentication, you will be prompted
                for a secure pin during login. This pin can be retrieved from a
                TOTP-supported application on your phone.
            </p>

            <div>
                {hasSetupData ? (
                    <Button onClick={onShowSetup}>
                        <ShieldCheck />
                        Continue setup
                    </Button>
                ) : (
                    <Form {...enable.form()} onSuccess={onShowSetup}>
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                Enable 2FA
                            </Button>
                        )}
                    </Form>
                )}
            </div>
        </div>
    );
}
