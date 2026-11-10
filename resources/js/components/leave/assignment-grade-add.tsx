import { router } from '@inertiajs/react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { update } from '@/routes/tenant/vacations/assignments';
import { options as gradeOptions } from '@/routes/tenant/vacations/grades';
import type { LeavePolicyOption } from '@/types';

/** Gives a job grade a policy of its own: the grade is looked up on the server, as many as the company has. */
export default function AssignmentGradeAdd({
    options,
}: {
    options: LeavePolicyOption[];
}) {
    const tenantSlug = useTenantSlug();
    const [gradeId, setGradeId] = useState('');
    const [policyId, setPolicyId] = useState('');

    return (
        <div className="flex flex-wrap items-end gap-3 border-t pt-4">
            <div className="grid min-w-56 flex-1 gap-1.5 sm:max-w-xs">
                <label
                    htmlFor="assignment-grade"
                    className="text-sm font-medium"
                >
                    Agregar un nivel de puesto
                </label>
                <Combobox
                    id="assignment-grade"
                    name="job_grade_id"
                    source={serverSource(gradeOptions.url(tenantSlug), [])}
                    placeholder="Busca categoría o nivel"
                    onChange={(option) => setGradeId(option.value)}
                />
            </div>
            <Select value={policyId} onValueChange={setPolicyId}>
                <SelectTrigger
                    aria-label="Política del nivel"
                    className="w-full sm:w-64"
                >
                    <SelectValue placeholder="Elige la política" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button
                type="button"
                variant="outline"
                disabled={gradeId === '' || policyId === ''}
                onClick={() =>
                    router.put(
                        update.url(tenantSlug),
                        {
                            scope: 'job_grade',
                            job_grade_id: gradeId,
                            policy_id: policyId,
                        },
                        { preserveScroll: true },
                    )
                }
            >
                Asignar
            </Button>
        </div>
    );
}
