# Un solo enlace para la persona: plan

Spec: [`../specs/2026-11-14-single-personal-link-design.md`](../specs/2026-11-14-single-personal-link-design.md). TDD: cada fila es
test que falla → código mínimo → verde → un commit.

1. **Quejas en el portal.** `Complaints\Support\ComplaintsPortalSection` (módulo `complaints`, canal disponible → su URL pública).
   Test Feature: aparece/no aparece (módulo apagado, canal sin publicar), no lleva identidad.
2. **Evaluación NOM-035 en el portal.** `Campaigns\Support\SurveyPortalSection`: participante invitado de campaña activa con guía
   pendiente → `route('survey.show', surveyToken())`; no con campaña cerrada, hecha, sin invitar, de otra persona ni de otra empresa.
3. **Clima/pulso en el portal.** `Engagement\Support\SurveyPortalSection`: invitación abierta, sin contestar ni cancelar → `engagement.survey.show`.
4. **Mi jornada.** Migración (`issued_by` nulo = lo emitió la propia persona), `Attendance\Actions\IssueSelfEnrollment`,
   `PortalController` (`portal.attendance.show`, `portal.attendance.enroll`), `routes/attendance-portal.php`, página
   `attendance/portal`, `AttendancePortalSection`, test de navegador, rutas en `RouteSecurityTest`.
5. **Orden del hub.** `Telework\PortalController::ORDER` con las claves nuevas.
6. **Correos.** Pie «Su portal» en la capa de correo y su enlace (`PortalAccess::url()`, opcional) en las invitaciones y recordatorios de las dos encuestas.
7. **Cierre.** `composer ci:check`.
