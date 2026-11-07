<?php

declare(strict_types=1);

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
        'portal.show', // guest: la persona teletrabajadora no tiene cuenta; el token es la credencial; throttled
        'portal.checklist.show', // guest: la lista de verificación de la persona; el token del portal es la credencial; solo ve sus lugares; throttled
        'portal.checklist.store', // guest: envía la lista una sola vez, bajo candado; el token del portal es la credencial; throttled
        'portal.evidence.store', // guest: la persona envía las fotos que pidió la Comisión, una sola vez, bajo candado; el token del portal es la credencial; solo sus lugares; throttled
        'portal.documents.file', // guest: la persona lee el PDF que le pidieron confirmar; el token del portal es la credencial; solo documentos pedidos a ella; throttled
        'portal.documents.acknowledge', // guest: la persona confirma que leyó, una sola vez, bajo candado; el token del portal es la credencial; throttled
        'portal.notices.show', // guest: la persona ve sus avisos escritos (6.3, 6.7, 6.9) y lo que envió; el token del portal es la credencial; solo con periodo abierto; throttled
        'portal.notices.store', // guest: la persona envía un aviso escrito; el token del portal es la credencial; solo con periodo abierto; throttled
        'portal.equipment.respond', // guest: la persona confirma que recibió un equipo o lo rechaza con su motivo, una sola vez, bajo candado; el token del portal es la credencial; solo su equipo; throttled
        'portal.seating.show', // guest: la persona ve su página de Ley Silla; el token del portal es la credencial; solo ve lo suyo; un enlace no válido da 404 sin decir nada más; throttled
        'portal.seating.inputs.store', // guest: la persona envía su calzado y sus molestias, de forma voluntaria y tras aceptar el aviso; el token del portal es la credencial; throttled
        'portal.seating.acknowledgments.store', // guest: la persona confirma, una sola vez, que le informaron de su evaluación; el token del portal es la credencial; solo la suya; throttled
        'portal.seating.reports.store', // guest: la persona reporta un asiento dañado sin que se guarde quién; el token del portal es la credencial; throttled
        'portal.policy.show', // guest: la persona lee la política vigente de su centro y su propio estado de acuse; el token del portal es la credencial; solo ve lo suyo y lo que la empresa publicó; un enlace no válido da 404 sin decir nada más; throttled
        'portal.support.show', // guest: la persona canalizada por aviso en su portal lee el mensaje de su empresa, en usted, con el destino propuesto; nada de la Guía I ni la nota de RH; el token del portal es la credencial; solo lo suyo; un enlace no válido o sin mensaje da 404; throttled
        'portal.policy.acknowledge', // guest: la persona confirma, una sola vez, que leyó la versión vigente de la política; el token del portal es la credencial; solo la suya; throttled
        'workers.show', // guest: las personas ven los centros de su empresa y lo que la empresa decidió que todos lean; el enlace permanente (derivado, rotable, desactivable, con vencimiento) es la credencial; sin sesión, sin cookies y sin rastro de quien la abre; el mismo 404 neutro por cualquier causa; throttled por dirección y por enlace
        'workers.center', // guest: las personas ven lo que un centro publica (la política vigente); el enlace permanente es la credencial; un centro ajeno, archivado o inexistente da el mismo 404 neutro que un enlace inventado; sin sesión ni cookies; throttled por dirección y por enlace
        'portal.visits.confirm', // guest: la persona autoriza por escrito la visita a su lugar; el token del portal es la credencial; solo sus lugares; throttled
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
        'attendance.device.enroll.show', // guest: la persona abre en su teléfono el enlace que le llegó por correo (RH solo lo ve para entregarlo en mano si la persona no tiene correo, y queda en la bitácora; sin cuenta) para leer el acuerdo; el enlace es la credencial: de un solo uso, vence en 48 h y un enlace usado, reemplazado, vencido, ajeno o de alguien dado de baja da el mismo 404; throttled por IP y por enlace
        'attendance.device.enroll.options', // guest: da las opciones WebAuthn para crear la llave del teléfono de la persona del enlace; el desafío es de un solo uso y vence en 60 s; el mismo 404 que la página; throttled por IP y por enlace
        'attendance.device.enroll.register', // guest: acepta el acuerdo y guarda la llave verificada (firma, origen, verificación de la persona); consume el enlace en una sola actualización condicional; el mismo 404 que la página; throttled por IP y por enlace
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
 * (Horizon wraps its routes in a `horizon` group built from config/horizon.php) counts too.
 */
function requiresAuthentication(LaravelRoute $route): bool
{
    return collect(Route::gatherRouteMiddleware($route))
        ->contains(fn (mixed $middleware): bool => is_string($middleware)
            && (in_array($middleware, ['auth', Authenticate::class], true) || Str::startsWith($middleware, ['auth:', Authenticate::class.':'])));
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
