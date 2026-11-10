import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { modeHint } from '@/lib/engagement/pulse';
import type { PulseModeOption } from '@/types';

/** Starts a pulse draft: RH picks what this wave looks at (one of the modes) and nothing is sent until it is launched. */
export default function ModePicker({
    modes,
    label,
}: {
    modes: PulseModeOption[];
    label: string;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();

    return (
        <Form
            {...routes.store.form(tenantSlug)}
            className="flex w-full max-w-md flex-col gap-4 text-left"
        >
            {({ processing, errors }) => (
                <>
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            ¿Qué quieres mirar en este pulso?
                        </legend>
                        {modes.map((mode, position) => (
                            <label
                                key={mode.value}
                                className="flex min-h-[52px] cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                            >
                                <input
                                    type="radio"
                                    name="mode"
                                    value={mode.value}
                                    defaultChecked={position === 0}
                                    className="mt-0.5 size-5 shrink-0 accent-primary"
                                />
                                <span className="flex flex-col">
                                    <span className="font-medium">
                                        {mode.label}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {modeHint(mode.value)}
                                    </span>
                                </span>
                            </label>
                        ))}
                        {errors['mode'] !== undefined && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {errors['mode']}
                            </p>
                        )}
                    </fieldset>
                    <Button type="submit" disabled={processing}>
                        {processing ? <Spinner /> : <Plus aria-hidden="true" />}
                        {label}
                    </Button>
                </>
            )}
        </Form>
    );
}
