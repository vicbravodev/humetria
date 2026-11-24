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
        'roadmap', // guest: lo que Humetría planea agregar; texto fijo, sin datos de nadie
        'tools.nom_035', 'tools.nom_037', 'tools.ley_silla', 'tools.complaints', 'tools.compensation', 'tools.attendance',
        'tools.leave', 'tools.org_chart', 'tools.engagement', // guest: la página pública de cada herramienta (ToolPages); texto fijo, sin datos de nadie
        'guides.workday-2027', // guest: la guía de la reforma de jornada; texto fijo, sin datos de nadie
        'blog.index', // guest: el índice público del blog; artículos de archivos .md ya revisados; sin datos de nadie
        'blog.show', // guest: un artículo del blog por su slug; solo los publicados (revisados y con fecha cumplida), un borrador o un slug desconocido da 404; texto fijo, sin datos de nadie
        'blog.sitemap', // guest: el sitemap de los artículos del blog para los buscadores; solo direcciones y fechas
        'films.show', // guest: the films of the landing page, served with byte ranges so a chapter can jump inside one; only files named humetria-*.mp4|webm that exist in public/video, anything else is a 404
        'demo-requests.store', // landing demo form; throttled + honeypot
        'demo.door', // guest: la puerta de una demostración en vivo (el QR que escanea el público); el código aleatorio de una sesión que abrió la consola y que vence es la credencial; solo entrega trabajadores de la empresa demo y redirige a un enlace de un solo uso de WorkerAccess (/me/{slug}/link/{token}) emitido como la jefa de RH de la demo; un código cerrado, vencido, desconocido o de otra empresa da la misma página; throttled por dirección
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
        'workers.show', // guest: las personas ven los centros de su empresa y lo que la empresa decidió que todos lean; el enlace permanente (derivado, rotable, desactivable, con vencimiento) es la credencial; sin sesión, sin cookies y sin rastro de quien la abre; el mismo 404 neutro por cualquier causa; throttled por dirección y por enlace
        'workers.center', // guest: las personas ven lo que un centro publica (la política vigente); el enlace permanente es la credencial; un centro ajeno, archivado o inexistente da el mismo 404 neutro que un enlace inventado; sin sesión ni cookies; throttled por dirección y por enlace
        'messaging.webhooks.meta.verify', // guest: el proveedor no tiene cuenta; la firma del cuerpo crudo es la credencial; sin sesión ni cookies; throttled por dirección; tamaño máximo
        'messaging.webhooks.meta.receive', // guest: el proveedor no tiene cuenta; la firma del cuerpo crudo es la credencial; sin sesión ni cookies; throttled por dirección; tamaño máximo
        'messaging.webhooks.resend.receive', // guest: el proveedor no tiene cuenta; la firma del cuerpo crudo es la credencial; sin sesión ni cookies; throttled por dirección; tamaño máximo
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
        'attendance.device.punch.show', // guest: la pantalla Marcar de la persona en su dispositivo vinculado (sin cuenta), desde su portal `/me/{slug}` o desde el QR de la pantalla del centro, con su código (que solo vale 60 s); no muestra nada de nadie; throttled por IP
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
        'sso.start', // guest: starts the sign-in with the identity provider of the company in the address; the same 404 for a company that does not exist, has no SSO in its plan, switched it off or proved no domain; state, nonce and PKCE in the session; throttled per address
        'sso.callback', // guest: the identity provider comes back here with a code; the session of the start (state, nonce, PKCE verifier, company) is the credential, the ID token must name our issuer, client and nonce, and its email must be a verified one at a domain the company proved, of someone who is already a member; never creates an account; the same generic refusal for everything else; throttled per address
        'sso.staff.start', // guest: starts the sign-in of Humetría's staff with the platform provider (state, nonce, PKCE in the session); 404 when none is configured; throttled per address
        'sso.staff.callback', // guest: the provider comes back with a code; the session of the start is the credential, the ID token must name our issuer, client and nonce, with a verified email at the staff domain of an existing account that holds a platform role; never creates an account; the same generic refusal for everything else; throttled per address
        'sso.discover', // guest: finds the company whose proven domain is the one after the @ of a work email and sends there; the same generic refusal when none has it or SSO is not available; throttled per address
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

test('what asks for a recent proof takes the person\'s session first', function () {
    $delicate = collect(appRoutes())->filter(fn (LaravelRoute $route): bool => in_array(App\Modules\WorkerAccess\Http\Middleware\RequireRecentProof::class, Route::gatherRouteMiddleware($route), true));

    foreach ($delicate as $route) {
        $middleware = Route::gatherRouteMiddleware($route);

        expect(array_search(RequireWorkerSession::class, $middleware, true))->toBeInt()->toBeLessThan(array_search(App\Modules\WorkerAccess\Http\Middleware\RequireRecentProof::class, $middleware, true));
    }
});

/**
 * The addresses that carry a credential, each with its reason. A credential in an address leaks through history, logs,
 * shared screens and forwarded emails: the person who works never has one that serves more than once (goal 1 of the
 * WorkerAccess design). Their pages read the session; the ones here are single-use, signed, or not the person's.
 *
 * @return array<string, string>
 */
