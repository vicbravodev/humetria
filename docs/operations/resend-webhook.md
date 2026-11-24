# Webhook de Resend y seguimiento del correo

El correo de producción sale por **la API de Resend** (`MAIL_MAILER=resend`, `RESEND_API_KEY`), no por SMTP: por SMTP Resend
reemplaza el Message-ID y descarta los encabezados propios, así que ningún evento regresa con algo nuestro (verificado el
2026-11-18 con un correo de prueba). Por la API, la etiqueta (`tags`) sí regresa en cada evento. El transporte es propio
(`ResendApiTransport`, sin paquete) y reemplaza al `resend` de Laravel, que necesita `resend/resend-php`.

Cada correo de una notificación que implementa `TracksDelivery` tiene su fila en `messaging_deliveries` antes de salir, y sale con
la etiqueta `delivery_id` = el id de esa fila. El webhook la lee para saber a qué mensaje se refiere el evento.

## Alta del endpoint

1. En Resend, *Webhooks* → *Add Webhook*, URL `https://<dominio>/webhooks/resend`.
2. Eventos: `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`, `email.complained`.
3. Copia el *Signing Secret* (`whsec_…`) a `RESEND_WEBHOOK_SECRET` en Dokploy.

## Qué hace con cada evento

| Evento | Estado del mensaje |
| --- | --- |
| `email.sent` | `sent` |
| `email.delivered` | `delivered` |
| `email.delivery_delayed` | `delayed` |
| `email.bounced`, tipo `Temporary` o `Transient` | `delayed` |
| `email.bounced`, cualquier otro tipo o sin tipo | `bounced` |
| `email.complained` | `complained` |
| cualquier otro, o un mensaje que no es nuestro | se ignora (200) |

Un rebote nunca dispara nada por WhatsApp. La firma (Svix) se verifica sobre el cuerpo crudo antes de leer nada: sin firma válida
o con una marca de tiempo a más de 5 minutos, 403; JSON inválido, 400 y el log `mail.webhook_malformed`. El mismo `svix-id` se
procesa una sola vez.

## Rotar el secreto

1. En Resend, genera el secreto nuevo.
2. Pon el actual en `RESEND_WEBHOOK_SECRET_PREVIOUS` y el nuevo en `RESEND_WEBHOOK_SECRET`; despliega. Se aceptan los dos.
3. Cuando Resend ya firme solo con el nuevo, quita `RESEND_WEBHOOK_SECRET_PREVIOUS`.
