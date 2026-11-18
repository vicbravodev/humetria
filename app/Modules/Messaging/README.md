# Messaging (WhatsApp y eventos de correo)

Solo **transporte**: el gateway hacia WhatsApp (Cloud API de Meta, un número central de Humetria, plantillas UTILITY, solo
salida), los webhooks de Meta y de Resend, el registro de plantillas, el consentimiento y el historial de estados de cada
mensaje. El módulo que manda un aviso (Campaigns, Engagement…) sigue eligiendo sus canales en su propio `via()`. Diseño completo:
`docs/superpowers/specs/2026-11-18-whatsapp-channel.md`.

## API pública (solo `Contracts`, `Events` y `Data`)

| Contrato | Para qué |
| --- | --- |
| `WhatsAppGateway` | `send(OutboundMessage): SendOutcome` (Cloud API, nulo con `WHATSAPP_ENABLED=false`, falso en pruebas) |
| `ChannelConsent` | `canSend(tenantId, channel, phone)`: canal activo con opt-in vigente y sin baja |
| `ChannelPicker` | un solo canal por persona (el de menor preferencia entre los activos con dato de contacto) |
| `TracksDelivery` | la notificación dice a qué invitación pertenece, para seguir su entrega |
| `InvitationLinks` | cada módulo entrega el enlace y los parámetros de su plantilla |
| `DeliverySummary` | agregados por canal de una campaña o encuesta (mínimo de 5) |
| `DeliveryOutcomes` | cuáles mensajes no se entregaron, para la conciliación de Billing |

Los contratos se agregan en el commit que los usa; el diseño fija sus firmas.

## Rutas (`routes/messaging.php`)

`GET|POST /webhooks/whatsapp` y `POST /webhooks/resend`. El proveedor no tiene cuenta: la **firma del cuerpo crudo** es la
credencial (se verifica antes de procesar nada). Sin sesión ni cookies, con throttle por dirección (`messaging-webhooks`) y
tamaño máximo (`LimitWebhookBody`).

## Privacidad (invariante)

- Se guarda el flujo **completo** de cada mensaje con su hora exacta (`messaging_deliveries`, `messaging_delivery_events`, de solo
  inserción), pero RH y la plataforma ven **solo agregados** por canal, con el mínimo de 5. Ninguna pantalla ni prop de Inertia
  entrega filas por persona.
- El teléfono nunca se guarda en estas tablas: solo su HMAC (`recipient_hash`, con claves versionadas).
- Ninguna tabla de respuestas tiene llave, canal, id de mensaje ni teléfono hacia estas tablas. El canal vive solo en
  `invited_via` de la invitación.
- Nada del payload de Meta ni de Resend llega a logs.

## Orden de validación

1. Local: gateway falso y `Http::fake()`. 2. Número de prueba de Meta. 3. Número real. `WHATSAPP_ENABLED=false` por omisión.
