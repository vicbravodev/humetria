import { Mail, MessageCircle } from 'lucide-react';
import type { Ref } from 'react';
import Initials from '@/components/humetria/initials';
import { Button } from '@/components/ui/button';
import { toolsText } from '@/lib/demo-requests';
import { whatsappUrl } from '@/lib/whatsapp-url';
import type { DemoRequestRow } from '@/types';

/** One request in full: who asked, how to reach them, what they want and what they wrote. */
export default function DemoRequestDetail({
    ref,
    request,
}: {
    /** Takes the focus when a request is chosen on a phone. */
    ref?: Ref<HTMLElement>;
    request: DemoRequestRow;
}) {
    const facts = [
        { term: 'Empresa', value: request.company },
        { term: 'Tamaño', value: `${request.workforce} trabajadores` },
        { term: 'Le interesa', value: toolsText(request.tools) },
    ];

    return (
        <article
            ref={ref}
            tabIndex={-1}
            data-test="demo-request-detail"
            className="flex min-w-0 flex-[999_1_400px] flex-col gap-[22px] px-5 py-6 focus:outline-none md:px-7"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Initials name={request.name} className="size-11 text-sm" />
                    <div className="flex flex-col">
                        <h2 className="text-lg leading-[26px] font-semibold">
                            {request.name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {request.profile} · llegó el {request.received_at}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm">
                        <a
                            href={whatsappUrl(request.whatsapp)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <MessageCircle aria-hidden />
                            WhatsApp
                        </a>
                    </Button>
                    <Button asChild size="sm">
                        <a href={`mailto:${request.email}`}>
                            <Mail aria-hidden />
                            Escribir correo
                        </a>
                    </Button>
                </div>
            </div>
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-x-6 gap-y-4 border-y py-[18px]">
                <div className="flex flex-col gap-0.5">
                    <dt className="text-[13px] leading-[18px] text-muted-foreground">
                        Correo
                    </dt>
                    <dd className="text-sm font-medium break-all">
                        {request.email}
                    </dd>
                </div>
                <div className="flex flex-col gap-0.5">
                    <dt className="text-[13px] leading-[18px] text-muted-foreground">
                        WhatsApp
                    </dt>
                    <dd className="text-sm font-medium tabular-nums">
                        {request.whatsapp}
                    </dd>
                </div>
                {facts.map((fact) => (
                    <div key={fact.term} className="flex flex-col gap-0.5">
                        <dt className="text-[13px] leading-[18px] text-muted-foreground">
                            {fact.term}
                        </dt>
                        <dd className="text-sm font-medium">{fact.value}</dd>
                    </div>
                ))}
            </dl>
            <div className="flex flex-col gap-2">
                <h3 className="text-[13px] leading-[18px] text-muted-foreground">
                    Mensaje
                </h3>
                <p className="max-w-[640px] whitespace-pre-line">
                    {request.message ?? (
                        <span className="text-muted-foreground">
                            Sin mensaje
                        </span>
                    )}
                </p>
            </div>
        </article>
    );
}
