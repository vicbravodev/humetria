import ReferralChoices from '@/components/results/referral-choices';
import type { DestinationOption, ReferralTold } from '@/types';

/**
 * «2. ¿Cómo se lo dices?» (board V4-N035-Canalizacion): in person with a short script, or a notice in their portal when an
 * email can announce it; and what to say, or what they will read, for the destination chosen.
 */
export default function ReferralToldField({
    told,
    onChange,
    reachable,
    destination,
}: {
    told: ReferralTold;
    onChange: (told: ReferralTold) => void;
    reachable: boolean;
    destination: DestinationOption | undefined;
}) {
    const said =
        told === 'in_person'
            ? destination && `Guion: «${destination.script}»`
            : destination?.notice;

    return (
        <div className="grid gap-2.5">
            <ReferralChoices
                name="told"
                legend="2. ¿Cómo se lo dices?"
                options={[
                    {
                        value: 'in_person',
                        label: 'En persona, en privado',
                        hint: 'Recomendado. Te damos un guion corto para la plática.',
                    },
                    {
                        value: 'portal',
                        label: 'Aviso en su portal',
                        hint: reachable
                            ? 'Lo lee con su enlace. Útil si trabaja en otro turno.'
                            : 'No tiene correo para avisarle: díselo en persona.',
                        disabled: !reachable,
                    },
                ]}
                value={told}
                onChange={onChange}
                columns={1}
            />
            <div className="rounded-[10px] bg-muted px-4 py-3.5 text-sm">
                <p className="text-muted-foreground">
                    {told === 'in_person'
                        ? 'Lo que puedes decirle'
                        : 'Lo que verá en su portal'}
                </p>
                <p className="mt-1">
                    {said ?? 'Elige a dónde la canalizas para ver qué decirle.'}
                </p>
            </div>
        </div>
    );
}
