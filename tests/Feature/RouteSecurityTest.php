<?php

declare(strict_types=1);

use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The public surface of the app: routes that may be reached without authentication.
 * Adding a name here needs a one-line reason.
 *
 * @return list<string>
 */
function publicRouteNames(): array
{
    return [
        'home', // landing page
        'privacy-notice', // guest: el aviso de privacidad del sitio; texto fijo, sin datos de nadie
        'films.show', // guest: the films of the landing page, served with byte ranges so a chapter can jump inside one; only files named humetria-*.mp4|webm that exist in public/video, anything else is a 404
        'demo-requests.store', // landing demo form; throttled + honeypot
        'login', // guest: sign-in form
        'login.store', // guest: sign-in submit
        'password.request', // guest: forgot-password form
        'password.email', // guest: sends the reset link
        'password.reset', // guest: reset form reached from the emailed link
        'password.update', // guest: reset submit
        'two-factor.login', // mid-login: 2FA challenge, user is not authenticated yet
        'two-factor.login.store', // mid-login: 2FA challenge submit
        'passkey.login', // guest: passkey sign-in submit
        'passkey.login-options', // guest: passkey sign-in challenge
        'well-known.passkeys', // spec-mandated discovery document for password managers
        'invitations.show', // guest: invitation link from the email; the token is the credential
        'invitations.accept', // guest: accepts the invitation; throttled, single-use token
        'survey.show', // guest: survey link from the email; the token is the credential; throttled
        'survey.submit', // guest: submits one guide; throttled, under a lock, once per guide
        'engagement.survey.show', // guest: la persona abre la encuesta de clima o pulso desde su correo; el token del enlace es la credencial; no muestra nada de nadie; un enlace inválido, usado o cancelado no dice por qué; throttled
        'engagement.survey.submit', // guest: envía la encuesta completa una sola vez, bajo candado; el token es la credencial; throttled
        'portal.show', // guest: el inicio del portal por enlace, mientras las demás herramientas abren sus páginas con él (hasta el paso 9 del diseño de WorkerAccess); el token es la credencial; su sección de teletrabajo lleva a las páginas de la persona, sin credencial; throttled
        'portal.feedback.show', // guest: la persona con equipo a cargo ve el resultado agregado de su grupo; el token del portal es la credencial; nunca respuestas ni textos de nadie; throttled
        'portal.climate.show', // guest: la persona lee lo que la empresa publicó tras la encuesta de clima: participación, índice y compromisos de la empresa y de sus propios grupos; el token del portal es la credencial; nada individual; 404 si no hay nada publicado; throttled
        'portal.support.show', // guest: la persona canalizada por aviso en su portal lee el mensaje de su empresa, en usted, con el destino propuesto; nada de la Guía I ni la nota de RH; el token del portal es la credencial; solo lo suyo; un enlace no válido o sin mensaje da 404; throttled
        'portal.attendance.show', // guest: la persona ve sus teléfonos vinculados y el camino a marcar y a Mi semana; el token del portal es la credencial; solo lo suyo y sin llaves; un enlace no válido o una empresa sin registro de jornada da 404 sin decir nada más; throttled
        'workers.show', // guest: las personas ven los centros de su empresa y lo que la empresa decidió que todos lean; el enlace permanente (derivado, rotable, desactivable, con vencimiento) es la credencial; sin sesión, sin cookies y sin rastro de quien la abre; el mismo 404 neutro por cualquier causa; throttled por dirección y por enlace
        'workers.center', // guest: las personas ven lo que un centro publica (la política vigente); el enlace permanente es la credencial; un centro ajeno, archivado o inexistente da el mismo 404 neutro que un enlace inventado; sin sesión ni cookies; throttled por dirección y por enlace
        'complaints.public.show', // guest: la persona presenta su queja sin cuenta, sin sesión y sin cookies; el código del canal es la credencial de la empresa; un código inválido o un canal sin activar dan el mismo 404; throttled por una huella de la dirección
        'complaints.public.centers', // guest: busca los centros de trabajo de la empresa del código para el formulario; solo con el canal abierto; el código es la credencial de la empresa; throttled
        'complaints.public.store', // guest: la persona envía su queja sin cuenta, sin sesión y sin cookies; boleto firmado, trampa para bots y tope por dirección y por día; el código es la credencial de la empresa
        'complaints.follow-up.show', // guest: la persona pide su referencia y su clave; sin cuenta, sin sesión y sin cookies; el código del canal es la credencial de la empresa; throttled
        'complaints.follow-up.open', // guest: la persona abre su caso con la referencia y la clave en el cuerpo; igual respuesta si falla una u otra; throttled por una huella de la dirección
        'complaints.follow-up.messages.store', // guest: la persona escribe en su caso con la referencia y la clave; solo el día, nunca la hora; throttled
        'complaints.follow-up.withdraw', // guest: la persona retira su caso con la referencia y la clave; throttled
        'attendance.device.kiosk.activate', // guest: la pantalla del QR de un centro (tableta fija, sin cuenta) abre su enlace una sola vez: el token del enlace se cambia por un secreto nuevo que solo guarda una cookie httpOnly y el enlace muere; un enlace ya usado, revocado, ajeno o desconocido da el mismo 404; throttled
        'attendance.device.kiosk.show', // guest: la página de la pantalla del QR; el secreto de su cookie httpOnly es la credencial; el mismo 404 por cualquier causa; throttled
        'attendance.device.kiosk.qr', // guest: la pantalla del QR pide el código vigente de su centro (rota cada 30 s); el secreto de su cookie httpOnly es la credencial; el mismo 404 por cualquier causa; throttled
        'attendance.device.punch.home', // guest: la pantalla Marcar de la persona en su dispositivo vinculado (sin cuenta), desde la dirección del correo «dispositivo vinculado»; no muestra nada de nadie hasta que una llave verificada marca en esta sesión, y entonces solo el día de esa persona (tipos y horas, nunca ubicación); throttled por IP
        'attendance.device.punch.show', // guest: la misma pantalla Marcar abierta desde el QR de la pantalla del centro, con su código (que solo vale 60 s); throttled por IP
        'attendance.device.punch.options', // guest: da las opciones WebAuthn para firmar una marca, sin persona ni llaves listadas; el desafío queda ligado a la sesión del navegador, es de un solo uso y vence en 60 s; throttled por IP
        'attendance.device.punch.store', // guest: registra la marca de la persona cuya llave firmó (firma, origen, verificación de la persona, desafío de esta sesión); la persona sale solo de la firma, la empresa solo del slug y la hora del servidor; una firma inválida, de un dispositivo revocado, de alguien dado de baja o de otra empresa da el mismo 404; throttled por IP y por llave
        'attendance.device.punch.forget', // guest: «¿No es usted?» en la pantalla Marcar: el navegador olvida al instante a quien marcó en él; no recibe datos, no lee ni devuelve nada de nadie; throttled por IP
        'attendance.device.agreement.options', // guest: da las opciones WebAuthn para firmar la aceptación del acuerdo de registro electrónico, sin persona ni llaves listadas; el desafío queda ligado a la sesión del navegador y solo sirve para el acuerdo (no para marcar, la semana ni corregir), es de un solo uso y vence en 60 s; throttled por IP
        'attendance.device.agreement.accept', // guest: la persona acepta la versión vigente del acuerdo antes de su primera marca (criterio 5.1) con una firma propia de esta acción (un solo uso); la persona sale solo de la firma y la aceptación guarda esa llave; una firma inválida, una llave revocada, una persona dada de baja u otra empresa dan el mismo 404; una versión vieja, 409 con el texto vigente; throttled por IP y por llave
        'attendance.device.week.show', // guest: la página «Mi semana» de la persona en su dispositivo vinculado (sin cuenta); no trae nada de nadie: la semana solo llega tras una firma verificada; throttled por IP
        'attendance.device.week.options', // guest: da las opciones WebAuthn para firmar la lectura o la respuesta de una semana, sin persona ni llaves listadas; el desafío queda ligado a la sesión del navegador y solo sirve para la semana (no para marcar), es de un solo uso y vence en 60 s; throttled por IP
        'attendance.device.week.read', // guest: devuelve la semana de la persona cuya llave firmó (sus marcas con hora local, horas y avisos; nunca ubicación ni dispositivo); cada lectura pide su propia firma; firma inválida, dispositivo revocado, persona dada de baja u otra empresa dan el mismo 404; throttled por IP y por llave
        'attendance.device.week.confirm', // guest: la persona confirma su semana ya terminada con una firma propia de esta acción (un solo uso); la persona sale solo de la firma y queda en la bitácora con el dispositivo; el mismo 404 por cualquier firma inválida; throttled por IP y por llave
        'attendance.device.week.dispute', // guest: la persona objeta su semana ya terminada con motivo y una firma propia de esta acción (un solo uso); la persona sale solo de la firma y queda en la bitácora con el dispositivo; el mismo 404 por cualquier firma inválida; throttled por IP y por llave
        'attendance.device.correction.options', // guest: da las opciones WebAuthn para firmar una solicitud de corrección, sin persona ni llaves listadas; el desafío queda ligado a la sesión del navegador y solo sirve para corregir (no para marcar ni para la semana), es de un solo uso y vence en 60 s; throttled por IP
        'attendance.device.correction.store', // guest: la persona pide a RH corregir una marca suya o una que olvidó, con motivo y una firma propia de esta acción (un solo uso); la persona sale solo de la firma; una firma inválida, un dispositivo revocado, una persona dada de baja, otra empresa o una marca que no es suya dan el mismo 404; nunca ubicación; throttled por IP y por llave
        'worker.home', // guest: la entrada de la persona trabajadora a su empresa (sin cuenta ni credencial en la dirección); sin sesión muestra la pantalla de entrada sin el nombre de nadie; con sesión (validada en cada petición), su inicio; una empresa desconocida o bloqueada da el mismo 404; throttled por IP
        'worker.enter.options', // guest: da las opciones WebAuthn para entrar con la llave, sin persona ni llaves listadas; el desafío queda ligado a la sesión del navegador y solo sirve para entrar, es de un solo uso y vence en 60 s; throttled por IP
        'worker.enter', // guest: abre la sesión de la persona cuya llave firmó; una firma inválida, una llave revocada, de otra empresa o de alguien dado de baja dan la misma respuesta; regenera la sesión; throttled por IP y por llave
        'worker.leave', // guest: «Salir» borra solo la entrada de la persona en la sesión y la regenera; no recibe datos; throttled por IP
        'worker.link.request', // guest: la persona escribe su correo y pide un enlace de un solo uso (30 min); la respuesta es siempre la misma exista o no la persona; solo se envía si exactamente una persona activa de la empresa tiene ese correo; anula los enlaces pedidos antes; throttled por IP y por correo (hash)
        'worker.link.show', // guest: muestra «Continuar» de un enlace de un solo uso sin gastarlo (los filtros de correo abren los enlaces); un enlace usado, anulado, vencido, desconocido, ajeno o de alguien dado de baja muestra la misma pantalla; throttled por IP y por enlace
        'worker.link.open', // guest: gasta el enlace de un solo uso con una actualización condicional (de dos a la vez solo uno gana) y abre la sesión de su persona; regenera la sesión; el mismo resultado para todo enlace que no sirve; throttled por IP y por enlace
        'worker.key.show', // guest: la pantalla para crear una llave en el dispositivo; exige la sesión de la persona (sin ella, a la entrada) y dice si esa sesión no puede (entró con un enlace pedido por la persona o ya tiene 3 llaves); solo el nombre de la propia persona; throttled por IP
        'worker.key.options', // guest: da las opciones WebAuthn para crear la llave de la persona de la sesión; 403 sin sesión o con una que entró con un enlace pedido por la persona, 409 con 3 llaves activas; el desafío es de un solo uso y vence en 60 s; throttled por IP
        'worker.key.store', // guest: guarda la llave verificada de la persona de la sesión (mismas reglas que las opciones, el límite contado bajo candado); bitácora, log y aviso con «No fui yo»; throttled por IP
        'worker.not-me.show', // guest: «No fui yo» desde el correo de una llave nueva; la dirección firmada por la app (30 días) es la credencial: sin firma o alterada 403, llave de otra empresa 404; solo muestra la confirmación; throttled por IP
        'worker.not-me.confirm', // guest: confirma «No fui yo» con la misma dirección firmada: anula la llave, cierra las sesiones de la persona (valid_after), anula sus enlaces, avisa al contacto de la empresa y queda en la bitácora; una vez; throttled por IP
        'report.shared', // guest: a report from a temporary link (48 h at most, revocable); the token is the credential; throttled
        'compensation.report.shared', // guest: the report of a contract scenario from a temporary link (7 days at most, revocable); groups under the minimum folded into Otros, no annex, nobody named; the token is the credential; throttled
    ];
}

