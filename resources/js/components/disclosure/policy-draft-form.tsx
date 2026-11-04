import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import PolicySectionEditor from '@/components/disclosure/policy-section-editor';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { sectionPayload, withChange, withoutSection } from '@/lib/disclosure';
import { update } from '@/routes/tenant/disclosure/policy/draft';
import type { PolicyDraft } from '@/types';

const HELP: Record<string, string> = {
    responsibles:
        'Quiénes, con capacidad para aplicar la política, responden de ella. Es lo que dice la nota de la Guía IV.',
    resources:
        'Con qué recursos cuenta el centro para cumplirla. Es lo que dice la nota de la Guía IV.',
};

/**
 * The draft of the policy, section by section, from the example of Guía IV. Saving checks only the size: the policy is
 * published when it is complete (criterio C-51). The words are the company's own, so the page says to review them.
 */
export default function PolicyDraftForm({
    draft,
    note,
}: {
    draft: PolicyDraft;
    note: string;
}) {
    const tenantSlug = useTenantSlug();
    const form = useForm({ sections: draft.sections });
    const sections = form.data.sections;
    const error = (key: string) =>
        key === 'commitments' ? form.errors['sections'] : undefined;

    return (
        <form
            noValidate
            className="grid gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    sections: data.sections.map(sectionPayload),
                }));
                form.put(
                    update.url({ tenant: tenantSlug, document: draft.id }),
                    {
                        preserveScroll: true,
                    },
                );
            }}
        >
            <p className="max-w-[680px] text-meta text-muted-foreground">
                {note}
            </p>
            {sections.map((section) => (
                <PolicySectionEditor
                    key={section.key}
                    section={section}
                    help={HELP[section.key] ?? null}
                    error={error(section.key)}
                    onChange={(change) =>
                        form.setData(
                            'sections',
                            withChange(sections, section.key, change),
                        )
                    }
                    onRemove={
                        section.removable
                            ? () =>
                                  form.setData(
                                      'sections',
                                      withoutSection(sections, section.key),
                                  )
                            : null
                    }
                />
            ))}
            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner /> : <Save aria-hidden />}
                    Guardar borrador
                </Button>
            </div>
        </form>
    );
}