function credentialAddresses(): array
{
    return [
        // The person's, single use.
        'worker.link.show' => 'el enlace de entrada: un solo uso, vence en 48 h (RH) o 30 min (la persona); mostrarlo no lo gasta',
        'worker.link.open' => 'gasta el mismo enlace de entrada al abrir la sesión: de dos a la vez solo uno gana',
        'worker.not-me.show' => '«No fui yo»: {credential} es el id de la llave; la credencial es la firma de la app (signed, 30 días) y solo revoca',
        'worker.not-me.confirm' => '«No fui yo»: la misma dirección firmada; revoca una vez',
        // Not the person's: a screen, an anonymous survey, a company's channel, a report shared for a while.
        'attendance.device.kiosk.activate' => 'la pantalla del QR de un centro (no una persona) cambia el enlace por una cookie y el enlace muere',
        'survey.show' => 'encuesta anónima NOM-035: el enlace es de un solo uso y no identifica a nadie ante RH',
        'survey.submit' => 'la misma encuesta: envía una guía, una vez',
        'engagement.survey.show' => 'encuesta anónima de clima o pulso, de un solo uso',
        'engagement.survey.submit' => 'la misma encuesta: se envía una vez',
        'complaints.public.show' => 'el código del canal de denuncias es de la empresa, no de una persona',
        'complaints.public.centers' => 'el mismo código de la empresa',
        'complaints.public.store' => 'el mismo código de la empresa',
        'complaints.follow-up.show' => 'el mismo código de la empresa; la referencia y la clave del caso van en el cuerpo',
        'complaints.follow-up.open' => 'el mismo código de la empresa',
        'complaints.follow-up.messages.store' => 'el mismo código de la empresa',
        'complaints.follow-up.withdraw' => 'el mismo código de la empresa',
        'workers.show' => 'la página de trabajadores de la empresa: un enlace de toda la empresa, rotable, sin nada de nadie',
        'workers.center' => 'la misma página, por centro',
        'report.shared' => 'un reporte de RH compartido por 48 h como máximo, revocable',
        'compensation.report.shared' => 'un escenario de compensación compartido por 7 días como máximo, revocable',
        'password.reset' => 'restablecer la contraseña de una cuenta del panel: el token de Fortify es de un solo uso y vence',
        'invitations.show' => 'la invitación de un miembro del panel: un solo uso',
        'invitations.accept' => 'la misma invitación: se acepta una vez',
        'films.show' => 'no es credencial: el nombre de un video de la landing',
        'blog.show' => 'no es credencial: el slug público de un artículo, el mismo para todo el mundo',
        'demo.door' => 'la puerta de la empresa demo: el código es de una sesión en vivo, no de una persona; vence en horas, la sala la cierra y cada escaneo recibe un enlace de entrada de un solo uso',
    ];
}

/** The parameter names that can only be a credential, whatever their shape. */
const CREDENTIAL_PARAMETER = '/token|code|secret|key|credential|signature|hash|link/i';

/**
 * The routes reachable without an account of the panel whose address carries a credential: a parameter named like one, or
 * any other than the company's slug that is not a record's UUID or a date (a record the session authorizes is not a
 * credential).
 *
 * @return list<string>
 */
function addressesWithCredentials(): array
{
    return collect(appRoutes())
        ->filter(fn (LaravelRoute $route): bool => in_array($route->getName(), publicRouteNames(), true) || in_array(RequireWorkerSession::class, Route::gatherRouteMiddleware($route), true))
        ->filter(fn (LaravelRoute $route): bool => collect($route->parameterNames())->contains(fn (string $name): bool => $name !== 'tenant'
            && (preg_match(CREDENTIAL_PARAMETER, $name) === 1 || ! in_array($route->wheres[$name] ?? null, ['[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}', '\d{4}-\d{2}-\d{2}'], true))))
        ->map(fn (LaravelRoute $route): string => (string) $route->getName())
        ->values()
        ->all();
}

test('no address of the person who works carries a credential that serves more than once', function () {
    expect(array_values(array_diff(addressesWithCredentials(), array_keys(credentialAddresses()))))->toBe([])
        ->and(array_values(array_diff(array_keys(credentialAddresses()), addressesWithCredentials())))->toBe([]);

    // Under the person's addresses only the single-use ones: the entrance link, «No fui yo» (signed) and the screen of a center.
    $personal = collect(appRoutes())
        ->filter(fn (LaravelRoute $route): bool => array_key_exists((string) $route->getName(), credentialAddresses()) && Str::startsWith($route->uri(), ['me/', 'a/', 'portal']))
        ->map(fn (LaravelRoute $route): string => (string) $route->getName())->sort()->values()->all();
    expect($personal)->toBe(['attendance.device.kiosk.activate', 'worker.link.open', 'worker.link.show', 'worker.not-me.confirm', 'worker.not-me.show'])
        ->and((Route::getRoutes()->getByName('worker.not-me.show') ?? throw new LogicException)->gatherMiddleware())->toContain('signed:relative')
        ->and((Route::getRoutes()->getByName('worker.not-me.confirm') ?? throw new LogicException)->gatherMiddleware())->toContain('signed:relative');
});

test('a page of the person that took a token in its address would be caught', function (string $uri) {
    Route::get($uri, fn (): string => '')->middleware([App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant::class, RequireWorkerSession::class])->name('portal.sample.show');
    Route::getRoutes()->refreshNameLookups();

    expect(addressesWithCredentials())->toContain('portal.sample.show');
})->with([
    'a token' => 'me/{tenant:slug}/sample/{token}',
    'anything that is not a record' => 'me/{tenant:slug}/sample/{person}',
]);
