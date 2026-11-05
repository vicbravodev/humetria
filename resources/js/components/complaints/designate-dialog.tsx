import { Form, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import DesignateChoice from '@/components/complaints/designate-choice';
import DesignateFields from '@/components/complaints/designate-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { designateWords, eligibleFor } from '@/lib/complaints/channel';
import { store } from '@/routes/tenant/complaints/handlers';
import { index as members } from '@/routes/tenant/members';
import type { CoverageByKind, HandlerCandidate } from '@/types';

/**
 * «¿Quién atiende las quejas contra…?» (board V4-Quejas-Designar): someone of the team or of the committee whom the conflict
 * leaves, for the whole company or one center. Only people with an account (decisión A8): someone from outside is invited
 * first as a user with the role «Atención de quejas».
 */
export default function DesignateDialog({
    open,
    onOpenChange,
    row,
    candidates,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The kind of complaint it is for; none for any. */
    row: CoverageByKind | null;
    candidates: HandlerCandidate[];
}) {
    const tenantSlug = useTenantSlug();
    const people = eligibleFor(candidates, row?.excludedRole ?? null);
    const words = designateWords(row, people);
    const [from, setFrom] = useState(
        people.team.length === 0 ? 'committee' : 'team',
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-[620px]">
                <DialogTitle>{words.title}</DialogTitle>
                <DialogDescription>{words.lead}</DialogDescription>
                <Form
                    {...store.form(tenantSlug)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset className="grid gap-2">
                                <legend className="sr-only">
                                    De dónde viene
                                </legend>
                                <DesignateChoice
                                    value="team"
                                    label="Alguien de tu equipo"
                                    hint={words.teamHint}
                                    checked={from === 'team'}
                                    disabled={people.team.length === 0}
                                    onChange={setFrom}
                                />
                                <DesignateChoice
                                    value="committee"
                                    label="La Comisión de seguridad e higiene"
                                    hint={words.committeeHint}
                                    checked={from === 'committee'}
                                    disabled={people.committee.length === 0}
                                    onChange={setFrom}
                                />
                            </fieldset>
                            <DesignateFields
                                people={
                                    from === 'team'
                                        ? people.team
                                        : people.committee
                                }
                                errors={errors}
                            />
                            <p className="flex items-start gap-3 rounded-lg bg-muted px-4 py-3 text-sm">
                                <ShieldCheck
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    Le avisamos por correo, sin nada de ningún
                                    caso. Puedes quitarle el acceso cuando
                                    quieras; lo que hizo queda en la bitácora.
                                    ¿Alguien de fuera, como un abogado? Primero
                                    invítalo en{' '}
                                    <Link
                                        href={members(tenantSlug)}
                                        className="font-medium text-primary underline underline-offset-4"
                                    >
                                        Usuarios
                                    </Link>{' '}
                                    con el rol «Atención de quejas».
                                </span>
                            </p>
                            <div className="flex justify-end gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="ghost">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Designar
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
