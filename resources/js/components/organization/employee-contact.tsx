import { Mail, Smartphone } from 'lucide-react';
import { formatMobile } from '@/lib/employees';
import { cn } from '@/lib/utils';
import type { EmployeeRow } from '@/types';

const channel = (on: boolean) =>
    cn('size-4 shrink-0', on ? 'text-primary' : 'text-muted-foreground/35');

/**
 * How the surveys reach someone: both channels as icons, dimmed when missing, then the email or, without one, the
 * mobile. The other mobile is in the phone's label, for screen readers and on hover.
 */
export default function EmployeeContact({
    employee,
}: {
    employee: EmployeeRow;
}) {
    const mobile =
        employee.mobile === null ? null : formatMobile(employee.mobile);

    return (
        <div className="flex min-w-0 items-center gap-2">
            <Mail aria-hidden className={channel(employee.email !== null)} />
            <span
                role="img"
                aria-label={
                    mobile === null ? 'Sin celular' : `Celular ${mobile}`
                }
                title={mobile ?? 'Sin celular'}
            >
                <Smartphone aria-hidden className={channel(mobile !== null)} />
            </span>
            <span className="truncate tabular-nums">
                {employee.email ?? mobile}
            </span>
        </div>
    );
}
