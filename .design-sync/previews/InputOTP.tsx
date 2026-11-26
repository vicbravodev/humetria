import { InputError, InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot, Label } from 'humetria';

const Slots = ({ from, to }: { from: number; to: number }) => (
    <InputOTPGroup>
        {Array.from({ length: to - from }, (_, i) => (
            <InputOTPSlot key={from + i} index={from + i} className="size-11" />
        ))}
    </InputOTPGroup>
);

export const VerificationCode = () => (
    <div className="flex flex-col items-center gap-3">
        <InputOTP aria-label="Código de verificación" maxLength={6} value="4821" onChange={() => {}}>
            <Slots from={0} to={6} />
        </InputOTP>
    </div>
);

export const SplitInThree = () => (
    <div className="grid gap-2">
        <Label>Código de tu app de autenticación</Label>
        <InputOTP aria-label="Código de verificación" maxLength={6} value="482193" onChange={() => {}}>
            <Slots from={0} to={3} />
            <InputOTPSeparator />
            <Slots from={3} to={6} />
        </InputOTP>
    </div>
);

export const WrongCode = () => (
    <div className="flex flex-col items-center gap-3">
        <InputOTP aria-label="Código de verificación" maxLength={6} value="000000" onChange={() => {}}>
            <Slots from={0} to={6} />
        </InputOTP>
        <InputError message="El código no es válido o ya venció. Pide uno nuevo." />
    </div>
);

export const Disabled = () => (
    <InputOTP aria-label="Código de verificación" maxLength={6} value="482193" onChange={() => {}} disabled>
        <Slots from={0} to={6} />
    </InputOTP>
);
