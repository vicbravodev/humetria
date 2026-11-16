# Manual de operación: observabilidad en PostHog

Para quien opera Humetría. Errores, logs y llamadas a modelos de IA van al mismo proyecto de PostHog (nube US) que mide la
landing (`docs/operations/ssr.md` §8). Todo sale del servidor con `POSTHOG_PROJECT_TOKEN`; sin token no sale nada y la app
funciona igual. `tests/Unit/OperationsDocsTest.php` exige que este manual nombre cada señal como la escribe el código.

## 1. Qué se manda

| Qué | Cómo | Dónde se ve en PostHog | Qué lleva | Qué nunca lleva |
|-----|------|------------------------|-----------|-----------------|
| Excepciones del servidor | evento `$exception` en cola (`App\Support\PostHogExceptions`), máximo 10 por clase al minuto | Error tracking | clase, archivo, línea, traza sin argumentos, ruta, `request_id` | el mensaje, la URL, el usuario |
| Logs | canal `posthog` (`App\Logging\PostHogLogs`), OTLP/HTTP JSON a `https://us.i.posthog.com/i/v1/logs`, en lote al terminar cada petición, job o comando (o cada 100 líneas); nunca por la cola | Logs | el nombre del evento (`modulo.accion`) y su contexto ya redactado (`RedactSensitiveData`); de una excepción, solo su clase, archivo y línea | respuestas, contraseñas, tokens (redactados), el texto de una excepción |
| Errores del navegador (fuera de la landing) | `$exception` desde `posthog-js` (build sin scripts externos) en modo solo errores (`resources/js/lib/error-tracking.ts`); los mandan el `AppErrorBoundary` (una página que se rompe al pintar), los errores y promesas sin atrapar de `window` y una respuesta fallida de Humi (`HumiStreamError`, no las negativas a propósito) | Error tracking | tipo, mensaje enmascarado (correos, números largos, URLs a su origen), marcos con la ruta del script, `page` (el componente de Inertia), `request_id`, navegador y sistema | la dirección de la página (`$current_url` es solo el origen: la encuesta y los portales llevan un token de un solo uso en la ruta), el referente, la sesión, los pasos previos, el texto de Humi; nada en el navegador (`persistence: 'memory'`), sin perfil ni IP |
| Llamadas a modelos | evento `$ai_generation` en cola (`App\Modules\Assistant\Listeners\SendLlmAnalytics`), uno por llamada, también si falla | LLM analytics | modelo, proveedor, tokens, latencia en segundos, error (clase), herramientas usadas, `tenant_id`; de **Humi**, además, el prompt completo (instrucciones y mensaje ya enmascarado) y la respuesta | de cualquier otro agente (resumen de comentarios de clima): ningún texto, ni de entrada ni de salida |

Notas:

- `distinct_id` de `$ai_generation`: HMAC-SHA256 del id de usuario con `APP_KEY` (seudónimo estable); `$process_person_profile: false`.
  `$ai_trace_id` es la conversación de Humi, así una conversación se lee completa como traza.
- El prompt y la respuesta de Humi viajan en la carga del job `SendPostHogEvent`: pasan por Valkey y Horizon los muestra
  en "recent jobs" (1 hora). Es lo que decidió el dueño; los comentarios de clima nunca entran ahí.
- Si Valkey o el limitador fallan al reportar una excepción, se pierde el evento de PostHog, no el log de la excepción:
  queda `posthog.dispatch_failed` (warning). Si PostHog rechaza o no responde un evento: `posthog.send_failed`. Si PostHog
  Logs no responde, ese lote se pierde en silencio (la misma línea ya está en stderr).

- En el navegador hay dos modos y nunca los dos a la vez (`App\Http\Analytics`): la **landing** (ruta `home`) sigue con
  su medición y su consentimiento (`docs/operations/ssr.md` §8); **toda otra página** (panel, encuestas, buzón, portales,
  Humi) recibe el bloque `error-tracking-config` (solo token y host) y la CSP abre `https://us.i.posthog.com` en
  `connect-src`, con el token solo: no lleva datos de visitantes. Sin token, ni bloque ni origen. `before_send` deja pasar
  únicamente `$exception` y solo las propiedades de una lista cerrada (`resources/js/lib/error-event.ts`).
