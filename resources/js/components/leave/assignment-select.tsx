import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/vacations/assignments';
import type { LeavePolicyOption } from '@/types';

/** The value of «no policy»: whoever has it follows the law. */
const LAW = 'law';

/**
 * Chooses the policy of one target (the company, a staff group or a job grade) and sends it as soon as it is chosen. «Ley»
 * takes the policy away. A viewer sees the choice and cannot change it.
 */
export default function AssignmentSelect({
    label,
    target,
    value,
    options,
    disabled,
}: {
    /** What it is for, said to screen readers. */
    label: string;
    /** The target as the server takes it: the scope with its group or grade. */
    target: Record<string, string>;
    /** The id of the policy; null for the law. */
    value: string | null;
    options: LeavePolicyOption[];
    disabled: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Select
            value={value ?? LAW}
            disabled={disabled}
            onValueChange={(next) =>
                router.put(
                    update.url(tenantSlug),
                    { ...target, policy_id: next === LAW ? '' : next },
                    { preserveScroll: true },
                )
            }
        >
            <SelectTrigger aria-label={label} className="w-full sm:w-64">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={LAW}>Ley (sin política)</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
