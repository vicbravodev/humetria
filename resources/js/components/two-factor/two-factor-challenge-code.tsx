import { REGEXP_ONLY_DIGITS } from 'input-otp';
import InputError from '@/components/input-error';
import OtpSlots from '@/components/two-factor/otp-slots';
import { InputOTP } from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';

type Props = {
    code: string;
    onChange: (value: string) => void;
    disabled: boolean;
    error?: string;
};

export default function TwoFactorChallengeCode({
    code,
    onChange,
    disabled,
    error,
}: Props) {
    return (
        <div className="flex flex-col items-center justify-center space-y-3 text-center">
            <div className="flex w-full items-center justify-center">
                <InputOTP
                    name="code"
                    aria-label="Authentication code"
                    maxLength={OTP_MAX_LENGTH}
                    value={code}
                    onChange={(value) => onChange(value)}
                    disabled={disabled}
                    pattern={REGEXP_ONLY_DIGITS}
                    autoFocus
                >
                    <OtpSlots />
                </InputOTP>
            </div>
            <InputError message={error} />
        </div>
    );
}
