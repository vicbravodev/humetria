// A stand-in for the bundle: answers /health and renders what the landing must have (h1 and the nine tools).
import { readFileSync } from 'node:fs';
import { createServer } from 'node:http';

const expected = JSON.parse(
    readFileSync(
        new URL('../../fixtures/landing-expected.json', import.meta.url),
    ),
);
const body = `<div id="app"><h1>${expected.h1}</h1>${expected.tools
    .map(
        (tool) =>
            `<div role="tabpanel"><h3>${tool.name}</h3><p>${tool.hint}</p></div>`,
    )
    .join('')}</div>`;

createServer((request, response) => {
    request.resume();
    response.setHeader('Content-Type', 'application/json');
    response.end(
        JSON.stringify(
            request.url === '/health' ? { status: 'OK' } : { head: [], body },
        ),
    );
}).listen(Number(process.env.SSR_PORT), '127.0.0.1');
