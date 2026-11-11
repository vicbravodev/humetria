import { Composition } from 'remotion';
import { HeroLoop } from './HeroLoop';
import { HumiChat } from './HumiChat';
import { HC } from './humi/timeline';
import { FPS, H, T, W } from './theme';
import { AnonimatoFilm, anonimato, anonimatoTimeline } from './presentations/anonimato';
import { Nom035Film, nom035, nom035Timeline } from './presentations/nom-035';

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
    </>
);
