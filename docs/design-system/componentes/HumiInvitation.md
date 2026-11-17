Globo con el que la burbuja cerrada de Humi invita a preguntarle algo real de la página
(`components/assistant/humi-invitation.tsx`; lienzo «La burbuja cerrada invita» e «Invitaciones: mensajes y reglas»).

**Recibe:** `message`, `assistantHref` y las respuestas `onAccept`, `onNotNow`, `onNeverAgain`, `onClose`.

- **Anatomía:** tarjeta de 320 px (`bg-card`, borde, radio 10, `shadow-float`) a la izquierda de la burbuja con su flecha;
  el mensaje, «Preguntarle a Humi» (primario) y «Ahora no» (fantasma), el enlace «No volver a sugerir», la X y abajo una
  barra de 3 px que cuenta 8 s. En el teléfono cabe en `100vw − 92px` y sus controles miden 44 px.
- **Mensaje:** una pregunta de menos de 90 caracteres con un dato real de la página, tuteo, sin exclamaciones ni emoji.
  Sale del dato `invite.{página}` de `tenant.assistant.facts`; sin dato no hay globo (nunca un «¿Necesitas ayuda?»).
  Páginas: Campañas, Resultados de una evaluación, Centros de trabajo y Programas de intervención.
- **Cuándo:** con la burbuja cerrada y sin diálogo ni recorrido abiertos; tras 60 s en la página, luego a lo más una vez
  cada 10 min, 2 por sesión y 1 al día en la misma página (`lib/assistant/invitation-rules.ts`, con su Vitest).
- **Cómo se va:** sola al terminar la barra (el cursor o el foco la pausan), con Esc o la X. «Ahora no» dos veces seguidas
  apaga las sugerencias 7 días; «No volver a sugerir» las apaga y dice dónde se reactivan: «Sugerencias de la burbuja»,
  al pie del historial en la página de Humi. Si la persona abre a Humi, no vuelve a invitar en esa sesión.
- **Preguntarle a Humi:** abre el panel con la pregunta de la página escrita en la caja, sin mandarla.
- **Accesible:** se anuncia en un `role="status"` que siempre está (cortés), no roba el foco ni suena; con movimiento
  reducido solo aparece (la barra sigue contando, porque es la que la cierra).
- Lo descartado vive en el navegador (`localStorage` por persona y empresa; la cuenta de la sesión en `sessionStorage`),
  no en el servidor. No se mide (PostHog solo mide la landing).
