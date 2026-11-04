import { Form, usePage } from '@inertiajs/react';
import { Share2 } from 'lucide-react';
import { useState } from 'react';
import ShareLink from '@/components/compensation/share-link';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/compensation/scenarios/shares';

/** The days a link may last: a week at most, which the database holds too. */
const DAYS = [1, 2, 3, 5, 7];

/** Opens a temporary link to the report for someone with no account: from 1 to 7 days. */
export default function ShareDialog({ scenarioId }: { scenarioId: string }) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);
    const link = usePage().flash.reportLink;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Share2 aria-hidden="true" />
                    Compartir
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Compartir el reporte</DialogTitle>
                <DialogDescription>
                    Un enlace temporal para que alguien sin cuenta lea el
                    reporte. Los grupos de pocas personas salen juntos como
                    «Otros», y nunca incluye el anexo por persona. Puedes
                    revocarlo antes de que venza.
                </DialogDescription>
                <Form
                    {...store.form({
                        tenant: tenantSlug,
                        scenario: scenarioId,
                    })}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="share-days">Dura</Label>
                                <Select name="days" defaultValue="3">
                                    <SelectTrigger
                                        id="share-days"
                                        aria-describedby="share-days-error"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DAYS.map((days) => (
                                            <SelectItem
                                                key={days}
                                                value={String(days)}
                                            >
                                                {days === 1
                                                    ? '1 día'
                                                    : `${days} días`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="share-days-error"
                                    message={errors['days']}
                                />
                            </div>
                            {link !== undefined && <ShareLink link={link} />}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Crear enlace
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
