import { evidenceDots } from '@/lib/attendance/report';
import { cn } from '@/lib/utils';

/** The evidence of a day (board A7): three dots, as many filled as its weakest mark allows, and its name. */
export default function ReportEvidence({ level }: { level: string }) {
    const filled = evidenceDots(level);

    return (
        <span className="inline-flex items-center gap-2">
            <span aria-hidden className="inline-flex gap-0.5">
                {[1, 2, 3].map((dot) => (
                    <i
                        key={dot}
                        className={cn(
                            'size-1.5 rounded-full',
                            dot <= filled ? 'bg-primary' : 'bg-track',
                        )}
                    />
                ))}
            </span>
            {level}
        </span>
    );
}
