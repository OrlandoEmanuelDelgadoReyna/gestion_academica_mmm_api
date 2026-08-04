# Informe de reconciliación del dominio

## Fuente de verdad

El diccionario de datos canónico define el contrato funcional. Las migraciones existentes ya representaban ese contrato; la reconciliación corrigió los modelos Eloquent que todavía reflejaban una versión anterior del dominio.

## Inconsistencias corregidas

| Área | Inconsistencia | Decisión aplicada |
|---|---|---|
| Calificaciones | El modelo guardaba puntajes por criterio inexistentes. | Se alineó a `promedio_tareas`, `nota_examen_final`, `nota_final`, `estado` y `calculado_at`. |
| Criterios | Se usaban `ponderacion` y `activo`. | Se reemplazó por `codigo`, `porcentaje` y las restricciones reales. |
| Examen final | Usaba duración y fechas inexistentes. | Se alineó a `inicio_at`, `fin_at` y `nota_minima_aprobatoria`. |
| Tareas y entregas | Nombres de archivo, fechas y estados no persistidos. | Se alinearon a `publicado_at`, `fecha_limite_at`, `ruta_archivo`, `entregado_at`, `nota` y `calificado_at`. |
| Materiales | Se asociaban a lecciones. | Se asociaron a programaciones académicas, como define la FK. |
| Certificados | Usaba matrícula y revocación por usuario inexistentes. | Se alineó a programación opcional, reemplazo, firmas, autorización y vigencia. |
| Cultos | Usaba título y fechas inexistentes. | Se alineó a `inicio_at`, `fin_at`, lugar y creador. |
| Bloques y participaciones | La participación dependía directamente de culto. | Se restauró la relación `Culto -> Bloque -> Participación`. |
| Comunicación | Anuncios, eventos y notificaciones usaban nombres de atributos previos. | Se alinearon a los atributos persistidos `publicado_at`, `vence_at`, `inicio_at`, `fin_at`, `contenido`, `tipo` y `enviado_at`. |
| Sesiones e intentos | Usaban fechas y campos no existentes. | Se alinearon a `orden`, `inicio_at`, `fin_at` y a la tabla puente sin atributos adicionales. |

## Archivos modificados

Modelos de evaluación, escuela bíblica, materiales, certificados, cultos, bloques, participaciones, eventos, anuncios y notificaciones bajo `app/Models`.

## Validación realizada

- Laravel Pint ejecutado sobre aplicación y migraciones.
- Sintaxis PHP validada en todos los modelos.
- `php artisan migrate:fresh --seed --force` ejecutado correctamente.
- El comando `db:show --counts` no completó por ausencia de `ext-intl` en el runtime local; la migración y el seeding no dependen de esa extensión.

## Resultado

Los atributos persistentes, casts y relaciones corregidas reflejan la estructura indicada por las migraciones y el diccionario. Las reglas de negocio transversales continúan reservadas para sus servicios transaccionales.
