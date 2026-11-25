# Manual de operación: la app instalable de la persona trabajadora

Para quien opera Humetria en producción (Dokploy). Describe lo que la app instalable de la persona (marcar con QR, marcar
sin conexión, avisos push) trae al servidor: variables, interruptores, llaves, señales en logs, medidas y la revisión en
teléfonos reales. El diseño está en `docs/superpowers/specs/2026-11-20-worker-pwa-offline-design.md`; la norma, en
`docs/normas/jornada/criterios.md` (3.3.1 y 5.2). Este manual, el diseño y el código dicen lo mismo:
`tests/Unit/OperationsDocsTest.php` falla si una variable, una señal o una cifra de aquí deja de ser la del código.

## 1. Qué es

- **Manifest** por empresa: `/me/{slug}/manifest.webmanifest` (nombre de la empresa, íconos 192 y maskable).
- **Service worker**: `/me/{slug}/sw.js`, con alcance `/me/{slug}`. El archivo vive en una carpeta más angosta que su alcance,
  por eso la respuesta lleva la cabecera `Service-Worker-Allowed`. Guarda solo archivos del build y el cascarón; nunca HTML de
  Inertia ni datos de personas.
- **Cascarón**: `/me/{slug}/offline`, lo que abre el ícono sin conexión (Marcar).
- **Cola de marcas** en el teléfono (almacenamiento del navegador): las marcas hechas sin conexión esperan ahí hasta entregarse.
- **Boletos**: `POST /me/{slug}/tickets` (`attendance.device.tickets`). Un boleto firmado con `APP_KEY` es la cota inferior de la
  hora de una marca sin conexión: el servidor lo entrega con cada respuesta firmada y el teléfono guarda los últimos.
- **Entrega**: `POST /me/{slug}/punch/offline` (`attendance.device.punch.offline`). Cada marca se autoriza con su propia firma y
  su boleto, de un solo uso.
- Boletos y entrega van **sin sesión, cookies ni CSRF** (como el canal de denuncias): después de un apagón puede no haber sesión.
  Los protegen la firma, el boleto, los limitadores y `RejectCrossSiteRequests`.

## 2. Parámetros

Fuente única: la configuración. Esta tabla es lo que hoy tiene el código.

| Parámetro | Llave de configuración | Variable de entorno | Valor |
|---|---|---|---|
| Función sin conexión en el cliente | `attendance.offline.enabled` | `ATTENDANCE_OFFLINE_ENABLED` | encendida |
| Boletos por lote | `attendance.offline.ticket_batch` | — | 8 boletos |
| Boletos guardados en el teléfono | cliente (`MAX_TICKETS`) | — | 16 boletos |
| Horizonte del boleto | `attendance.offline.ticket_horizon_days` | — | 35 días |
| Corte de intervalo ancho | `attendance.offline.wide_interval_minutes` | — | 240 min |
| Hora declarada cuenta hasta | `attendance.offline.count_declared_max_hours` | — | 72 h |
| Ventana vieja del QR | `attendance.offline.qr_old_window_minutes` | — | 30 min |
| Marca ya registrada en línea | `attendance.offline.already_recorded_window_minutes` | — | 10 min |
| Marca vieja sin verificar | `attendance.offline.review_age_days` | — | 7 días |
| Marcas por entrega | `attendance.offline.delivery_batch_max` | — | 20 marcas |
| Cuerpo de la entrega | `OfflinePunchRequest` | — | 64 KB |
| Nota de la persona | `OfflinePunchRequest` | — | 500 caracteres |
| Bloqueo por días sin cubrir | `attendance.week.block_uncovered_days` | `ATTENDANCE_WEEK_BLOCK_UNCOVERED` | encendido |
| Llaves del dispositivo activas | `worker_access.device_keys_max` | `WORKER_ACCESS_DEVICE_KEYS_MAX` | 3 llaves |
| Vigencia del pase | `worker_access.pass_hours` | `WORKER_ACCESS_OFFLINE_PASS_HOURS` | 24 h |
| Apagado del service worker | `worker_access.pwa_sw_kill` | `PWA_SW_KILL` | apagado |
| Límite de entrega por IP | limitador `attendance-offline` | — | 600 peticiones por minuto |
| Límite de boletos por IP | limitador `attendance-tickets` | — | 300 por minuto |
| Espacio entre pedidos de boletos | cliente | — | 30 s |
| Límite de la llave del dispositivo | limitador `worker-device-key` | — | 30 por minuto por IP, 20 por minuto por sesión |
| Espera de navegación y `punch/options` | cliente | — | 4 s |
| Espera de `punch/store` | cliente | — | 20 s |
| Espera de la cola entre entregas | cliente | — | de 5 s a 15 min |
| Suscripciones push por persona | `StoreSubscription::MAX_PER_PERSON` | — | 5 suscripciones |
| TTL del aviso push | `SendPush::TTL` | — | 3600 s |
| Llaves VAPID | `services.vapid.*` | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | sin valor en `.env.example` |

