import { ListChecks } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import type { SeatEditing, SeatFeatureOption } from '@/types';

/** The nine characteristics of Quinta F: each one the seat has, with what the DOF says it gives. */
export default function SeatFeaturesFields({
    seat,
    features,
}: {
    seat: SeatEditing | null;
    features: SeatFeatureOption[];
}) {
    return (
        <SettingRow
            icon={ListChecks}
            title="Características"
            description="Marca las que tiene este asiento."
        >
            <div className="grid gap-2 sm:grid-cols-2">
                {features.map((feature) => (
                    <label key={feature.value} className={CHOICE_CLASS}>
                        <input type="hidden" name={feature.value} value="0" />
                        <input
                            type="checkbox"
                            name={feature.value}
                            value="1"
                            defaultChecked={
                                seat?.features[feature.value] === true
                            }
                            className="size-5 shrink-0 accent-primary"
                        />
                        <span>
                            {feature.label}
                            <span className="block text-caption text-muted-foreground">
                                {feature.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
        </SettingRow>
    );
}
