import type { Option } from '@/lib/option-search';

/** Adds a choice to a list built one by one (centers, people), once each; the "all" choice (empty value) is never added. */
export function addChoice(chosen: Option[], choice: Option): Option[] {
    return choice.value === '' ||
        chosen.some((option) => option.value === choice.value)
        ? chosen
        : [...chosen, choice];
}

export function removeChoice(chosen: Option[], value: string): Option[] {
    return chosen.filter((option) => option.value !== value);
}
