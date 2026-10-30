import { Form, usePage } from '@inertiajs/react';
import { Share2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import ReportLink from '@/components/results/report-link';
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
import { store } from '@/routes/tenant/campaigns/shares';
import type { ShareDurationOption } from '@/types';

/** Opens a temporary link to the report for someone with no account: from 15 minutes to 48 hours. */
export default function ShareDialog({
    tenantSlug,
    campaignId,
    durations,
}: {
    tenantSlug: string;
    campaignId: string;
    durations: ShareDurationOption[];
}) {
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
                <DialogTitle>Compartir el informe</DialogTitle>
                <DialogDescription>
                    Un enlace temporal para que alguien sin cuenta lea el
                    informe. Nunca incluye la Guía I. Puedes revocarlo antes de
                    que venza.
                </DialogDescription>
                <Form
                    {...store.form([tenantSlug, campaignId])}
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="share-duration">Dura</Label>
                                <Select name="duration" defaultValue="24h">
                                    <SelectTrigger
                                        id="share-duration"
                                        aria-describedby="share-duration-error"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {durations.map((duration) => (
                                            <SelectItem
                                                key={duration.value}
                                                value={duration.value}
                                            >
                                                {duration.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="share-duration-error"
                                    role="alert"
                                    message={errors['duration']}
                                />
                            </div>
                            {link !== undefined && <ReportLink link={link} />}
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
