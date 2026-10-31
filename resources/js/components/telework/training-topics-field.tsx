import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { TrainingChoice } from '@/types';

/** The 8.1 topics the training covered: at least one. */
export default function TrainingTopicsField({
    topics,
    error,
}: {
    topics: TrainingChoice[];
    error: string | undefined;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 font-medium">Temas que cubrió</legend>
            <ul className="grid gap-2">
                {topics.map((topic) => (
                    <li key={topic.value} className="flex items-center gap-3">
                        <Checkbox
                            id={`training-topic-${topic.value}`}
                            name="topics[]"
                            value={topic.value}
                        />
                        <Label htmlFor={`training-topic-${topic.value}`}>
                            {topic.label}
                        </Label>
                    </li>
                ))}
            </ul>
            <InputError role="alert" message={error} />
        </fieldset>
    );
}
