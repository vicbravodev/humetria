# Criterios de roles y permisos de la empresa en Humetría

Versión 0.1 (borrador) · 24 de noviembre de 2026 · Base: diseño de permisos granulares
(`docs/superpowers/specs/2026-11-23-granular-permissions-design.md`).

Este documento explica **cómo reparte Humetría el acceso al panel de una empresa**: quién puede entrar a qué y quién lo
decide. No es una norma de la ley: cada criterio es de tipo **Humetría** (así funciona la plataforma). Humi responde con él
cuando alguien pregunta qué puede hacer o cómo dar acceso a otra persona.

> **Estado: borrador.** Varias decisiones están pendientes de Victor (ver el final del diseño). Si el código y este
> documento no coinciden, se corrige uno de los dos en el mismo cambio.

## 1. Quién administra el acceso

### C-01. Solo el dueño administra roles y permisos · Humetría

El dueño de la cuenta (owner) es la única persona que crea, edita y borra roles, y que da o quita permisos a las personas.
Ese poder no se delega: ningún rol ni permiso adicional lo da. Quien entra como soporte a la cuenta del dueño puede ver la
pantalla de «Roles y permisos», pero no cambia nada.

### C-02. Lo que queda fuera del panel de roles · Humetría

No se reparten desde «Roles y permisos»: ser dueño de la cuenta (hay uno por empresa y se cambia aparte), la facturación,
el plan, los ajustes de privacidad (como el mínimo de respuestas por grupo) y el inicio de sesión único. Tampoco lo que
depende del plan: si la empresa no tiene contratada una herramienta, sus permisos aparecen apagados con la razón y no dan
acceso aunque estén guardados; vuelven a valer si la herramienta vuelve al plan.

## 2. Roles

### C-03. Las plantillas de Humetría son fijas y se copian · Humetría

Humetría trae roles plantilla (por ejemplo RH, Solo consulta, Comisión de seguridad e higiene y Atención de quejas). No se
editan: para ajustar uno, se copia como rol propio y se cambia la copia. Lo que Humetría agregue después a una plantilla no
llega a las copias ya hechas.

### C-04. Roles propios, hasta 20 por empresa · Humetría

El dueño puede crear roles propios con el nombre que quiera, en blanco o a partir de una plantilla, hasta **20 por
empresa**. Al llegar a 20, para crear otro hay que borrar uno que no se use. Un rol que tiene personas o invitaciones sin
aceptar (aunque hayan vencido) no se puede borrar: primero se pasan esas personas a otro rol o se cancelan las invitaciones.

### C-05. Un nivel de acceso por área · Humetría

Un rol se arma eligiendo, para cada área del panel (Panel; Centros de trabajo y empleados; Acceso de trabajadores; Humi;
NOM-035; NOM-037; Ley Silla; Canal de quejas; Clima laboral; Pulso; Asistencia; Vacaciones; Organigrama; Compensación), **un
solo nivel**: «Sin acceso», «Ver» o «Administrar» (en el canal de quejas, «Atender» o «Administrar»; en Humi, «Usar»).
Cada nivel incluye lo del anterior. Algunas áreas tienen además permisos aparte, que se marcan uno por uno: ver los
tamizajes de la Guía I (NOM-035), revisar espacios de teletrabajo (NOM-037) y evaluar el riesgo de los puestos (Ley Silla).

### C-06. Una persona tiene un solo rol · Humetría

Cada persona tiene un rol en cada empresa, nunca varios a la vez. Si necesita algo más que su rol, se le dan permisos
adicionales (C-07); si necesita menos, se le asigna otro rol.

## 3. Permisos adicionales por persona

### C-07. Los permisos adicionales solo suman · Humetría

El dueño puede darle a una persona permisos además de los de su rol. Lo que la persona puede hacer es lo de su rol más sus
permisos adicionales; un permiso adicional nunca le quita algo que su rol sí trae. Los permisos de una empresa valen solo en
esa empresa: quien trabaja para dos tiene en cada una lo que esa le dio.

### C-08. Las invitaciones llevan rol y permisos · Humetría

Al invitar a alguien, el dueño elige su rol (plantilla o propio) y, si quiere, permisos adicionales. La persona entra con
eso al aceptar; si para entonces la empresa ya no tiene alguna herramienta, ese permiso no le da acceso.

## 4. Permisos sensibles y bitácora

### C-09. Los permisos sensibles piden confirmación y quedan en la bitácora · Humetría

Algunos permisos dan acceso a información delicada: ver los tamizajes de la Guía I (lista identificada de personas a
canalizar), atender o administrar el canal de quejas, revisar espacios de teletrabajo y ver o administrar compensación.
Darlos, en un rol o a una persona, pide una confirmación explícita. Crear, editar o borrar un rol, cambiar el rol de alguien
y darle o quitarle permisos quedan en la bitácora, marcando cuándo el cambio toca un permiso sensible. Ningún permiso abre
las respuestas anónimas de las encuestas: los resultados siempre son agregados y respetan el mínimo por grupo.

### C-10. Conflicto de interés en el canal de quejas · Humetría

Quien administra la organización (el nivel «Administrar» de Centros de trabajo y empleados) cuenta como RH, y el dueño
cuenta como dirección. Al recibir ese acceso, la persona deja de atender las quejas presentadas contra RH o contra la
dirección, respectivamente (criterio C-45 de NOM-035). *Esta equivalencia está pendiente de confirmar por Victor.*

## 5. Adónde llega cada persona

### C-11. Cada quien entra a lo primero que puede ver · Humetría

Al entrar, la persona llega a la primera herramienta que su acceso le permite ver. Si no tiene acceso a ninguna, ve una
página que se lo dice y le indica que lo pida al dueño de la cuenta. El menú solo muestra lo que la persona puede abrir.

### C-12. Qué dice Humi sobre el acceso · Humetría

Humi le dice a cada persona qué puede hacer ella, área por área. Al dueño le dice además cuántos roles propios tiene la
empresa (de 20), cuántas personas tienen cada rol y cuántas tienen permisos adicionales: siempre conteos, nunca quién tiene
qué. De cada área (NOM-035 y sus resultados, NOM-037, Ley Silla, vacaciones, clima, pulso, asistencia, organigrama) Humi
solo le cuenta algo a quien la ve en el panel: a quien no, le dice que su rol no la incluye y que pida acceso.
