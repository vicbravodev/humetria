# Un solo enlace para la persona: el portal como entrada única

Fecha: 2026-11-14 · Estado: **aprobado** (el usuario aceptó las cinco recomendaciones y pidió resolverlo todo)

## Problema

La gente que no tiene cuenta (sin login) recibe un enlace distinto por herramienta: el portal (`portal/{token}`), la
encuesta NOM-035 (`survey/{token}`), la de clima o pulso (`engagement/{token}`), el alta de su teléfono para checar
(`a/{slug}/enroll/{token}`, 48 h, un solo uso) y el canal de quejas (`/c/{code}`). Nadie guarda cinco enlaces, y el
seguimiento se pierde.

## Decisión

El **portal personal ya existente es la entrada única**. Su enlace es permanente por persona (RH solo puede
reemplazarlo; nunca lo recibe) y lista, por módulo, lo que la persona tiene habilitado (`PortalSection`, etiqueta
`portal.sections`). Lo que faltaba se agrega como secciones nuevas, cada una de su módulo:

| Sección | Módulo | A dónde lleva |
|---|---|---|
| Mi jornada (`attendance`) | Attendance | `portal/{token}/attendance`: marcar, Mi semana y vincular el teléfono |
| Evaluación NOM-035 (`survey`) | Campaigns | el enlace de su invitación, mientras la campaña esté abierta y le falte una guía |
| Encuesta de clima o pulso (`engagement`) | Engagement | el enlace de su invitación, mientras la encuesta esté abierta y no la haya contestado |
| Quejas y denuncias (`complaints`) | Complaints | el canal público de la empresa, sin pasarle ninguna identidad |

Los **resultados** que ve la persona (num. 7.8) ya están en el portal: «Avisos y resultados de su centro» (`center`) y
«Nos dijeron / Haremos» (`climate`). No se construye nada nuevo.

Además, el correo de cada invitación y recordatorio de encuesta (NOM-035 y clima o pulso) lleva al pie «Todo lo suyo, en
un solo lugar: Su portal». Es un detalle opcional: si el enlace no se puede emitir (`PortalAccess::url()` devuelve null y lo
registra), el correo sale igual sin el pie. El correo del alta del teléfono no lo lleva: emitir ese enlace ya escribe su
propio log y el portal mismo es ahora el camino para vincular un teléfono.

## Passkey

La passkey sigue siendo lo que demuestra quién marca (criterio 1.3 y anti-metas del registro de jornada); el enlace del
portal **no** marca. Vincular un teléfono desde el portal no salta la regla de «RH no puede vincular un dispositivo como la
persona»: el portal solo emite, a quien tiene su enlace (que RH nunca recibe), el mismo enlace de alta de 48 h y un uso
(`EnrollmentLink`) y la manda a esa página. `EnrollDevice` no cambia; cada dispositivo vinculado sigue avisándose por
correo a la persona (`DeviceLinked`) y RH puede revocarlo.

Marcar y Mi semana siguen firmándose con la passkey en `a/{slug}`; el QR de la pantalla del centro sigue llevando directo a marcar.

## Privacidad (invariante)

- **Encuestas:** la invitación ya conoce a la persona; las respuestas no tienen llave hacia ella. Mostrar «tiene una encuesta
  pendiente» con su propio enlace no liga nada nuevo. El enlace se deriva del id de la invitación con la llave de la
  aplicación (como en el correo): no se guarda ni se loguea.
- **Quejas:** el portal solo enlaza a la dirección pública del canal. Nunca guarda ni muestra la referencia ni la clave de
  un seguimiento (criterio C-46).
- Ninguna sección escribe en logs el token ni el enlace de la encuesta.

## Fuera de alcance

- Revocar dispositivos desde el portal (lo hace RH).
- Cambiar los enlaces viejos: siguen funcionando.
- Pantalla de instalación (PWA) del portal.