Solo `ATTENDANCE_OFFLINE_ENABLED`, `ATTENDANCE_WEEK_BLOCK_UNCOVERED`, `PWA_SW_KILL`, las dos de `WORKER_ACCESS_*` y las de VAPID
son variables; el resto son constantes del código: cambiarlas es un cambio de código.

## 3. Interruptores y qué no apagan

| Variable | Qué hace |
|---|---|
| `ATTENDANCE_OFFLINE_ENABLED=false` | Apaga la cola y la interfaz sin conexión del cliente (lo lee por `context.offline`). |
| `PWA_SW_KILL=true` | `/me/{slug}/sw.js` sirve el worker de apagado: borra sus cachés y se desregistra. Las páginas dejan de registrarlo. |
| `ATTENDANCE_WEEK_BLOCK_UNCOVERED=false` | Deja de bloquear «Confirmar» la semana por días sin cubrir; los días se siguen calculando y logueando. |

**Ninguno apaga la entrega**: lo que ya está guardado en los teléfonos se entrega igual. Cambiar uno exige reiniciar `web` (la
configuración se cachea al arrancar).

## 4. Llaves

- **`APP_KEY`**: firma los boletos y los pases. Al rotarla, la anterior va en `APP_PREVIOUS_KEYS` (separadas por comas) y debe
  quedarse ahí **al menos 35 días**, el horizonte de un boleto (`attendance.offline.ticket_horizon_days`). Rotar `APP_KEY` sin
  ponerla ahí invalida boletos y pases y deja marcas «No aceptada» en los teléfonos.
