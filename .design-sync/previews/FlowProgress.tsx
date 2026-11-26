import { FlowProgress } from 'humetria';

export const MidFlow = () => (
    <div className="max-w-xl">
        <FlowProgress label="Pregunta 3 de 8" left="Faltan unos 9 min" value={2} max={8} />
    </div>
);

export const FirstQuestion = () => (
    <div className="max-w-xl">
        <FlowProgress label="Pregunta 1 de 8" left="Faltan unos 12 min" value={0} max={8} />
    </div>
);

export const Review = () => (
    <div className="max-w-xl">
        <FlowProgress label="Revisa tu política" left={null} value={8} max={8} />
    </div>
);
