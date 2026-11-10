import { Check } from 'lucide-react';
import { Fragment } from 'react';
import { linkProgress } from '@/lib/attendance/devices';
import type { StepState } from '@/lib/attendance/devices';
import { cn } from '@/lib/utils';
import type { DevicePerson } from '@/types';

const NAMES = ['Enlace', 'Acuerdo', 'Llave'];

const SAID: Record<StepState, string> = {
    done: 'hecho',
    current: 'en espera',
    todo: 'pendiente',
};

/** The road «Enlace → Acuerdo → Llave» of a person, three dots joined by a line, and in a line where they are. */
export default function LinkSteps({
    person,
}: {
    person: Pick<DevicePerson, 'status' | 'link' | 'devices'>;
}) {
    const { steps, caption } = linkProgress(person);

    return (
        <div className="flex flex-col gap-1.5">
            <ol className="flex w-[196px] items-center">
                {steps.map((state, index) => (
                    <Fragment key={NAMES[index]}>
                        {index > 0 && (
                            <li
                                aria-hidden
                                className={cn(
                                    'h-[3px] min-w-3.5 flex-1 rounded-full',
                                    state === 'todo'
                                        ? 'bg-track'
                                        : 'bg-primary',
                                )}
                            />
                        )}
                        <li className="flex">
                            <span
                                className={cn(
                                    'inline-flex size-[22px] items-center justify-center rounded-full border-2',
                                    state === 'done' &&
                                        'border-primary bg-primary text-primary-foreground',
                                    state === 'current' &&
                                        'border-[3px] border-primary bg-accent',
                                    state === 'todo' && 'border-input bg-card',
                                )}
                            >
                                {state === 'done' && (
                                    <Check
                                        aria-hidden
                                        className="size-3.5"
                                        strokeWidth={3}
                                    />
                                )}
                                <span className="sr-only">
                                    {NAMES[index]}: {SAID[state]}
                                </span>
                            </span>
                        </li>
                    </Fragment>
                ))}
            </ol>
            <div
                aria-hidden
                className="flex w-[200px] justify-between text-caption text-muted-foreground"
            >
                {NAMES.map((name) => (
                    <span key={name}>{name}</span>
                ))}
            </div>
            <p className="text-sm">{caption}</p>
        </div>
    );
}