- **VAPID** (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`): se generan con `sail artisan notifications:vapid-keys`
  (las imprime; no escribe el `.env`) y se cargan en Dokploy. `VAPID_SUBJECT` empieza con `mailto:` o `https://`. Sin las tres,
  no se manda ningún aviso (señal `push.unconfigured`). **Rotarlas invalida toda suscripción**: la app se vuelve a suscribir al
  abrirse.
- **Hosts permitidos de push** (`PushHosts`): `fcm.googleapis.com`, `push.services.mozilla.com`, `push.apple.com` y
  `notify.windows.com`, o un subdominio de ellos, siempre `https`, sin usuario ni puerto. Una suscripción a otro host se rechaza:
  el servidor no publica a donde la persona escriba.
- `minishlink/web-push` usa `ext-gmp` o `ext-bcmath` «para rendimiento»; no las exige (sí exige `curl`, `mbstring` y `openssl`,
  que la imagen base ya trae). El `Dockerfile` las instala junto con `gd` y `exif` (etapa `base`); se comprobó construyendo esa
  etapa y listando las extensiones (`php -m`). Falta solo el primer envío real a FCM, Apple y Mozilla (sección 9).

## 5. Señales en logs

Todas son eventos de una línea en JSON, ya redactados como todo log; ninguna lleva a la persona, la posición, la nota, el boleto,
el pase ni el QR (los que traen `employee_id` lo traen como cualquier log de asistencia).

| Señal | Cuándo | Datos útiles |
|---|---|---|
| `attendance.offline_mark_received` | Una marca sin conexión entró | `basis`, `interval_minutes`, `queue_age_minutes`, `signer_type`, `review_flags` |
| `attendance.offline_mark_rejected` | Una marca llegó y no se aceptó | `reason`, `queue_age_minutes`, `signer_type`; con fila también `basis`, `interval_minutes` |
| `attendance.offline_mark_already_recorded` | La copia en la cola era de una marca hecha con conexión | `type` |
| `attendance.offline_queue_reported` | El teléfono pidió boletos e informó su cola | `count`, `oldest_age_minutes` (nada de personas) |
| `attendance.week_uncovered_days_computed` | Se confirmó una semana con días sin cubrir (bloqueo apagado) | `uncovered_days` (conteo), `week_start` |
| `push.unconfigured` | Faltan llaves VAPID o el sujeto no es válido | `reason`: `missing_keys` o `subject_invalid` |
| `push.subscribed` | Una suscripción nueva | `replaced` |
| `push.sent` / `push.expired` / `push.failed` | Resultado de un envío (aceptado; suscripción vencida y borrada; otro rechazo) | `subscription_id`, `status` o `reason` (`invalid_keys`, `exhausted`) |
| `push.subscriptions_forgotten` | Se dieron de baja (salir de la app, o la empresa retiró el acceso) | `count`, `reason` |
| `push.vapid_keys_generated` | Alguien corrió el comando de llaves | — |
| `push.demo_blocked` | La empresa demo no manda push | — |

Motivos (`reason`) de una marca no aceptada: `ticket_invalid` y `signature_invalid` (no dejan fila: no hay a quién atribuirla),
`person_inactive`, `ticket_expired`, `pass_expired`, `agreement_not_accepted`, `sequence_conflict` y `wrong_center`.

Las consultas se hacen en PostHog Logs filtrando por el nombre del evento (el mensaje del log) y agrupando por el atributo de la
columna «Datos útiles»; no hay ningún evento de analítica ni nada contado en el teléfono.

## 6. Medidas de éxito

Solo lo que ve el servidor, desde esos logs. Umbrales iniciales, **pendientes del dueño** hasta que los confirme:

| Medida | Cómo se lee | Se revisa si |
|---|---|---|
| Marcas entregadas por día | conteo de `attendance.offline_mark_received` por día, con QR y sin QR (`basis`) | tendencia; sin umbral |
| Proporción no aceptada | `attendance.offline_mark_rejected` entre lo entregado, por `reason` | pasa de 5 % en un día con 20 entregas o más; o `signature_invalid` pasa de 1 %; o `ticket_invalid` aparece más de 3 veces al día |
| Ancho del intervalo | p50 y p95 de `interval_minutes` | el p95 pasa de 240 durante una semana (se revisa el corte de intervalo ancho) |
| Edad en la cola al entregar | p95 de `queue_age_minutes` | pasa de 1 440 (24 h): la app no está entregando sola al volver la conexión |
| Colas que no entregan | `oldest_age_minutes` de `attendance.offline_queue_reported` | alguna pasa de 24 h: se revisa el mismo día |

Responden a «nadie reporta que no pudo marcar»: una marca que no llega deja rastro, ya sea en la cola reportada, en un rechazo o
en su edad al entregar.

## 7. Despliegue y reversa

- La versión de la caché es `app-revision` (`APP_REVISION`, la `GIT_SHA` de la imagen): `humetria-pwa-{slug}-{revision}`. Cada
  empresa tiene su caché y un despliegue nuevo no borra la de otra empresa instalada en el mismo teléfono.
- Un teléfono con el worker viejo y el servidor nuevo sigue entregando (reto `v1`, boleto `v1`).
- El esquema solo avanza: las migraciones son aditivas y no se borran columnas, así que volver a una imagen anterior no pierde
  marcas.
- El worker se compila con `npm run build:sw`, que corre en shell POSIX (Linux y macOS); `npm run build` ya lo incluye.
- Reversa por función, sin desplegar: las tres variables de la sección 3.

## 8. Riesgos aceptados

- Con conexión, alguien puede usar un boleto viejo y declarar una hora anterior: sin bandera gana a lo más 240 minutos; con
  `wide_interval`, hasta 72 horas contadas hasta que Recursos Humanos verifique; más allá cuenta solo la cota probada.
- Quien usa el plan B (llave del dispositivo) debe entrar a `/me` con sesión a diario: el pase vale 24 h.
- Las marcas sin conexión no se podan.

## 9. Revisión en teléfonos reales

La lista consolidada del PR. Quien la corre anota resultado y fecha.

| Teléfono / sistema | Acción | Resultado esperado | Umbral | Resultado y fecha |
|---|---|---|---|---|
| ≥ 2 Android (uno viejo o con Play services desactualizado), app instalada | Firmar sin conexión con la llave de acceso, con «reintentar una vez» | Firma | ≥ 4 de 5 intentos por teléfono; si no, solo llave del dispositivo en Android | Validado por el dueño con los datos del 2026-11-20; no se repite. |
| Android, app instalada | `navigator.storage.persist()` y cerrar la app con marcas en cola | Concedido; el contador sigue | 3 de 3 | |
| iPhone (Safari, manual) y Android (Chrome) | Instalar | Nombre de la empresa; ícono 192 y maskable sin recorte | 1 de 1 por sistema | |
| iPhone y Android, instalada | Cerrar la app, modo avión, abrir desde el ícono | Abre Marcar sin conexión (el cascarón) | 3 de 3 | |
| iPhone y Android | Marcar con QR y sin QR en modo avión; volver la conexión | «Guardada sin conexión», contador, se entrega sola y Recursos Humanos la ve | 3 de 3 | |
| iPhone y Android | Señal intermitente: modo avión justo después de enviar | Al reintentar, `duplicate`; se borra del teléfono | 2 de 2 | |
| iPhone y Android | Un fin de semana sin entregar (≈ 63 h) con varias marcas | Entran con `wide_interval`, cuenta la hora declarada acotada | 1 de 1 | |
| iPhone con llave de iCloud; Android con Google Password Manager | Firma del reto v1 | Verifica en el servidor | 3 de 3 | |
| iPhone con llave de Chrome / Google | Alta: la práctica falla, «Preparar este teléfono», marcar sin conexión | Plan B: marca con la llave del dispositivo | 2 de 2 | |
| iPhone y Android | Llave del dispositivo tras cerrar y reiniciar; Safari y Chrome (dos motores) | Sobrevive y verifica | 2 de 2 | |
| iPhone y Android | Reemitir el acceso con marcas en cola | Las firmadas antes de la revocación entran; las nuevas no se firman | 1 de 1 | |
| iPhone y Android | Lector de QR con moiré, poca luz, Android viejo | Lee; la cámara se apaga al salir | ≥ 4 de 5 | |
| iPhone y Android | Inicio → Marcar y Mi semana → Marcar (navegación de Inertia) | Cámara y ubicación funcionan | 2 de 2 | |
| iPhone y Android | Semana con un día laborable vacío; Recursos Humanos registra el permiso | «Confirmar» apagado con la fecha; después confirma | 1 de 1 | |
| iPhone y Android | Semana confirmada y luego entrega de una marca de esos días | La semana queda pendiente otra vez | 1 de 1 | |
| iPhone (iOS ≥ 16.4, instalada) y Android | Permitir avisos tras la primera marca; Recursos Humanos revisa | Aviso con pantalla bloqueada: solo «Tiene un aviso en Humetría»; tocar abre Marcar | 3 de 3; la suscripción sigue tras varios envíos | |
| iPhone y Android | «Salir» con avisos activos | Ya no llega nada | 1 de 1 | |
| Producción (Dokploy) | Envío real a FCM, Apple y Mozilla | 201 (`push.sent`) | 1 de 1 por servicio | |
| iPhone y Android | Dos despliegues seguidos con la app abierta | El worker nuevo toma la versión nueva; el cascarón no queda roto | 2 de 2 | |
| iPhone y Android | `PWA_SW_KILL=true` | La app vuelve a la red, las cachés se borran, las marcas guardadas se entregan | 1 de 1 | |
| Un teléfono con dos empresas instaladas | Abrir cada una sin conexión | Cada una abre su cascarón y no borra la caché de la otra | 1 de 1 | |
| Gmail, Outlook, Facebook, Instagram | Abrir el enlace dentro de la app | Aviso «Ábralo en Safari o Chrome» | 4 de 4 | |
| iPhone y Android, 375 px, claro y oscuro, letra grande | Marcar, Mi semana, cascarón | Legible, sin desborde | 1 de 1 | |
