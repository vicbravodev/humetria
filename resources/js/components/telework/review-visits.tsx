import { Form } from '@inertiajs/react';
import { House } from 'lucide-react';
import ReviewAuthorization from '@/components/telework/review-authorization';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/visit-authorizations';
import type { ReviewAuthorization as Authorization } from '@/types';

/** The visits to the place: ask the person to authorize one, attach their paper, record the visit. */
export default function ReviewVisits({
    tenantSlug,
    workplaceId,
    authorizations,
    canAsk,
}: {
    tenantSlug: string;
    workplaceId: string;
    authorizations: Authorization[];
    canAsk: boolean;
}) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card">
            <header className="flex flex-wrap items-center justify-between gap-2 border-b bg-muted px-5 py-3">
                <h2 className="text-lg font-semibold">Visitas</h2>
                {canAsk && (
                    <Form
                        {...store.form({
                            tenant: tenantSlug,
                            workplace: workplaceId,
                        })}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <House aria-hidden />
                                )}
                                Pedir autorización de visita
                            </Button>
                        )}
                    </Form>
                )}
            </header>
            {authorizations.length === 0 ? (
                <p className="px-5 py-4 text-[15px] text-muted-foreground">
                    Una visita necesita la autorización escrita de la persona.
                </p>
            ) : (
                <ul className="divide-y">
                    {authorizations.map((authorization) => (
                        <ReviewAuthorization
                            key={authorization.id}
                            tenantSlug={tenantSlug}
                            workplaceId={workplaceId}
                            authorization={authorization}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}
