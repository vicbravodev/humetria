import { Composition } from 'remotion';
import { HeroLoop } from './HeroLoop';
import { HumiChat } from './HumiChat';
import { HC } from './humi/timeline';
import { FPS, H, T, W } from './theme';
import { AnonimatoFilm, anonimato, anonimatoTimeline } from './presentations/anonimato';

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
        <Composition
            id={anonimato.id}
            component={AnonimatoFilm}
            durationInFrames={anonimatoTimeline.total}
            fps={anonimato.fps}
            width={anonimato.width}
            height={anonimato.height}
        />
    </>
);
