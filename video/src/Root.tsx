import { Composition } from 'remotion';
import { HeroLoop } from './HeroLoop';
import { HumiChat } from './HumiChat';
import { HC } from './humi/timeline';
import { FPS, H, T, W } from './theme';
import { AnonimatoFilm, anonimato, anonimatoTimeline } from './presentations/anonimato';
import { Nom035Film, nom035, nom035Timeline } from './presentations/nom-035';
import { Nom037Film, nom037, nom037Timeline } from './presentations/nom-037';
import { LeySillaFilm, leySilla, leySillaTimeline } from './presentations/ley-silla';
import { ComplaintsFilm, complaints, complaintsTimeline } from './presentations/complaints';
import { CompensationFilm, compensation, compensationTimeline } from './presentations/compensation';

export const Root = () => (
    <>
        <Composition id="HeroLoop" component={HeroLoop} durationInFrames={T.end} fps={FPS} width={W} height={H} />
        <Composition
            id="HumiChat"
            component={HumiChat}
            durationInFrames={HC.end}
            fps={FPS}
            width={HC.size}
            height={HC.size}
        />
        <Composition id={anonimato.id} component={AnonimatoFilm} durationInFrames={anonimatoTimeline.total} fps={anonimato.fps} width={anonimato.width} height={anonimato.height} defaultProps={{ theme: 'dark' as const }} />
        <Composition id={nom035.id} component={Nom035Film} durationInFrames={nom035Timeline.total} fps={nom035.fps} width={nom035.width} height={nom035.height} defaultProps={{ theme: 'dark' as const }} />
        <Composition id={nom037.id} component={Nom037Film} durationInFrames={nom037Timeline.total} fps={nom037.fps} width={nom037.width} height={nom037.height} defaultProps={{ theme: 'dark' as const }} />
        <Composition id={leySilla.id} component={LeySillaFilm} durationInFrames={leySillaTimeline.total} fps={leySilla.fps} width={leySilla.width} height={leySilla.height} defaultProps={{ theme: 'dark' as const }} />
        <Composition id={complaints.id} component={ComplaintsFilm} durationInFrames={complaintsTimeline.total} fps={complaints.fps} width={complaints.width} height={complaints.height} defaultProps={{ theme: 'dark' as const }} />
        <Composition id={compensation.id} component={CompensationFilm} durationInFrames={compensationTimeline.total} fps={compensation.fps} width={compensation.width} height={compensation.height} defaultProps={{ theme: 'dark' as const }} />
    </>
);
