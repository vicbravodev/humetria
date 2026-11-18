# WhatsApp: operación

WhatsApp es un canal de salida de Humetría (módulo `Messaging`): un solo número, con su nombre y su logo, manda por todas
las empresas, solo con plantillas de categoría UTILITY (invitaciones a encuestas). No hay marketing, no hay
conversaciones. Diseño y decisiones: `docs/superpowers/specs/2026-11-18-whatsapp-channel.md`. El correo se maneja aparte
(`docs/operations/resend-webhook.md`).

## 1. Interruptores

| Variable | Qué hace |
|----------|----------|
| `WHATSAPP_ENABLED` | `false` por omisión: la función no existe en ese despliegue (gateway nulo, el ajuste de canales no ofrece WhatsApp). Se enciende al último, después de la Fase 3. |
| `WHATSAPP_PAUSED` | Parada de emergencia: ningún mensaje sale para ninguna empresa, sin quitar la función. Los mensajes quedan `paused` y no se reenvían solos. |

Además hay una **pausa automática**: `messaging:watch-failures` (cada 5 minutos) abre una pausa de toda la función cuando,
en la ventana, los bloqueos y reportes de spam llegan al umbral. Solo la quita un operador con `messaging:resume`, después
de revisar por qué. Los valores están en `config/whatsapp.php` (`auto_pause`: 5 %, 50 mensajes, 60 minutos; supuestos por
confirmar por Victor antes de la Fase 3). El tope mensual de la plataforma (`WHATSAPP_PLATFORM_MONTHLY_CAP`) abre la misma
clase de pausa. Quitar una pausa **no reenvía nada**.

## 2. Alta en Meta y secretos en Dokploy

1. En Meta Business: la app con el caso de uso de mensajería de WhatsApp Business, el número de Humetría y un usuario de
   sistema con token permanente.
2. Secretos en Dokploy (nunca en el repositorio): `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`,
   `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, y las claves del hash del teléfono en `MESSAGING_RECIPIENT_KEYS`
   (`1:<base64>,2:<base64>`). `WHATSAPP_API_VERSION` es la versión de la Graph API (`v23.0` por omisión).
3. Después de cargar los secretos, el webhook de Meta: dirección `https://<dominio>/webhooks/whatsapp`, el token de
   verificación es `WHATSAPP_VERIFY_TOKEN`, y se suscribe el campo `messages` (y `phone_number_quality_update`). La
   firma `X-Hub-Signature-256` de cada POST se comprueba con el secreto de la app sobre el cuerpo crudo.
4. Las plantillas `survey_invite_v1` y `engagement_invite_v1` (es_MX, categoría UTILITY, dos parámetros de cuerpo y un
   botón de URL) se envían a Meta para su aprobación en cuanto existe el gateway; su texto está en `TemplateRegistry`.
   Si Meta reclasifica una plantilla como MARKETING, WhatsApp queda limitado a las invitaciones NOM-035 y se recalcula el
   presupuesto antes de activarlo.
5. La app de Meta se publica al final.

## 3. Rotación

- **Token de acceso** (`WHATSAPP_ACCESS_TOKEN`): se crea el nuevo en Meta, se cambia en Dokploy, se reinicia.
- **Secreto de la app**: se pone el nuevo en `WHATSAPP_APP_SECRET` y el anterior en `WHATSAPP_APP_SECRET_PREVIOUS`; se aceptan
  los dos mientras dura la rotación. Cuando Meta ya firma con el nuevo, se vacía el anterior.
- **Claves del hash** (`MESSAGING_RECIPIENT_KEYS`): se **agrega** una versión nueva (`3:<base64>`) y se conservan las
  anteriores. Una clave no se quita mientras exista una baja o un mensaje con su versión: como solo se guarda el hash y nunca
  el teléfono, no se puede recalcular. Las filas nuevas usan la versión mayor.

## 4. Límites, costo y topes

- `WHATSAPP_MAX_PER_SECOND` (envíos por segundo, a verificar contra el límite vigente del número), la cola `whatsapp` de
  Horizon (`supervisor-whatsapp`) y el limitador del job; un 429 de Meta reintenta con backoff sin perder la reserva.
- `WHATSAPP_DAILY_CAP_PER_COMPANY`: tope diario por empresa; al llegar, el envío espera al día siguiente
  (`whatsapp.daily_cap_reached`).
- Cupo mensual por plan y tope del dueño: `billing.versions.v1.plans.{plan}.quotas.whatsapp` y el ajuste «Canales de
  comunicación». Costo de Meta y margen: `billing.whatsapp` (supuestos por confirmar). Cada línea de uso guarda el costo y el
  precio; el estado de cuenta suma «Mensajes de WhatsApp» y `PlatformOverview` muestra el margen del mes.

## 5. Conciliación mensual

Cuando llega la factura de Meta: `billing:whatsapp-reconcile 2026-11`. Imprime, por empresa, los mensajes cobrados, los
que nunca se entregaron y el crédito sugerido en centavos, y el costo total de la plataforma del mes para compararlo con la
factura. El crédito lo aplica el operador con el ajuste del estado de cuenta (`AdjustStatement`), con la razón
«Crédito WhatsApp {mes}». Lo que Meta cobre de más lo absorbe Humetría. Cada noche `billing:release-holds` libera las
reservas que nadie usó.

## 6. Calidad del número

El log `whatsapp.quality_changed` (nivel warning) avisa un cambio de calidad del número (campo
`phone_number_quality_update` del webhook): revisar en el administrador de WhatsApp qué empresa lo causó y, si hace falta,
`WHATSAPP_PAUSED=true`. El número es compartido: las quejas de una empresa bajan la calidad para todas.

## 7. Retención y derechos de las personas

- `messaging:purge` (diario, 03:25 hora de México) borra los mensajes y su historial con más de
  `MESSAGING_RETENTION_MONTHS` (12 por omisión) y los eventos de proveedor con más de 7 días. Nunca borra las bajas.
- `messaging:forget` (a mano) pide el teléfono en un campo oculto y borra todos los mensajes escritos a ese teléfono, en todas
  las empresas, con su historial (derechos ARCO). No borra la baja. Los correos no guardan dirección ni huella: se van con la
  retención.
- `messaging:reconcile-unknown` (cada hora) cierra como `unresolved` los mensajes sin estado después de 24 horas;
  `messaging:replay-orphans` (cada minuto) aplica los estados que llegaron antes del id de su mensaje.

## 8. Orden de las fases

1. **Fase 1, local**: gateway falso y `Http::fake()` (el PR).
2. **Fase 2, con Victor y paso a paso**: (a) el número de prueba de la app, con hasta 5 destinatarios: un mensaje llega,
   el webhook recibe `sent`, `delivered` y `read`, y un STOP crea la baja; se confirma el formato del teléfono (`52` + 10
   dígitos). (b) El ping del webhook desde el panel de Meta. (c) El número real, con las dos plantillas aprobadas.
3. **Fase 3, producción**: con las tres salidas de la Fase 2 y los supuestos confirmados; los secretos primero, después el
   callback en Meta y `WHATSAPP_ENABLED` al último.
