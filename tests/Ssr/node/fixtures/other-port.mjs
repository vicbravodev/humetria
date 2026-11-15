// Preloaded (`node --import`) to run a built bundle next to the real one: the bundle listens on 13714, this makes it
// listen on SSR_PORT instead.
import http from 'node:http';

const original = Reflect.get(http.Server.prototype, 'listen');

http.Server.prototype.listen = function (_port, ...rest) {
    return Reflect.apply(original, this, [
        Number(process.env.SSR_PORT),
        ...rest,
    ]);
};
