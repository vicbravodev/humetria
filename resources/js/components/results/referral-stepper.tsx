import { REFERRAL_STEPS } from '@/lib/referral';
import { cn } from '@/lib/utils';

/** Where a person's channeling stands: Por canalizar, Canalizada, Atendida (pantallas: Canalizacion). */
export default function ReferralStepper({ step }: { step: number }) {
    return (
        <ol
            aria-label="Avance de la canalización"
            className="flex flex-wrap items-center gap-x-4 gap-y-1 text-caption sm:flex-nowrap sm:gap-x-2"
        >
            {REFERRAL_STEPS.map((label, index) => (
                <li
                    key={label}
                    aria-current={index === step ? 'step' : undefined}
                    className="flex items-center gap-2 whitespace-nowrap"
                >
                    {index > 0 && (
                        <span
                            aria-hidden
                            className={cn(
                                'hidden h-px w-8 sm:block',
                                index <= step ? 'bg-primary' : 'bg-border',
                            )}
                        />
                    )}
                    <span
                        aria-hidden
                        className={cn(
                            'size-2.5 rounded-full border-2',
                            index <= step
                                ? 'border-primary bg-primary'
                                : 'border-muted-foreground',
                        )}
                    />
                    <span
                        className={
                            index === step
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground'
                        }
                    >
                        {label}
                    </span>
                </li>
            ))}
        </ol>
    );
}