/**
 * Unnamed routes the framework registers and Laravel gives no way to name, keyed by URI.
 *
 * @return list<string>
 */
function unnamedPublicUris(): array
{
    return [
        'up', // health check from bootstrap/app.php `health:`; load balancers must reach it unauthenticated
    ];
}

/** @return list<LaravelRoute> */
function appRoutes(): array
{
    return array_values(Route::getRoutes()->getRoutes());
}

/**
 * Resolved the way the router runs them: groups expanded (and aliases, once the HTTP kernel registered them), so `auth` inside a middleware group
 * (Horizon wraps its routes in a `horizon` group built from config/horizon.php) counts too. A page of the person who works
 * (`me/{slug}/...`) takes their session instead (RequireWorkerSession: without it, to enter; it never makes the person the
 * user of the request).
 */
function requiresAuthentication(LaravelRoute $route): bool
{
    return collect(Route::gatherRouteMiddleware($route))
        ->contains(fn (mixed $middleware): bool => is_string($middleware)
            && (in_array($middleware, ['auth', Authenticate::class, RequireWorkerSession::class], true) || Str::startsWith($middleware, ['auth:', Authenticate::class.':'])));
}

test('every route has a name', function () {
    $unnamed = collect(appRoutes())
        ->filter(fn (LaravelRoute $route): bool => $route->getName() === null && ! in_array($route->uri(), unnamedPublicUris(), true))
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unnamed)->toBe([]);
});

