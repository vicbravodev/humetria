import { X } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { addGrade, removeGrade } from '@/lib/compensation/benefits';
import { serverSource } from '@/lib/server-options';
import type { ChoiceOption } from '@/types';

/**
 * The categories and levels a benefit is for, searched on the server and added one at a time as chips. With none,
 * it is for every category.
 */
export default function BenefitGradesField({
    gradesUrl,
    initial,
    error,
}: {
    gradesUrl: string;
    initial: ChoiceOption[];
    error: string | undefined;
}) {
    const [grades, setGrades] = useState(initial);

    return (
        <div className="grid gap-2">
            <Label htmlFor="benefit-grade-search">
                Categorías del tabulador
            </Label>
            {/* Remounted after each choice so it asks for the next one. */}
            <Combobox
                key={grades.length}
                id="benefit-grade-search"
                name=""
                source={serverSource(gradesUrl)}
                placeholder="Todas las categorías (busca para limitar)"
                onChange={(option) =>
                    setGrades((current) => addGrade(current, option))
                }
            />
            {grades.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {grades.map((grade) => (
                        <li
                            key={grade.value}
                            className="flex items-center gap-1 rounded-full bg-accent py-1 pr-1 pl-3 text-sm text-primary"
                        >
                            <input
                                type="hidden"
                                name="job_grade_ids[]"
                                value={grade.value}
                            />
                            {grade.label}
                            <button
                                type="button"
                                aria-label={`Quitar ${grade.label}`}
                                onClick={() =>
                                    setGrades((current) =>
                                        removeGrade(current, grade.value),
                                    )
                                }
                                className="flex size-6 items-center justify-center rounded-full hover:bg-card focus-visible:outline-2 focus-visible:outline-ring"
                            >
                                <X aria-hidden className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            <InputError message={error} />
        </div>
    );
}
