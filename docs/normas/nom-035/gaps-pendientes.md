# NOM-035: gaps pendientes

Lo que la norma pide y Humetría todavía no gestiona, más los hallazgos de usabilidad de la revisión del 2026-11-02.
Sale de correr `Nom035EvaluationSeeder` (ver abajo) y revisar las pantallas contra el DOF del 23/10/2018.
Las Guías I, II y III, su calificación, la muestra, la privacidad y el informe 7.7 **sí** cumplen: lo verifican
`tests/Feature/Seeders/Nom035EvaluationSeederTest.php` y las pruebas citadas en [`criterios.md`](criterios.md).

Cada gap es **un trabajo propio** (un PR, según `CLAUDE.md`) y empieza con diseño: es funcionalidad nueva. Al cerrar
uno, se actualiza «Lo que la plataforma no cubre» de `criterios.md` y se borra de aquí.

## Cómo ver el estado actual

```bash
sail artisan migrate:fresh --seed --seeder=Nom035EvaluationSeeder
```

Crea `demo-nom035` (`owner@`, `hr@` y `viewer@demo-nom035.test`) con cinco centros: 15, 16, 30, 51 y 100 personas, con
campañas en borrador, activas y cerradas. No manda correos; imprime los enlaces abiertos de las campañas activas.

## Orden propuesto

| # | Trabajo | Numerales | Tamaño |
|---|---|---|---|
| 1 | Registro de canalización | 5.5, 5.6, 5.8 c | S–M |
| 2 | Programa de intervención | 5.4, 8.3, 8.4, 8.5; inciso g del 7.7 | L |
| 3 | Política de prevención, difusión y portal de la persona trabajadora | 5.1, 5.7, 7.8 | M–L |
| 4 | Canal de quejas y denuncias | 8.1 b, 6.4 | L, necesita diseño de privacidad |
| 5 | Retoques de usabilidad | — | S |

El 1 va primero por pequeño. El 2 es el que más se nota: hoy un cliente que cierra una evaluación con riesgo alto
llega a «se requiere un Programa» y no tiene dónde seguir. Si el segmento de centros de hasta 15 personas pesa en el
producto, el 3 y el 4 suben: esos centros deben cumplir 5.1, 5.4, 5.5, 5.7, 8.1 y 8.2, y hoy solo cubrimos el 5.5.

## 1. Registro de canalización (5.5, 5.6, 5.8 c)

**Hoy.** La lista de canalización de la Guía I dice quién requiere valoración clínica (C-28) y solo la ven owner y RH.
No guarda que la persona se canalizó.

**Falta.** Por persona: estado (pendiente, canalizada, atendida), fecha e institución. Con bitácora (`RecordAudit`),
solo owner y RH, sin nada de esto en logs. Registrar los nombres de quienes recibieron valoración (5.8) ya lo da la
lista; el registro agrega la evidencia de que se hizo.

## 2. Programa de intervención (5.4, 8.3, 8.4, 8.5)

**Hoy.** El diagnóstico termina en «se requiere un Programa» según el nivel de riesgo (C-15). No hay programa.

**Falta.** Un programa que se crea desde una evaluación cerrada, con una fila por dominio o categoría en riesgo medio o
más:

- áreas o personas sujetas, tipo de acción y nivel (1, 2 o 3, numeral 8.5);
- fecha, responsable, avance y evaluación posterior;
- recordatorios por correo (el patrón de los resúmenes existentes);
- alimenta el inciso g del informe 7.7 (acciones de intervención), que hoy no tiene de dónde leer.

## 3. Política, difusión y portal de la persona trabajadora (5.1, 5.7, 7.8)

**Hoy.** La consulta de resultados (7.8) se cumple con el informe impreso o publicado por la empresa; el enlace del
informe (C-29) dura de 15 min a 48 h. No hay política de prevención ni difusión.

**Falta.** Plantilla de política basada en el ejemplo de la Guía IV, con versiones y publicación; difusión con acuse; y
una página permanente para las personas trabajadoras con política, medidas, mecanismos y resultados agregados (siempre
con el mínimo por grupo, C-23).

**Revisar antes de diseñar.** El módulo Telework ya tiene avisos con acuse y un portal por enlace. Los módulos no
pueden importarse entre sí: o se sube a un contrato público, o se duplica con cuidado.

## 4. Canal de quejas y denuncias (8.1 b, 6.4)

**Hoy.** Nada.

**Falta.** Enlace o QR por empresa, con opción anónima, categorías y seguimiento de cada caso con acceso restringido.
El escrito de la persona sobre un acontecimiento (6.5) cabe aquí como campo opcional, no como flujo aparte.

**Decidir en el diseño.** Quién recibe las quejas, cómo se garantiza la confidencialidad y si el anonimato es total.
Debe respetar el invariante de privacidad: nada que ligue a una persona con una respuesta de Guía II o III.

## 5. Retoques de usabilidad

Hallazgos de la revisión con el seeder (escritorio y celular):

- **«Contestó» tiene dos definiciones en el avance.** El medidor cuenta 26 de 51 (quien terminó la Guía II/III), pero
  las pestañas por estado y la participación por área cuentan otra cosa (por ejemplo «0 de 13»). Debe haber una sola
  definición, con el mismo número en todos lados.
- **«40 %» se parte en dos líneas.** Falta un espacio duro entre el número y el signo.
- **El submenú del informe se parte en tres filas a ~800 px.**
- **Guía III en celular: dos toques por pregunta** en los 72 reactivos. Evaluar si una respuesta toca y avanza.
- **Datos de ejemplo que se ven como errores:** «2 al 2 nov» en el rango de fechas y un giro fijo («Manufactura de
  autopartes») en el borrador. Revisar si la pantalla depende de datos que el cliente no tiene.
- **Un «3» sin etiqueta** en el bloque de contacto del borrador.

**No revisado:** modo claro, la Guía III completa en celular, tests de navegador y cobertura sobre la pantalla del
seeder.

## Fuera de alcance, por ahora

- **Integración al diagnóstico de la NOM-030 (7.6).** Depende de otra norma y no tiene demanda clara.
- **Unidades de verificación (numeral 9).** Son opcionales para el patrón; basta con que la evidencia sea exportable.
- **Exámenes médicos y evaluaciones psicológicas (5.6).** Los hace un tercero; el registro de canalización (1) deja la
  constancia.
