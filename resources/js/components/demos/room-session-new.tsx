import { Form } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { useState } from 'react';
import RoomTourList from '@/components/demos/room-tour-list';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { moveTour, sessionMinutes, toggleTour } from '@/lib/demos/room';
import { start } from '@/routes/demo-room/session';
import type { DemoTour } from '@/types';

/**
 * «Nueva sesión»: the client, the tours to show (chosen and put in order) with how long they take, and «Empezar sesión»,
 * which resets the demo first. «El centro y su gente» always goes first. Held while a run is going.
 */
export default function RoomSessionNew({
    tours,
    running,
}: {
    tours: DemoTour[];
    running: boolean;
}) {
    const [order, setOrder] = useState(() => tours.map((tour) => tour.key));
    const [chosen, setChosen] = useState<string[]>([]);
    const sorted = order.flatMap(
        (key) => tours.find((tour) => tour.key === key) ?? [],
    );
    const picked = order.filter((key) => chosen.includes(key));

    return (
        <section
            aria-labelledby="room-session-new"
            className="flex max-w-3xl flex-col gap-5 card p-5 md:p-6"
        >
            <div className="flex flex-col gap-1">
                <h2 id="room-session-new" className="text-lg font-semibold">
                    Nueva sesión
                </h2>
                <p className="text-sm text-pretty text-muted-foreground">
                    Escoge lo que este cliente quiere ver. El guía te lleva solo
                    por eso.
                </p>
            </div>
            <Form
                {...start.form()}
                transform={(data) => ({ ...data, tours: picked })}
                options={{ preserveScroll: true }}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex max-w-sm flex-col gap-2">
                            <Label htmlFor="client_name">
                                Cliente (opcional)
                            </Label>
                            <Input
                                id="client_name"
                                name="client_name"
                                maxLength={80}
                                autoComplete="off"
                                placeholder="Grupo Ejemplo"
                            />
                            <p className="text-meta text-muted-foreground">
                                Nombra el centro de la sesión: «Sala · Grupo
                                Ejemplo».
                            </p>
                            <InputError message={errors['client_name']} />
                        </div>
                        <RoomTourList
                            tours={sorted}
                            chosen={chosen}
                            onChange={(key, change) =>
                                'on' in change
                                    ? setChosen(
                                          toggleTour(chosen, key, change.on),
                                      )
                                    : setOrder(moveTour(order, key, change.by))
                            }
                        />
                        <InputError message={errors['tours']} />
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-pretty text-muted-foreground">
                                <span className="font-semibold text-foreground tabular-nums">
                                    {`${sessionMinutes(tours, picked)} min`}
                                </span>
                                {` aproximados · ${picked.length + 1} recorridos. Empezar reinicia la demo de cero (unos 30 s).`}
                            </p>
                            <Button
                                type="submit"
                                disabled={
                                    processing || running || picked.length === 0
                                }
                                className="max-sm:w-full"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Play aria-hidden />
                                )}
                                Empezar sesión
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
