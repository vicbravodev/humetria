import { usePage } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import SupportLink from '@/components/humetria/support-link';

/**
 * A company that is read only (its trial ended, its term lapsed or a person decided it) still opens its pages, but nothing
 * can be changed: the frame says why, on every page, so a refused save is never a mystery.
 */
export function CompanyReadOnlyNotice() {
    const { tenant } = usePage().props;

    if (!tenant?.read_only) {
        return null;
    }

    return (
        <div
            data-slot="company-read-only-notice"
            className="px-4 pt-4 md:px-10 md:pt-6 print:hidden"
        >
            <Notice>
                <strong>Tu empresa está en solo lectura.</strong> Puedes
                consultar y exportar tu información, pero no hacer cambios.{' '}
                <SupportLink subject={`Reactivar ${tenant.trade_name}`}>
                    Contacta al administrador de Humetría
                </SupportLink>{' '}
                para reactivarla.
            </Notice>
        </div>
    );
}
