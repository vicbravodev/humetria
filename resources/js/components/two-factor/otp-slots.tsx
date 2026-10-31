import { InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';

export default function OtpSlots() {
    return (
        <InputOTPGroup>
            {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                <InputOTPSlot key={index} index={index} className="size-11" />
            ))}
        </InputOTPGroup>
    );
}
