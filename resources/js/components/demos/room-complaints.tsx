import RoomShareLink from '@/components/demos/room-share-link';
import type { DemoShareLink } from '@/types';

/** The demo's complaint channel: its address does not change between resets, so its QR can be printed. */
export default function RoomComplaints({ link }: { link: DemoShareLink }) {
    return (
        <section
            aria-labelledby="room-complaints"
            className="flex flex-col gap-3 card p-5"
        >
            <h3 id="room-complaints" className="text-base font-semibold">
                Canal de denuncias
            </h3>
            <RoomShareLink link={link} alt="Código QR del canal de denuncias">
                Cualquiera del público puede enviar una denuncia, anónima o no,
                y verla llegar al panel de RH.
            </RoomShareLink>
        </section>
    );
}
