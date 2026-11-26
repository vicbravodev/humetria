import { Form } from '@inertiajs/react';
import AnnouncementActions from '@/components/announcements/announcement-actions';
import AnnouncementAudience from '@/components/announcements/announcement-audience';
import AnnouncementMoment from '@/components/announcements/announcement-moment';
import AnnouncementSelect from '@/components/announcements/announcement-select';
import AnnouncementText from '@/components/announcements/announcement-text';
import { Button } from '@/components/ui/button';

import { store, update } from '@/routes/announcements';
import type { AnnouncementOption, AnnouncementRow } from '@/types';

export type AnnouncementDraft = { kind: string; message: string };

/**
 * «Nuevo aviso» in the page (tablero Avisos), or the one being changed: what it is about, to whom, between which two moments
 * (Mexico City's) the companies see it, its title and its message. «Programar aviso» publishes it on its date; a draft never
 * reaches a panel.
 */
export default function AnnouncementForm({
    announcement,
    catalogs,
    draft,
}: {
    announcement: AnnouncementRow | null;
    catalogs: { kinds: AnnouncementOption[]; plans: AnnouncementOption[] };
    draft: {
        value: AnnouncementDraft;
        onChange: (draft: AnnouncementDraft) => void;
        onDone: () => void;
    };
}) {
    const { value, onChange, onDone } = draft;

    return (
        <section
            id="nuevo-aviso"
            aria-labelledby="announcement-form-title"
            className="scroll-mt-6 card"
        >
            <header className="flex flex-wrap items-center gap-3 border-b px-6 py-4">
                <h2
                    id="announcement-form-title"
                    className="flex-1 text-base font-semibold whitespace-nowrap"
                >
                    {announcement === null ? 'Nuevo aviso' : 'Editar aviso'}
                </h2>
                {announcement === null ? (
                    <span className="text-meta text-muted-foreground">
                        Horas de la Ciudad de México
                    </span>
                ) : (
                    <Button variant="ghost" size="sm" onClick={onDone}>
                        Cancelar
                    </Button>
                )}
            </header>
            <Form
                {...(announcement === null
                    ? store.form()
                    : update.form(announcement.id))}
                options={{ preserveScroll: true }}
                onSuccess={onDone}
                noValidate
                className="grid gap-5 px-6 py-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5 md:grid-cols-2">
                            <AnnouncementSelect
                                id="announcement-kind"
                                label="Tipo"
                                field={{
                                    name: 'kind',
                                    options: catalogs.kinds,
                                    value: value.kind,
                                    onChange: (kind) =>
                                        onChange({ ...value, kind }),
                                }}
                                error={errors['kind']}
                            />
                            <AnnouncementAudience
                                announcement={announcement}
                                plans={catalogs.plans}
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
                        </div>
                        <AnnouncementText
                            announcement={announcement}
                            message={{
                                value: value.message,
                                onChange: (message) =>
                                    onChange({ ...value, message }),
                            }}
                            errors={errors}
                        />
                        <AnnouncementActions processing={processing} />
                    </>
                )}
            </Form>
        </section>
    );
}
