import { Form } from '@inertiajs/react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { update } from '@/routes/tenant/org-chart/manager';
import { options } from '@/routes/tenant/org-chart/people';
import type { QualityPerson } from '@/types';

/**
 * The manager of a person, chosen on their row: searched on the server among the active people, without the person
 * and whoever reports to them; whoever has a manager may also be left with nobody above. «Asignar» is the primary
 * button only once something was chosen: until then there is nothing to save.
 */
export default function OrgQualityFix({ person }: { person: QualityPerson }) {
    const tenantSlug = useTenantSlug();
    const [chosen, setChosen] = useState(false);
    const id = `manager-${person.id}`;
    const people = `${options.url(tenantSlug)}?${new URLSearchParams({ except: person.id }).toString()}`;

    return (
        <Form
            {...update.form({ tenant: tenantSlug, employee: person.id })}
            options={{ preserveScroll: true }}
            className="grid gap-1.5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex items-center gap-2">
                        <Label htmlFor={id} className="sr-only">
                            Jefe directo de {person.name}
                        </Label>
                        <div className="min-w-0 flex-1 md:w-64 md:flex-none">
                            <Combobox
                                id={id}
                                name="manager_id"
                                source={serverSource(
                                    people,
                                    person.manager === null
                                        ? []
                                        : [
                                              {
                                                  value: '',
                                                  label: 'Sin jefe directo',
                                              },
                                          ],
                                )}
                                defaultValue={person.manager ?? undefined}
                                onChange={() => setChosen(true)}
                                placeholder="Elige a su jefe…"
                                invalid={errors['manager_id'] !== undefined}
                                describedBy={
                                    errors['manager_id'] === undefined
                                        ? undefined
                                        : `${id}-error`
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            variant={chosen ? 'default' : 'outline'}
                            disabled={processing || !chosen}
                            aria-label={`Asignar jefe a ${person.name}`}
                        >
                            {processing && <Spinner />}
                            Asignar
                        </Button>
                    </div>
                    <InputError
                        id={`${id}-error`}
                        message={errors['manager_id']}
                    />
                </>
            )}
        </Form>
    );
}
