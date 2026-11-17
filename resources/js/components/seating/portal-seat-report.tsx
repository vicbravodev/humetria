import { Form } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import PortalFrame from '@/components/seating/portal-frame';
import PortalHeading from '@/components/seating/portal-heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { show } from '@/routes/portal/seating';
import { store } from '@/routes/portal/seating/reports';

/**
 * Reports a damaged seat (findings 13 and 15, board SillaAviso), anonymous: the screen does not name the person, and the
 * server keeps neither who reports nor their position, only the center and what they write.
 */
export default function PortalSeatReport({
    slug,
    company,
}: {
    slug: string;
    company: string;
}) {
    return (
        <Form {...store.form(slug)}>
            {({ processing, errors }) => (
                <PortalFrame
                    company={company}
                    privacy="anonymous"
                    back={show.url(slug)}
                    actions={
                        <Button
                            className="h-14 w-full text-lg font-semibold"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Avisar del asiento dañado
                        </Button>
                    }
                >
                    <PortalHeading
                        eyebrow="Ley Silla"
                        title="Avisar de un asiento dañado"
                    >
                        Cuéntenos cuál y qué le pasa. No guardamos quién avisa:
                        solo llega a la empresa y a la Comisión.
                    </PortalHeading>
                    <div className="grid gap-2">
                        <Label
                            htmlFor="report-description"
                            className="text-caption font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Qué asiento y qué le pasa
                        </Label>
                        <Textarea
                            id="report-description"
                            name="description"
                            rows={6}
                            placeholder="Escriba aquí, con sus palabras"
                            className="text-[17px] leading-[26px]"
                            aria-invalid={
                                errors['description'] === undefined
                                    ? undefined
                                    : true
                            }
                        />
                        <InputError message={errors['description']} />
                    </div>
                </PortalFrame>
            )}
        </Form>
    );
}
