import { NewVersionBanner } from 'humetria';

// The banner is `fixed`; the transformed frame stands in for the phone screen so it stays inside its cell.
export const OverTheApp = () => (
    <div
        className="relative overflow-hidden rounded-xl border bg-muted"
        style={{ transform: 'translateZ(0)', width: 414, height: 120 }}
    >
        <NewVersionBanner onUpdate={() => {}} />
    </div>
);
