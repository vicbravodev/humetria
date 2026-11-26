import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ChannelsNotices from '@/components/tenancy/channels-notices';
import CompanyChannelsForm from '@/components/tenancy/company-channels-form';
import type { CompanyChannelsPage } from '@/types';

/** «Canales de comunicación» (the owner's): through which channels the company's people are written to. */
export default function Channels(props: CompanyChannelsPage) {
    return (
        <>
            <Head title="Canales de comunicación" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Canales de comunicación"
                    description="Por dónde les escribe Humetría a las personas de tu empresa: invitaciones a las encuestas y, después, otros avisos."
                />
                <ChannelsNotices isDemo={props.isDemo} />
                <CompanyChannelsForm page={props} />
            </div>
        </>
    );
}