test('every route requires authentication unless it is in the public allowlist', function () {
    $unprotected = collect(appRoutes())
        ->reject(fn (LaravelRoute $route): bool => requiresAuthentication($route))
        ->reject(fn (LaravelRoute $route): bool => in_array($route->getName(), publicRouteNames(), true) || in_array($route->uri(), unnamedPublicUris(), true))
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

test('the public allowlist has no stale entries', function () {
    $names = collect(appRoutes())->map(fn (LaravelRoute $route): ?string => $route->getName())->filter()->all();
    $uris = collect(appRoutes())->map(fn (LaravelRoute $route): string => $route->uri())->all();

    expect(array_values(array_diff(publicRouteNames(), $names)))->toBe([])
        ->and(array_values(array_diff(unnamedPublicUris(), $uris)))->toBe([]);
});

test('the person\'s pages that take their session also enter the company of their address first', function () {
    $pages = collect(appRoutes())->filter(fn (LaravelRoute $route): bool => in_array(RequireWorkerSession::class, Route::gatherRouteMiddleware($route), true));

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $route) {
        $middleware = Route::gatherRouteMiddleware($route);

        expect($route->uri())->toStartWith('me/{tenant}/')
            ->and(array_search(App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant::class, $middleware, true))->toBeLessThan(array_search(RequireWorkerSession::class, $middleware, true));
    }
});

test('no page of the telework tool is opened with the portal link any more', function () {
    $telework = collect(appRoutes())->filter(fn (LaravelRoute $route): bool => Str::startsWith((string) $route->getActionName(), 'App\\Modules\\Telework\\Http\\Controllers\\Portal'));

    expect($telework->map(fn (LaravelRoute $route): string => $route->uri())->filter(fn (string $uri): bool => Str::contains($uri, '{token}'))->values()->all())
        ->toBe(['portal/{token}']);
});
