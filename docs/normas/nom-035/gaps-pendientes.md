# NOM-035: gaps pendientes

Lo que la norma pide y Humetría todavía no gestiona.
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
| 2 | Programa de intervención | 5.4, 8.3, 8.4, 8.5; inciso g del 7.7 | L |
| 3 | Política de prevención, difusión y portal de la persona trabajadora | 5.1, 5.7, 7.8 | M–L |

El 2 es el que más se nota: hoy un cliente que cierra una evaluación con riesgo alto
llega a «se requiere un Programa» y no tiene dónde seguir. Si el segmento de centros de hasta 15 personas pesa en el
producto, el 3 sube: esos centros deben cumplir 5.1, 5.4, 5.5, 5.7, 8.1 y 8.2, y hoy cubrimos el 5.5 y el mecanismo de quejas (8.1 b).

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

## Fuera de alcance, por ahora

- **Integración al diagnóstico de la NOM-030 (7.6).** Depende de otra norma y no tiene demanda clara.
- **Unidades de verificación (numeral 9).** Son opcionales para el patrón; basta con que la evidencia sea exportable.
- **Que la Guía III avance sola al contestar (celular).** Ahorraría un toque por reactivo, pero contradice «la encuesta
  nunca avanza sola» (design system, `CLAUDE.md`): quien contesta debe poder corregir antes de seguir.
- **Exámenes médicos y evaluaciones psicológicas (5.6).** Los hace un tercero; el registro de canalización (C-28 de
  `criterios.md`) ya deja la constancia.
