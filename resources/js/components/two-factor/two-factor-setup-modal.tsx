import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import GridScanIcon from '@/components/two-factor/grid-scan-icon';
import TwoFactorSetupStep from '@/components/two-factor/two-factor-setup-step';
import TwoFactorVerificationStep from '@/components/two-factor/two-factor-verification-step';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    isOpen: boolean;
    onClose: () => void;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
};

type ModalConfig = {
    title: string;
    description: string;
    buttonText: string;
};

function modalConfigFor(
    twoFactorEnabled: boolean,
    showVerificationStep: boolean,
): ModalConfig {
    if (twoFactorEnabled) {
        return {
            title: 'Verificación en dos pasos activada',
            description:
                'La verificación en dos pasos ya está activada. Escanea el código QR o escribe la clave de configuración en tu aplicación de autenticación.',
            buttonText: 'Cerrar',
        };
    }

    if (showVerificationStep) {
        return {
            title: 'Confirma tu código de verificación',
            description:
                'Escribe el código de 6 dígitos de tu aplicación de autenticación',
            buttonText: 'Continuar',
        };
    }

    return {
        title: 'Activar la verificación en dos pasos',
        description:
            'Para terminar de activarla, escanea el código QR o escribe la clave de configuración en tu aplicación de autenticación',
        buttonText: 'Continuar',
    };
}

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: Props) {
    const [showVerificationStep, setShowVerificationStep] =
        useState<boolean>(false);

    const modalConfig = useMemo(
        () => modalConfigFor(twoFactorEnabled, showVerificationStep),
        [twoFactorEnabled, showVerificationStep],
    );

    const resetModalState = useCallback(() => {
        if (twoFactorEnabled) {
            clearSetupData();
        }

        setShowVerificationStep(false);
    }, [clearSetupData, twoFactorEnabled]);

    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);

    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);

            return;
        }

        clearSetupData();
        handleClose();
    }, [requiresConfirmation, clearSetupData, handleClose]);

    const fetchSetupDataRef = useRef(fetchSetupData);

    useEffect(() => {
        fetchSetupDataRef.current = fetchSetupData;
    }, [fetchSetupData]);

    useEffect(() => {
        if (isOpen && !qrCodeSvg) {
            void fetchSetupDataRef.current();
        }
    }, [isOpen, qrCodeSvg]);

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="flex items-center justify-center">
                    <GridScanIcon />
                    <DialogTitle>{modalConfig.title}</DialogTitle>
                    <DialogDescription className="text-center">
                        {modalConfig.description}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center space-y-5">
                    {showVerificationStep ? (
                        <TwoFactorVerificationStep
                            onClose={handleClose}
                            onBack={() => setShowVerificationStep(false)}
                        />
                    ) : (
                        <TwoFactorSetupStep
                            qrCodeSvg={qrCodeSvg}
                            manualSetupKey={manualSetupKey}
                            buttonText={modalConfig.buttonText}
                            onNextStep={handleModalNextStep}
                            errors={errors}
                        />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
