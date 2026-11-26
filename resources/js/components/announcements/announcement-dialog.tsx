import { useState } from 'react';
import AnnouncementAudience from '@/components/announcements/announcement-audience';
import AnnouncementMoment from '@/components/announcements/announcement-moment';
import AnnouncementText from '@/components/announcements/announcement-text';
import ChoiceField from '@/components/humetria/choice-field';
import FormDialog from '@/components/humetria/form-dialog';
import { store, update } from '@/routes/announcements';
import type { AnnouncementOption, AnnouncementRow } from '@/types';

const PUBLISH = [
    { value: '1', label: 'Publicar en su fecha' },
    { value: '0', label: 'Guardar como borrador' },
];

/**
 * Writes a new announcement or changes one: what it is about, to whom, its message and between which two moments (Mexico
 * City's) the companies see it. A draft never reaches a panel.
 */
export default function AnnouncementDialog({
    announcement,
    kinds,
    plans,
    open,
    onOpenChange,
}: {
    announcement: AnnouncementRow | null;
    kinds: AnnouncementOption[];
    plans: AnnouncementOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [kind, setKind] = useState<string>(announcement?.kind ?? 'news');
    const [publish, setPublish] = useState(
        announcement?.published === false ? '0' : '1',
    );

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={announcement === null ? 'Nuevo aviso' : 'Editar aviso'}
            description="Las empresas lo ven como un banner en su panel, entre el inicio y el fin. Las horas son las de la Ciudad de México."
            form={
                announcement === null
                    ? store.form()
                    : update.form(announcement.id)
            }
            submit="Guardar aviso"
            wide
        >
            {(errors) => (
                <>
                    <ChoiceField
                        name="kind"
                        legend="Tipo"
                        options={kinds}
                        value={kind}
                        onChange={setKind}
                        columns={2}
                        error={errors['kind']}
                    />
                    <AnnouncementAudience
                        announcement={announcement}
                        plans={plans}
                        errors={errors}
                    />
                    <AnnouncementText
                        announcement={announcement}
                        errors={errors}
                    />
                    <AnnouncementMoment
                        which="starts"
                        legend="Inicio"
                        announcement={announcement}
                        errors={errors}
                    />
                    <AnnouncementMoment
                        which="ends"
                        legend="Fin"
                        announcement={announcement}
                        errors={errors}
                    />
                    <ChoiceField
                        name="publish"
                        legend="Publicación"
                        options={PUBLISH}
                        value={publish}
                        onChange={setPublish}
                        columns={2}
                        error={errors['publish']}
                    />
                </>
            )}
        </FormDialog>
    );
}
