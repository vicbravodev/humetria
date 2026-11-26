import { ProgressBar, RouteInspectionCard } from 'humetria';

export const MidRoute = () => (
    <div className="w-[360px]">
        <RouteInspectionCard ready={9} applicable={15} afterStage={12} stage={3} href="#inspeccion">
            <ProgressBar value={9} max={15} label="9 de 15 cosas que podrías mostrar" />
        </RouteInspectionCard>
    </div>
);

export const JustStarted = () => (
    <div className="w-[360px]">
        <RouteInspectionCard ready={0} applicable={13} afterStage={1} stage={1} href="#inspeccion">
            <ProgressBar value={0} max={13} label="0 de 13 cosas que podrías mostrar" />
        </RouteInspectionCard>
    </div>
);

export const Complete = () => (
    <div className="w-[360px]">
        <RouteInspectionCard ready={15} applicable={15} afterStage={15} stage={null} href="#inspeccion">
            <ProgressBar value={15} max={15} label="15 de 15 cosas que podrías mostrar" />
        </RouteInspectionCard>
    </div>
);
