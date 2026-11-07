import { Composition } from 'remotion';
import { HeroLoop } from './HeroLoop';
import { FPS, H, T, W } from './theme';

export const Root = () => (
    <Composition id="HeroLoop" component={HeroLoop} durationInFrames={T.end} fps={FPS} width={W} height={H} />
);
