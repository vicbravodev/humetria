# Plan editorial del blog

Versión 0.1 · 17 de noviembre de 2026 · Estado: **propuesta, pendiente de visto bueno**.

El blog existe para que una persona de RH que busca «¿qué guía de la NOM-035 me toca?» o «¿es obligatorio el reloj
checador en 2027?» encuentre una respuesta correcta, con su fuente oficial, y llegue a una herramienta de Humetría.
Cada artículo se escribe a partir de [`docs/normas/`](../normas/README.md), nunca de blogs ni de notas de prensa
(regla de esos documentos). Si una cifra no está en el DOF o en la LFT, no se publica.

## Qué se encontró

- **La jornada es el tema del momento y no está agotado.** La reforma del DOF del 01/05/2026 baja la jornada máxima de
  48 horas (2026) a 46 (2027), 44 (2028), 42 (2029) y 40 (2030), cada 1 de enero. **No hay un paso a 35 horas** en el
  decreto: el destino es 40. El registro electrónico de la jornada (art. 132-XXXIV) está en la LFT desde el 1 de mayo de 2026, con multa de 250 a
  5,000 UMA; el decreto da hasta el 31 de diciembre de 2026 para ajustar procesos (transitorio tercero) y las
  disposiciones de la STPS, que definen a quién aplica y qué se exceptúa, entran en vigor el 1 de enero de 2027
  (transitorio quinto). **No se escribe «obligatorio desde 2027»**: se dice lo que dice cada transitorio. Esas
  disposiciones **seguían sin publicarse al 10 de noviembre de 2026**. Esa incertidumbre es lo que la gente busca.
- **La prensa está llena de cifras que se contradicen** (topes de horas extra de 2028 y 2029; rangos de multas de la
  NOM-035). Un artículo que cita el DOF y dice «esto es lo que dice el texto y esto es lo que falta» se distingue solo.
- **NOM-035, NOM-037 y Ley Silla son evergreen**: búsquedas constantes, y la STPS dirige inspecciones a esos temas.
- **La STPS no ha publicado guías de la NOM-037 ni de la Ley Silla** más allá del DOF; el contenido de despachos no se usa
  como fuente. Quien escriba desde el texto oficial compite con menos ruido.

## Primera tanda: 10 artículos

Orden de publicación = orden de la lista. Los cinco primeros son el grupo de la jornada, que pierde valor después de
enero de 2027; los demás son perennes. Cada uno lleva la fecha de última revisión y, si algo depende de la STPS, una
caja «Lo que falta por publicar».

| # | Artículo (título de trabajo) | Intención de búsqueda | Fuente interna | Herramienta |
|---|---|---|---|---|
| 1 | **Jornada de 40 horas: el calendario completo 2026-2030** | «reducción jornada laboral 40 horas», «jornada 2027 cuántas horas» | `jornada` §2.1, 2.7 | Asistencia |
| 2 | **Registro electrónico de la jornada: qué exige el artículo 132-XXXIV y desde cuándo** | «registro electrónico de jornada obligatorio», «multa registro jornada» | `jornada` §1 | Asistencia |
| 3 | **¿Reloj checador obligatorio en 2027? Qué cuenta como registro electrónico** | «reloj checador obligatorio 2027», «registro de asistencia home office» | `jornada` §1.2, 4 | Asistencia |
| 4 | **Horas extra con la jornada reducida: topes, doble y triple, con ejemplos** | «horas extra 2027 cuánto se pagan», «tope horas extra semanal» | `jornada` §2.3-2.4, 2.8, 2.9 | Asistencia |
| 5 | **Lista de 10 pasos para llegar al 1 de enero de 2027 con el registro de jornada en orden** | «cómo preparar registro de jornada», «checklist reforma 40 horas» | `jornada` completo | Asistencia + demo |
| 6 | **NOM-035: qué guía te toca según tu centro de trabajo (15, 16 a 50, más de 50)** | «NOM-035 qué guía aplicar», «NOM-035 cuántos trabajadores» | `nom-035` A | Evaluaciones NOM-035 |
| 7 | **NOM-035 paso a paso: de la política al informe, y qué evidencia guardar** | «cómo cumplir NOM-035», «evidencias NOM-035 inspección» | `nom-035` | NOM-035 + Intervención |
| 8 | **NOM-037 teletrabajo: obligaciones de la empresa y la lista de verificación** | «NOM-037 obligaciones patrón», «política de teletrabajo» | `nom-037` | Teletrabajo |
| 9 | **Ley Silla: cuántos asientos hay que tener y cómo demostrarlo** | «ley silla obligaciones», «ley silla inspección» | `ley-silla` C-01 a C-03 | Ley Silla |
| 10 | **Vacaciones dignas: la tabla vigente y cómo llevar los saldos sin errores** | «tabla vacaciones 2026», «vacaciones dignas cuántos días» | `vacaciones` | Vacaciones |

Segunda tanda (después de medir la primera): clima laboral anónimo y por qué el mínimo de 5 respondentes; canal de
denuncias y el numeral de la NOM-035 sobre quejas; costo de la reducción de jornada en un contrato colectivo.

## Reglas de cada artículo

1. Todo dato normativo cita su fuente oficial con enlace al DOF o a la LFT. Lo que es interpretación se dice «interpretación».
2. Una sola intención de búsqueda por artículo. Título de 60 caracteres o menos, descripción de 120 a 160.
3. Al final, un enlace interno a la herramienta que resuelve el problema y a la demo. Sin promesas de «cumplimiento
   garantizado»: Humetría ayuda a documentar, no sustituye a un abogado.
4. Se firma con `reviewed_by` y `reviewed_at` solo cuando el dueño lo revisó. Sin esa fecha no se publica.
5. Los artículos de jornada se revisan cuando la STPS publique sus disposiciones, y cambian `updated_at`.

## Lo que no se afirma hoy

- Quién queda exceptuado del registro electrónico (lo define la STPS).
- Los topes de horas extra de 2028 y 2029: el decreto es la fuente (`jornada` §2.3), no la prensa.
- Montos en pesos de multas: se dicen en UMA, que es lo que dice la ley.