- Si una página se rompe, la persona ve «Algo salió mal» con el **código para soporte** (el `request_id`); la página de
  error 500/503 lo muestra igual. Con ese código se encuentran en Logs las líneas de esa petición y en Error tracking los
  `$exception` del servidor y del navegador (`request_id`).

## 2. Variables

| Variable | Producción | Qué hace |
|----------|-----------|----------|
| `POSTHOG_PROJECT_TOKEN` | el `phc_…` del proyecto | sin él, nada de esto sale |
| `LOG_CHANNEL` | `stack` | |
| `LOG_STACK` | `LOG_STACK=stderr,posthog` | stderr para Docker/Dokploy, posthog para guardar y alertar |
| `LOG_POSTHOG_LEVEL` | `info` | nivel mínimo que se manda a PostHog Logs |

## 3. Nombres de los logs que leen las alertas

| Log | Nivel | Cuándo | Contexto |
|-----|-------|--------|----------|
| `queue.job_failed` | error | un job falló para siempre (sin reintentos) | `job`, `queue`, `connection`, `attempts`, `exception` (clase) |
| `queue.long_wait` | warning | Horizon ve una cola esperando más que `horizon.waits` (60 s en `redis:default`) | `connection`, `queue`, `seconds` |
| `schedule.command_failed` | error | un comando programado terminó con error o con código distinto de 0 | `command`, `exception` (clase), `runtime` |
| `schedule.heartbeat` | info | cada 5 minutos, si el scheduler vive | — |
| `posthog.dispatch_failed` | warning | no se pudo encolar un evento para PostHog | `exception` (clase) |
| `posthog.send_failed` | warning | PostHog no aceptó un evento | `exception` (clase) |

Los jobs que mandan un solo correo reintentan 3 veces (60 s y 300 s) antes de `queue.job_failed`. `SendEnrollmentLink` y
`SendFeedbackPublished` corren una sola vez: reintentarlos no es seguro. Los jobs fallidos se guardan una semana
(`queue:prune-failed --hours=168`, igual que `horizon.trim.failed`).

## 4. Alertas que hay que configurar en PostHog

Todas con notificación al correo del dueño. Revisar umbrales tras las primeras semanas con clientes.

1. **Pico de errores** (Error tracking → alerta): más de 10 `$exception` en 15 minutos, o cualquier clase nueva de
   error, del servidor (`$lib = humetria-php`) o del navegador (`$lib = web`). Se ve también en Logs como líneas de nivel error.
2. **Job fallido** (Logs → alerta): al menos 1 línea con cuerpo `queue.job_failed` en 15 minutos.
3. **Comando programado fallido** (Logs → alerta): al menos 1 línea `schedule.command_failed` en 1 hora.
4. **Scheduler caído** (Logs → alerta por ausencia): 0 líneas `schedule.heartbeat` en 15 minutos.
5. **Cola atascada** (Logs → alerta): al menos 1 línea `queue.long_wait` en 15 minutos.
6. **Humi falla** (LLM analytics o Insights sobre `$ai_generation`): proporción con `$ai_is_error = true` mayor a 10 % en
   1 hora, con al menos 5 eventos. Opcional: latencia p95 (`$ai_latency`) mayor a 30 s.
7. **Reporte de errores roto**: al menos 1 línea `posthog.dispatch_failed` o `posthog.send_failed` en 1 hora (los errores
   ya no llegan a Error tracking; revisar Valkey y el token).

## 5. Verificaciones manuales pendientes

- Confirmar en producción que PostHog Logs recibe el lote (pestaña Logs, servicio `humetria`) y que acepta OTLP en JSON;
  si respondiera 415, cambiar el envío a protobuf es trabajo aparte (sin dependencia nueva no se puede: avisar).
- Crear las 7 alertas de la sección 4.
- Con el token puesto, provocar un error en el panel y en una encuesta y confirmar en Error tracking que llega el
  `$exception` con `page` y `request_id`, sin la ruta de la página, y que el navegador no hace otras peticiones a PostHog
  (ni `/flags` ni configuración remota) ni guarda nada en su almacenamiento local.
