import { loadFont } from '@remotion/fonts';
import { staticFile } from 'remotion';

for (const weight of ['400', '500', '600']) {
    void loadFont({
        family: 'Figtree',
        url: staticFile(`fonts/figtree-latin-${weight}-normal.woff2`),
        weight,
    });
}
