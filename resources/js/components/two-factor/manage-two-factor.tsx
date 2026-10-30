import { Form } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Smartphone } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import EnableTwoFactor from '@/components/two-factor/enable-two-factor';
import TwoFactorRecoveryCodes from '@/components/two-factor/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor/two-factor-setup-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean | undefined;
    requiresConfirmation?: boolean | undefined;
    twoFactorEnabled?: boolean | undefined;
};

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <SettingRow
            icon={Smartphone}
            title="Verificación en dos pasos"
            status={
                twoFactorEnabled ? (
                    <Badge variant="success">Activada</Badge>
                ) : (
                    <Badge variant="secondary">Desactivada</Badge>
                )
            }
            description="Al entrar, te pediremos también un código de una app de autenticación en tu celular."
            action={
                twoFactorEnabled ? (
                    <Form {...disable.form()}>
                        {({ processing }) => (
                            <Button
                                variant="outline"
                                type="submit"
                                disabled={processing}
                                aria-label="Desactivar verificación en dos pasos"
                            >
                                Desactivar
                            </Button>
                        )}
                    </Form>
                ) : (
                    <EnableTwoFactor
                        hasSetupData={hasSetupData}
                        onShowSetup={() => setShowSetupModal(true)}
                    />
                )
            }
        >
            {twoFactorEnabled && (
                <TwoFactorRecoveryCodes
                    recoveryCodesList={recoveryCodesList}
                    fetchRecoveryCodes={fetchRecoveryCodes}
                    errors={errors}
                />
            )}
            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </SettingRow>
    );
}
