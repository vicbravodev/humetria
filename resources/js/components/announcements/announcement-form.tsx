import { Form } from '@inertiajs/react';
import AnnouncementActions from '@/components/announcements/announcement-actions';
import AnnouncementAudience from '@/components/announcements/announcement-audience';
import AnnouncementMoment from '@/components/announcements/announcement-moment';
import AnnouncementSelect from '@/components/announcements/announcement-select';
import AnnouncementText from '@/components/announcements/announcement-text';
import AnnouncementPreview from '@/components/announcements/announcement-preview';

import type { AnnouncementDraft } from '@/hooks/use-announcement-editor';
import { store, update } from '@/routes/announcements';
import type { AnnouncementOption, AnnouncementRow } from '@/types';

/**
 * The form of «Nuevo aviso» or of the one being changed, in the side panel (design system: three fields or more go in a
 * FormSheet), with the preview of the banner under it: what it is about, to whom, between which two moments
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
        <Form
            {...(announcement === null
                ? store.form()
                : update.form(announcement.id))}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            noValidate
            className="flex min-h-0 flex-1 flex-col"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid flex-1 content-start gap-6 overflow-y-auto px-6 py-5">
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
                        <div className="grid gap-6 sm:grid-cols-2">
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
                        <AnnouncementPreview
                            kind={value.kind}
                            message={value.message}
                        />
                    </div>
                    <div className="flex justify-end border-t px-6 py-4">
                        <AnnouncementActions processing={processing} />
                    </div>
                </>
            )}
        </Form>
    );
}
