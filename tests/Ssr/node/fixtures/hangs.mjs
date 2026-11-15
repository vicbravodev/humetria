// A bundle whose render never ends: /health answers, POST /render loops forever.
import { createServer } from 'node:http';

createServer((request, response) => {
    request.resume();
    if (request.url === '/render') {
        for (;;);
    }
    response.end(JSON.stringify({ status: 'OK' }));
}).listen(Number(process.env.SSR_PORT), '127.0.0.1');
