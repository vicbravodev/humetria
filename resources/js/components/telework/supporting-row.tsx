import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { supportingRow } from '@/lib/telework/writing';
import { cn } from '@/lib/utils';
import { file, index as documents } from '@/routes/tenant/telework/documents';
import { show } from '@/routes/tenant/telework/writing';
import type { SupportingDocument } from '@/types';

/**
 * One supporting document (board V2-N037-Docs): missing, with «Crear con preguntas» and «Ya lo tengo»; or ready, with its
 * version, «Ver» and «Nueva versión».
 */
export default function SupportingRow({
    document,
    primary,
    canManage,
}: {
    document: SupportingDocument;
    /** The first one missing: its button leads. */
    primary: boolean;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const row = supportingRow(document, primary, canManage);

    return (
        <li className="grid grid-cols-[40px_minmax(0,1fr)] items-center gap-x-[18px] gap-y-3 border-t px-5 py-5 first:border-t-0 md:px-6 @[760px]:grid-cols-[40px_minmax(0,1fr)_auto]">
            <span
                className={cn(
                    'inline-flex size-10 items-center justify-center rounded-full',
                    row.view === null
                        ? 'border-[1.5px] border-dashed border-risk-alto'
                        : 'bg-primary text-primary-foreground',
                )}
            >
                {row.view !== null && (
                    <Check
                        aria-hidden
                        className="size-[18px]"
                        strokeWidth={3}
                    />
                )}
                <span className="sr-only">
                    {row.view === null ? 'Falta' : 'Listo'}
                </span>
            </span>
            <div className="flex min-w-0 flex-col gap-1">
                <p
                    className={cn(
                        'text-[17px] font-semibold',
                        row.view !== null && 'text-muted-foreground',
                    )}
                >
                    {document.name}
                </p>
                <p className="text-muted-foreground">{row.detail}</p>
                {row.meta !== null && (
                    <p className="text-sm text-muted-foreground">{row.meta}</p>
                )}
            </div>
            <div className="col-start-2 flex flex-wrap gap-2 @[760px]:col-start-auto @[760px]:justify-end">
                {row.view !== null && (
                    <Button variant="ghost" className="text-primary" asChild>
                        <a
                            href={file.url([tenantSlug, row.view])}
                            target="_blank"
                            rel="noreferrer"
                        >
                            Ver
                            <span className="sr-only"> · {document.name}</span>
                        </a>
                    </Button>
                )}
                {row.write !== null && (
                    <Button
                        variant={row.write.variant}
                        className={cn(
                            row.write.variant === 'ghost' && 'text-primary',
                        )}
                        asChild
                    >
                        <Link href={show([tenantSlug, document.kind])}>
                            {row.write.label}
                            <span className="sr-only"> · {document.name}</span>
                        </Link>
                    </Button>
                )}
                {row.upload && (
                    <Button variant="ghost" className="text-primary" asChild>
                        <Link
                            href={documents(tenantSlug, {
                                query: { kind: document.kind },
                            })}
                        >
                            Ya lo tengo
                            <span className="sr-only"> · {document.name}</span>
                        </Link>
                    </Button>
                )}
            </div>
        </li>
    );
}
