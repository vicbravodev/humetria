import { usePage } from '@inertiajs/react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useHumiSuggestionsSetting } from '@/hooks/use-humi-suggestions-setting';
import { invitationKey } from '@/lib/assistant/invitation-store';

/** «Sugerencias de la burbuja»: where the person turns on again what they declined in an invitation, or turns it off. */
export default function HumiSuggestionsToggle({ slug }: { slug: string }) {
    const { props } = usePage();
    const [on, turn] = useHumiSuggestionsSetting(
        invitationKey(props.auth.user.id, slug),
    );

    return (
        <div className="flex items-start gap-3">
            <Checkbox
                id="humi-suggestions"
                checked={on}
                onCheckedChange={(next) => turn(next === true)}
                className="mt-0.5"
            />
            <div className="grid gap-0.5">
                <Label htmlFor="humi-suggestions">
                    Sugerencias de la burbuja
                </Label>
                <p className="text-caption text-muted-foreground">
                    Con Humi cerrado, a veces te pregunta por algo real de la
                    página.
                </p>
            </div>
        </div>
    );
}
