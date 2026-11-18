import { Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    parsePeople,
    SLIDER_MAX,
    SLIDER_MIN,
    sliderValue,
} from '@/lib/pricing';

type Props = { people: number; onChange: (people: number) => void };

/** The one question of the section: how many people the company has. The cost of every plan answers it. */
export default function PricingPeopleField({ people, onChange }: Props) {
    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:gap-6">
            <Label htmlFor="pricing-people" className="text-base font-medium">
                ¿Cuántas personas hay en tu empresa?
            </Label>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Una persona menos"
                    disabled={people <= 1}
                    onClick={() => onChange(people - 1)}
                >
                    <Minus aria-hidden />
                </Button>
                <Input
                    id="pricing-people"
                    data-test="pricing-people"
                    inputMode="numeric"
                    autoComplete="off"
                    className="h-11 w-24 text-center text-base"
                    value={people}
                    onChange={(event) => {
                        const next = parsePeople(event.target.value);

                        if (next !== null) {
                            onChange(next);
                        }
                    }}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Una persona más"
                    onClick={() => onChange(people + 1)}
                >
                    <Plus aria-hidden />
                </Button>
            </div>
            <input
                type="range"
                data-test="pricing-slider"
                aria-label="Número de personas"
                min={SLIDER_MIN}
                max={SLIDER_MAX}
                value={sliderValue(people)}
                onChange={(event) => onChange(Number(event.target.value))}
                className="h-11 w-full max-w-xs cursor-pointer accent-primary"
            />
        </div>
    );
}
