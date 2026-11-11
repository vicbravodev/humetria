import type { KeyboardEvent } from 'react';
import GlideChips from '@/components/landing/glide-chips';
import {
    FAMILIES,
    type LandingFamily,
} from '@/components/landing/landing-families';
import { nextTab } from '@/lib/tabs';
import { cn } from '@/lib/utils';

type Props = {
    selected: LandingFamily;
    onSelect: (family: LandingFamily) => void;
};

/** The three families as one segmented control: the brand chip glides to the one chosen. */
export default function FamilyTabs({ selected, onSelect }: Props) {
    const move = (
        event: KeyboardEvent<HTMLButtonElement>,
        index: number,
    ): void => {
        const next = nextTab(event.key, index, FAMILIES.length);
        const family = next === null ? undefined : FAMILIES[next];

        if (family === undefined) {
            return;
        }

        event.preventDefault();
        onSelect(family);
        document
            .getElementById(`family-tab-${family.key}`)
            ?.focus({ preventScroll: true });
    };

    return (
        <div
            role="tablist"
            aria-label="Familias de herramientas"
            className="grid grid-cols-3 gap-1 rounded-2xl border bg-card p-1 lg:max-w-[720px]"
        >
            {FAMILIES.map((family, index) => {
                const current = family.key === selected.key;

                return (
                    <button
                        key={family.key}
                        type="button"
                        role="tab"
                        id={`family-tab-${family.key}`}
                        data-test={`family-tab-${family.key}`}
                        aria-selected={current}
                        aria-controls="tool-rail"
                        tabIndex={current ? 0 : -1}
                        onClick={() => onSelect(family)}
                        onKeyDown={(event) => move(event, index)}
                        className={cn(
                            'relative grid min-h-14 content-center justify-items-center gap-0.5 rounded-xl px-1.5 py-2 text-center transition-[color,transform] duration-200 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:scale-[0.98] sm:min-h-16',
                            current
                                ? 'text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <GlideChips
                            group="family"
                            current={current}
                            hovered={false}
                            className="rounded-xl"
                        />
                        <span className="relative text-meta leading-tight font-semibold lg:text-base">
                            <span className="hidden sm:inline">
                                {family.name}
                            </span>
                            <span className="sm:hidden">{family.short}</span>
                        </span>
                        <span className="relative hidden text-xs leading-tight opacity-90 sm:block">
                            {family.hint}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
