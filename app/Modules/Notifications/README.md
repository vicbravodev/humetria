# Notifications

Avisos push **sin contenido** a la persona trabajadora (Web Push con VAPID). Nunca manda correo ni WhatsApp: el correo lo manda cada
módulo con su carpeta `Notifications/` y WhatsApp sale solo por `Messaging`.

## Qué avisa

El teléfono recibe una señal vacía y el service worker de la app (`resources/js/sw/sw.ts`) muestra siempre el mismo texto, «Tiene un
aviso en Humetría» (`NOTICE` de `resources/js/lib/pwa/sw-rules.ts`). El aviso no lleva cuerpo, tipo, hora, nombre ni id: el detalle se
ve en Mi semana al abrir la app. `PushNotifier::notify($employeeId)` recibe solo a la persona; no hay por dónde pasar contenido
(`PrivacyNoticeMatchesCodeTest` lo exige).

Lo disparan dos eventos públicos de Attendance:

- `OfflineMarkReviewed`: RH verificó una marca sin conexión o la marcó incorrecta (con su corrección).
- `CorrectionResolved`: RH aprobó o rechazó una solicitud de corrección. No se escucha `MarkCorrected`: también sale de una corrección
  que RH hizo por su cuenta y nunca de un rechazo.

## Suscripciones

Tabla `push_subscriptions` (`tenant_id`, `employee_id`, `endpoint`, `p256dh`, `auth`, `last_success_at`; `unique(tenant_id, endpoint)`).
`endpoint`, `p256dh` y `auth` no salen de un modelo (`#[Hidden]`), ni de una página, ni de un log (`RedactSensitiveData`).

- Alta: `POST me/{slug}/push` (`portal.push.store`), con la sesión del portal. La persona sale de la sesión, nunca del cuerpo. Tope de
  5 teléfonos por persona (se borra el que se suscribió hace más tiempo). Teléfono compartido, mejor esfuerzo: la dirección pasa a quien
  se suscribió al último.
- La dirección debe ser de un servicio push de navegador (`PushHosts`: Google, Mozilla, Apple, Windows; https, sin usuario ni puerto):
  sin esa lista cualquiera con sesión haría que el servidor mande un POST adonde escriba.
- Baja: `DELETE me/{slug}/push` (`portal.push.destroy`) lo manda la app al tocar «Salir» o «¿No es usted?»; el servicio push que
  responde 404 o 410 también la borra; y `WorkerAccessRevoked` (RH revoca una llave o reemite el acceso, la persona dice «No fui yo»,
  o deja la empresa) borra todas las de la persona. Si el `DELETE` del cliente falla sin red, la suscripción queda y el siguiente
  aviso llega a ese teléfono con el mismo texto fijo, sin datos.
- `GET me/{slug}/push` (`portal.push.show`) da `{ publicKey }`; con `null` la app no ofrece el permiso.

## Envío

`PushNotifier` despacha un `DeliverPush` por teléfono (cola `default`, 3 intentos, espera de 60 y 300 s, después del commit de lo que lo
dijo). `SendPush` hace un POST sin cuerpo con `minishlink/web-push` (`TTL` 3600, `Urgency: normal`, cabecera VAPID) por el cliente HTTP
de Laravel (`LaravelHttpClient`: `Http::fake()` lo cubre). 201 anota `last_success_at`; 404 y 410 borran la fila; 429 espera lo que
diga `Retry-After` (entre 1 y 3600 s); 5xx o sin respuesta reintenta; cualquier otro 4xx se registra y no se borra (un 403 suele ser
una llave VAPID equivocada y borrar vaciaría a todos por un error de configuración). No sale nada de la empresa demo.

## Configuración

`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` y `VAPID_SUBJECT` (`mailto:` con el correo de soporte, o `https://`). Las llaves se generan con
`sail artisan notifications:vapid-keys` (imprime; no escribe el `.env`). Sin cualquiera de las tres, o con un sujeto inválido, no se manda
nada y queda `push.unconfigured` (`missing_keys` o `subject_invalid`). Rotar las llaves invalida toda suscripción: la app se vuelve a
suscribir al abrir.

## Logs

Solo `subscription_id`, `status`, `reason`, `count` y `replaced` (`push.subscriptions_forgotten` solo cuando borró algo): `push.subscribed`, `push.subscriptions_forgotten`, `push.sent`,
`push.expired`, `push.failed`, `push.unconfigured`, `push.demo_blocked` y `push.vapid_keys_generated`. Nunca la dirección, las llaves ni
la persona.
