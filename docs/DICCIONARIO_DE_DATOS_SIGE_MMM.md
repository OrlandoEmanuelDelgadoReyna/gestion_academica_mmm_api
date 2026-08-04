# Diccionario de datos canónico - SIGE-MMM

**Estado:** propuesta de línea base para aprobación antes de generar las migraciones restantes.  
**Motor objetivo:** MySQL 8.  
**Convención:** nombres físicos en `snake_case` y plural; PK = clave primaria; FK = clave foránea.

## 1. Principios de integridad

- Todas las PK usan `bigint unsigned` autoincremental (`id`).
- Las FK usan el mismo tipo que su PK de destino y, salvo una regla indicada, `RESTRICT` en eliminación y actualización.
- `created_at` y `updated_at` se incluyen en entidades maestras y transaccionales. `auditorias` es inmutable: solo usa `created_at`.
- `deleted_at` se usa únicamente en `iglesias`, `miembros` y `usuarios`; catálogos se deshabilitan con `activo` para preservar referencias.
- No se usan relaciones polimórficas sin FK. Un archivo o enlace se guarda como URL/ruta en la entidad que lo utiliza.
- Los estados se almacenan como `varchar` validado por la aplicación cuando no exista un catálogo explícito. No se usan `ENUM` de MySQL para evitar migraciones por cambios de catálogo.
- Las fechas y horas se almacenan en UTC. La zona horaria institucional se aplica en la capa de aplicación.
- Los índices únicos constituyen reglas de integridad, no solo optimizaciones.

## 2. Orden de migración

1. `iglesias`, `miembros`, `usuarios`, `roles`, `permisos`, `usuario_roles`, `rol_permisos`, `auditorias`.
2. `cargos`, `miembro_cargos`, `sociedades`, `miembro_sociedades`, `estados_membresia`, `historial_membresia`.
3. Escuela Bíblica.
4. Cultos, calendario, comunicación y certificados.

`miembros` no guarda una FK ni un indicador booleano del estado de membresía actual: este se obtiene del último registro vigente de `historial_membresia`. Así se evita duplicar la misma verdad en dos tablas. `usuarios.activo` es independiente y controla exclusivamente el acceso digital.

## 3. Seguridad e identidad

### iglesias

| Campo | Tipo | Nulo | Regla / propósito |
|---|---|---:|---|
| id | bigint unsigned | No | PK. |
| codigo | varchar(30) | No | Código institucional; UNIQUE. |
| nombre | varchar(150) | No | Nombre oficial. |
| direccion | varchar(255) | Sí | Dirección institucional. |
| telefono | varchar(30) | Sí | Contacto institucional. |
| correo_electronico | varchar(150) | Sí | Correo institucional. |
| activo | boolean | No | Default `true`; controla operación sin borrar. |
| created_at, updated_at, deleted_at | timestamp | Según columna | Trazabilidad y eliminación lógica. |

Índices: PK `id`; UNIQUE `codigo`; INDEX (`activo`, `deleted_at`).

### miembros

| Campo | Tipo | Nulo | Regla / propósito |
|---|---|---:|---|
| id | bigint unsigned | No | PK. |
| iglesia_id | bigint unsigned | No | FK a `iglesias.id`; identifica la pertenencia institucional. |
| tipo_documento | varchar(30) | No | Tipo de documento validado por aplicación. |
| numero_documento | varchar(30) | No | Identificación de la persona. |
| nombres | varchar(120) | No | Nombres legales o institucionales. |
| apellidos | varchar(120) | No | Apellidos. |
| fecha_nacimiento | date | Sí | Dato personal opcional. |
| sexo | char(1) | Sí | Valores permitidos `M`, `F` u `O`. |
| correo_electronico | varchar(150) | Sí | Contacto, no credencial de acceso. |
| telefono | varchar(30) | Sí | Contacto principal. |
| direccion | varchar(255) | Sí | Dirección principal. |
| created_at, updated_at, deleted_at | timestamp | Según columna | Trazabilidad y eliminación lógica. |

Restricciones: FK `iglesia_id` → `iglesias.id`; UNIQUE (`iglesia_id`, `tipo_documento`, `numero_documento`).  
Índices: INDEX (`iglesia_id`, `apellidos`, `nombres`); INDEX (`iglesia_id`, `deleted_at`).

### usuarios

| Campo | Tipo | Nulo | Regla / propósito |
|---|---|---:|---|
| id | bigint unsigned | No | PK. |
| miembro_id | bigint unsigned | No | FK a `miembros.id`; UNIQUE, un acceso por miembro. |
| nombre_usuario | varchar(60) | No | Identificador de inicio de sesión; UNIQUE. |
| contrasena | varchar(255) | No | Hash de contraseña; nunca texto plano. |
| activo | boolean | No | Default `true`; bloquea el acceso sin borrar historial. |
| ultimo_acceso_at | timestamp | Sí | Último acceso satisfactorio. |
| created_at, updated_at, deleted_at | timestamp | Según columna | Trazabilidad y eliminación lógica. |

Restricciones: FK `miembro_id` → `miembros.id`; UNIQUE `miembro_id`; UNIQUE `nombre_usuario`.  
Índices: INDEX (`activo`, `deleted_at`).

### roles

| Campo | Tipo | Nulo | Regla / propósito |
|---|---|---:|---|
| id | bigint unsigned | No | PK. |
| codigo | varchar(60) | No | Código técnico estable; UNIQUE. |
| nombre | varchar(100) | No | Nombre visible: Administrador, Pastor, Secretaria, etc. |
| descripcion | varchar(255) | Sí | Alcance funcional. |
| activo | boolean | No | Default `true`. |
| created_at, updated_at | timestamp | No | Trazabilidad. |

Índices: PK `id`; UNIQUE `codigo`; UNIQUE `nombre`; INDEX `activo`.

### permisos

| Campo | Tipo | Nulo | Regla / propósito |
|---|---|---:|---|
| id | bigint unsigned | No | PK. |
| codigo | varchar(100) | No | Acción técnica, por ejemplo `usuarios.crear`; UNIQUE. |
| modulo | varchar(60) | No | Agrupación funcional del permiso. |
| nombre | varchar(120) | No | Nombre legible. |
| descripcion | varchar(255) | Sí | Alcance de la acción. |
| activo | boolean | No | Default `true`. |
| created_at, updated_at | timestamp | No | Trazabilidad. |

Índices: PK `id`; UNIQUE `codigo`; INDEX (`modulo`, `activo`).

### usuario_roles

Campos: `id` PK; `usuario_id` FK a `usuarios.id`; `rol_id` FK a `roles.id`; `asignado_por_usuario_id` FK nullable a `usuarios.id`; `asignado_at` timestamp; `created_at`, `updated_at`.  
Restricciones: UNIQUE (`usuario_id`, `rol_id`); las FK usan `RESTRICT`. `asignado_por_usuario_id` solo puede ser nulo durante el seeding institucional inicial.  
Índices: INDEX `usuario_id`; INDEX `rol_id`.

### rol_permisos

Campos: `id` PK; `rol_id` FK a `roles.id`; `permiso_id` FK a `permisos.id`; `asignado_por_usuario_id` FK nullable a `usuarios.id`; `asignado_at` timestamp; `created_at`, `updated_at`.  
Restricciones: UNIQUE (`rol_id`, `permiso_id`); FK con `RESTRICT`. `asignado_por_usuario_id` solo puede ser nulo durante el seeding institucional inicial.  
Índices: INDEX `rol_id`; INDEX `permiso_id`.

### auditorias

| Campo | Tipo | Nulo | Regla / propósito |
|---|---|---:|---|
| id | bigint unsigned | No | PK. |
| usuario_id | bigint unsigned | Sí | FK a `usuarios.id`; nulo solo para procesos técnicos identificados. |
| accion | varchar(80) | No | Evento normalizado: CREATE, UPDATE, LOGIN, etc. |
| tabla_afectada | varchar(100) | No | Tabla auditada. |
| registro_id | bigint unsigned | Sí | Identificador del registro afectado. |
| datos_antes | json | Sí | Estado previo, sin secretos. |
| datos_despues | json | Sí | Estado posterior, sin secretos. |
| direccion_ip | varchar(45) | Sí | IPv4 o IPv6. |
| dispositivo | varchar(255) | Sí | User-agent o identificador de dispositivo. |
| created_at | timestamp | No | Fecha única e inmutable de la acción. |

Restricciones: FK `usuario_id` → `usuarios.id` con `RESTRICT`. No tiene `updated_at`, `deleted_at` ni operaciones de actualización desde la aplicación.  
Índices: INDEX (`tabla_afectada`, `registro_id`); INDEX (`usuario_id`, `created_at`); INDEX `created_at`.

## 4. Organización y membresía

### cargos

Campos: `id` PK; `iglesia_id` FK; `codigo` varchar(60); `nombre` varchar(120); `descripcion` varchar(255) nullable; `activo` boolean default true; timestamps.  
Restricciones e índices: UNIQUE (`iglesia_id`, `codigo`); FK a `iglesias`; INDEX (`iglesia_id`, `activo`).

### miembro_cargos

Campos: `id` PK; `miembro_id` FK; `cargo_id` FK; `fecha_inicio` date; `fecha_fin` date nullable; `activo` boolean default true; `observacion` text nullable; timestamps.  
Restricciones e índices: UNIQUE (`miembro_id`, `cargo_id`, `fecha_inicio`); CHECK `fecha_fin IS NULL OR fecha_fin >= fecha_inicio`; INDEX (`miembro_id`, `activo`); FK con `RESTRICT`.

### sociedades

Campos: `id` PK; `iglesia_id` FK; `codigo` varchar(60); `nombre` varchar(120); `descripcion` varchar(255) nullable; `activo` boolean default true; timestamps.  
Restricciones e índices: UNIQUE (`iglesia_id`, `codigo`); FK a `iglesias`; INDEX (`iglesia_id`, `activo`).

### miembro_sociedades

Campos: `id` PK; `miembro_id` FK; `sociedad_id` FK; `fecha_ingreso` date; `fecha_salida` date nullable; `activo` boolean default true; timestamps.  
Restricciones e índices: UNIQUE (`miembro_id`, `sociedad_id`, `fecha_ingreso`); CHECK de rango de fechas; INDEX (`miembro_id`, `activo`); FK con `RESTRICT`.

### estados_membresia

Campos: `id` PK; `codigo` varchar(60) UNIQUE; `nombre` varchar(120); `orden` tinyint unsigned UNIQUE; `activo` boolean default true; timestamps.  
Valores iniciales: Visitante, Probante, Curso de instrucciones bíblicas, Examen, Entrevista pastoral, Bautismo, Activo e Inactivo.

### historial_membresia

Campos: `id` PK; `miembro_id` FK; `estado_membresia_id` FK; `fecha_inicio` date; `fecha_fin` date nullable; `observacion` text nullable; `registrado_por_usuario_id` FK; timestamps.  
Restricciones e índices: INDEX (`miembro_id`, `fecha_inicio`); INDEX (`estado_membresia_id`, `fecha_inicio`); FK con `RESTRICT`; máximo un estado vigente por miembro se garantiza en Service dentro de una transacción.

### transiciones_estado_membresia

Campos: `id` PK; `estado_origen_id` FK a `estados_membresia.id`; `estado_destino_id` FK a `estados_membresia.id`; `requiere_observacion` boolean default false; `activo` boolean default true; timestamps.  
Restricciones e índices: UNIQUE (`estado_origen_id`, `estado_destino_id`); ambas FK usan `RESTRICT`; CHECK lógico `estado_origen_id <> estado_destino_id`. Esta entidad define el flujo institucional y permite explícitamente la reactivación desde Inactivo hacia Curso de instrucciones bíblicas.

## 5. Escuela Bíblica

### aulas

Campos: `id` PK; `iglesia_id` FK; `codigo` varchar(30); `nombre` varchar(100); `capacidad` smallint unsigned nullable; `activo` boolean default true; timestamps.  
Restricciones: UNIQUE (`iglesia_id`, `codigo`); CHECK `capacidad IS NULL OR capacidad > 0`; INDEX (`iglesia_id`, `activo`).

### cursos

Campos: `id` PK; `iglesia_id` FK; `codigo` varchar(60); `nombre` varchar(150); `descripcion` text nullable; `activo` boolean default true; timestamps.  
Restricciones: UNIQUE (`iglesia_id`, `codigo`); INDEX (`iglesia_id`, `activo`).

### programaciones_academicas

Campos: `id` PK; `curso_id` FK; `aula_id` FK nullable; `periodo` varchar(50); `grupo` varchar(60); `fecha_inicio` date; `fecha_fin` date; `capacidad` smallint unsigned; `escala_maxima` decimal(6,2); `nota_minima_aprobatoria` decimal(6,2); `maximo_intentos_examen` tinyint unsigned default 1; `estado` varchar(30); timestamps.  
Restricciones: UNIQUE (`curso_id`, `periodo`, `grupo`); CHECK `fecha_fin >= fecha_inicio`, `capacidad > 0`, `escala_maxima > 0`, `nota_minima_aprobatoria >= 0` y `nota_minima_aprobatoria <= escala_maxima`; INDEX (`curso_id`, `estado`); INDEX (`aula_id`, `fecha_inicio`).

### programacion_estados_membresia_permitidos

Campos: `id` PK; `programacion_academica_id` FK; `estado_membresia_id` FK; timestamps.  
Restricciones e índices: UNIQUE (`programacion_academica_id`, `estado_membresia_id`); FK con `RESTRICT`. Permite definir, por ejemplo, cursos doctrinales disponibles para miembros inactivos en proceso de reactivación sin codificar excepciones por nombre de curso.

### programacion_docentes

Campos: `id` PK; `programacion_academica_id` FK; `miembro_id` FK; `asignado_at` timestamp; timestamps.  
Restricciones: UNIQUE (`programacion_academica_id`, `miembro_id`); INDEX `miembro_id`.

### lecciones

Campos: `id` PK; `curso_id` FK; `orden` smallint unsigned; `nombre` varchar(150); `descripcion` text nullable; `activo` boolean default true; timestamps.  
Restricciones: UNIQUE (`curso_id`, `orden`); INDEX (`curso_id`, `activo`).

### sesiones

Campos: `id` PK; `programacion_academica_id` FK; `orden` smallint unsigned; `inicio_at` datetime; `fin_at` datetime; `tema` varchar(255) nullable; `estado` varchar(30); timestamps.  
Restricciones: UNIQUE (`programacion_academica_id`, `orden`); CHECK `fin_at > inicio_at`; INDEX (`programacion_academica_id`, `inicio_at`).

### sesion_lecciones

Campos: `id` PK; `sesion_id` FK; `leccion_id` FK; timestamps.  
Restricciones: UNIQUE (`sesion_id`, `leccion_id`); ambas FK usan `RESTRICT`. Esta tabla expresa que una lección puede distribuirse en varias sesiones.

### matriculas

Campos: `id` PK; `programacion_academica_id` FK; `miembro_id` FK; `fecha_matricula` datetime; `estado` varchar(30); timestamps.  
Estados permitidos: `pendiente`, `activa`, `retirada`, `finalizada`, `anulada`.  
Restricciones: UNIQUE (`programacion_academica_id`, `miembro_id`); INDEX (`miembro_id`, `estado`); INDEX (`programacion_academica_id`, `estado`). Conflictos de horario, cupo y elegibilidad de membresía se validan en Service.

### asistencias

Campos: `id` PK; `sesion_id` FK; `matricula_id` FK; `estado` varchar(20); `observacion` varchar(255) nullable; `registrado_por_usuario_id` FK; timestamps.  
Restricciones: UNIQUE (`sesion_id`, `matricula_id`); estado permitido: `asistio`, `falto` o `justificado`; `observacion` es obligatoria si el estado es `justificado`; INDEX `matricula_id`.

### tipos_material

Campos: `id` PK; `codigo` varchar(30) UNIQUE; `nombre` varchar(80); `activo` boolean default true; timestamps.  
Valores iniciales: PDF, Video, Documento y Enlace.

### materiales

Campos: `id` PK; `programacion_academica_id` FK; `tipo_material_id` FK; `titulo` varchar(150); `descripcion` text nullable; `ruta_recurso` varchar(2048); `publicado_at` datetime nullable; `creado_por_usuario_id` FK; timestamps.  
Índices: INDEX (`programacion_academica_id`, `publicado_at`); FK con `RESTRICT`.

### tareas

Campos: `id` PK; `programacion_academica_id` FK; `titulo` varchar(150); `descripcion` text nullable; `publicado_at` datetime; `fecha_limite_at` datetime nullable; `puntaje_maximo` decimal(6,2); `creado_por_usuario_id` FK; timestamps.  
Restricciones: CHECK `puntaje_maximo > 0` y fecha límite posterior a publicación; INDEX (`programacion_academica_id`, `fecha_limite_at`).

### entregas_tarea

Campos: `id` PK; `tarea_id` FK; `matricula_id` FK; `contenido` text nullable; `ruta_archivo` varchar(2048) nullable; `entregado_at` datetime; `nota` decimal(6,2) nullable; `retroalimentacion` text nullable; `calificado_at` datetime nullable; `calificado_por_usuario_id` FK nullable; timestamps.  
Restricciones: UNIQUE (`tarea_id`, `matricula_id`); al menos `contenido` o `ruta_archivo` debe existir; INDEX (`matricula_id`, `entregado_at`).

### criterios_evaluacion

Campos: `id` PK; `programacion_academica_id` FK; `codigo` varchar(30); `origen` varchar(30); `nombre` varchar(100); `porcentaje` decimal(5,2); `orden` tinyint unsigned; timestamps.  
Restricciones: UNIQUE (`programacion_academica_id`, `codigo`); UNIQUE (`programacion_academica_id`, `origen`); UNIQUE (`programacion_academica_id`, `orden`); CHECK porcentaje entre 0 y 100. Orígenes permitidos: `tareas` y `examen_final`. El Service exige que la suma sea 100 y normaliza cada tarea contra su `puntaje_maximo`.

### examenes_finales

Campos: `id` PK; `programacion_academica_id` FK; `titulo` varchar(150); `descripcion` text nullable; `inicio_at` datetime nullable; `fin_at` datetime nullable; `puntaje_maximo` decimal(6,2); `nota_minima_aprobatoria` decimal(6,2); `activo` boolean default true; timestamps.  
Restricciones: UNIQUE `programacion_academica_id`; CHECK de rangos de fechas y puntajes.

### preguntas_examen

Campos: `id` PK; `examen_final_id` FK; `orden` smallint unsigned; `tipo` varchar(30); `enunciado` text; `puntaje` decimal(6,2); timestamps.  
Restricciones: UNIQUE (`examen_final_id`, `orden`); tipo permitido `seleccion_unica` o `texto`; CHECK `puntaje > 0`.

### opciones_pregunta

Campos: `id` PK; `pregunta_examen_id` FK; `orden` smallint unsigned; `texto` varchar(500); `es_correcta` boolean default false; timestamps.  
Restricciones: UNIQUE (`pregunta_examen_id`, `orden`). El Service exige una única correcta para preguntas de selección única.

### intentos_examen

Campos: `id` PK; `examen_final_id` FK; `matricula_id` FK; `inicio_at` datetime; `fin_at` datetime nullable; `estado` varchar(30); `puntaje_obtenido` decimal(6,2) nullable; timestamps.  
Índices: INDEX (`matricula_id`, `estado`); INDEX (`examen_final_id`, `inicio_at`). El límite se toma de `programaciones_academicas.maximo_intentos_examen` y se valida en Service.

### respuestas_examen

Campos: `id` PK; `intento_examen_id` FK; `pregunta_examen_id` FK; `opcion_pregunta_id` FK nullable; `respuesta_texto` text nullable; `es_correcta` boolean nullable; `puntaje_obtenido` decimal(6,2) nullable; timestamps.  
Restricciones: UNIQUE (`intento_examen_id`, `pregunta_examen_id`); la coherencia entre tipo de pregunta y respuesta se valida en Service.

### calificaciones

Campos: `id` PK; `matricula_id` FK; `promedio_tareas` decimal(6,2) nullable; `nota_examen_final` decimal(6,2) nullable; `nota_final` decimal(6,2); `estado` varchar(30); `calculado_at` datetime; timestamps.  
Estados permitidos: `pendiente`, `aprobada`, `desaprobada`.  
Restricciones: UNIQUE `matricula_id`; INDEX (`estado`, `calculado_at`). La fórmula usa `criterios_evaluacion`, `escala_maxima` y `nota_minima_aprobatoria`; se ejecuta exclusivamente en Service.

## 6. Certificados, cultos y calendario

### tipos_certificado

Campos: `id` PK; `codigo` varchar(60) UNIQUE; `nombre` varchar(120); `categoria` varchar(30); `activo` boolean default true; timestamps.  
Categorías permitidas: `academico`, `membresia`, `recomendacion`.

No se crea una segunda tabla para cartas de recomendación: se emiten mediante la categoría `recomendacion` de esta entidad. Esta decisión evita duplicar el mismo documento institucional, su firma y su autorización.

### certificados

Campos: `id` PK; `miembro_id` FK; `tipo_certificado_id` FK; `programacion_academica_id` FK nullable; `certificado_reemplazado_id` FK nullable a `certificados.id`; `codigo_verificacion` varchar(80) UNIQUE; `emitido_at` datetime; `estado` varchar(30); `destinatario` varchar(150) nullable; `motivo` text nullable; `vence_at` datetime nullable; `ruta_documento` varchar(2048) nullable; `firmado_por_miembro_id` FK nullable; `firmado_at` datetime nullable; `autorizado_por_miembro_id` FK nullable; `autorizado_at` datetime nullable; `emitido_por_usuario_id` FK; timestamps.  
Estados permitidos: `emitido`, `revocado`, `reemplazado`.  
Índices: INDEX (`miembro_id`, `tipo_certificado_id`); INDEX (`estado`, `emitido_at`); INDEX `certificado_reemplazado_id`. Las condiciones de emisión, firma pastoral, autorización de secretaría y reemisión se validan en Service dentro de una transacción.

### tipos_culto

Campos: `id` PK; `iglesia_id` FK; `codigo` varchar(60); `nombre` varchar(120); `activo` boolean default true; timestamps.  
Restricciones: UNIQUE (`iglesia_id`, `codigo`); valores iniciales: Damas, Caballeros, Juvenil, Escuela Dominical, Evangelístico, Doctrinal, Especial, Confraternidad, Campaña y Aniversario.

### cultos

Campos: `id` PK; `iglesia_id` FK; `tipo_culto_id` FK; `inicio_at` datetime; `fin_at` datetime; `lugar` varchar(150) nullable; `estado` varchar(30); `creado_por_usuario_id` FK; timestamps.  
Restricciones: CHECK `fin_at > inicio_at`; INDEX (`iglesia_id`, `inicio_at`); INDEX (`tipo_culto_id`, `inicio_at`).

### tipos_participacion

Campos: `id` PK; `codigo` varchar(60) UNIQUE; `nombre` varchar(120); `requiere_miembro` boolean default true; `activo` boolean default true; timestamps.  
Valores iniciales: Predicación, Dirección, Lectura, Especial, Coros, Salmo, Testimonio, Oración e Himnos Congregacionales.

### bloques_culto

Campos: `id` PK; `culto_id` FK; `tipo_participacion_id` FK; `orden` smallint unsigned; `contenido` varchar(500) nullable; timestamps.  
Restricciones: UNIQUE (`culto_id`, `orden`); INDEX (`culto_id`, `tipo_participacion_id`). `contenido` guarda, por ejemplo, la lectura bíblica o himnos planificados.

### participaciones_culto

Campos: `id` PK; `bloque_culto_id` FK; `miembro_id` FK; `estado` varchar(30); `observacion` varchar(255) nullable; timestamps.  
Restricciones: UNIQUE (`bloque_culto_id`, `miembro_id`); INDEX (`miembro_id`, `estado`). Conflictos de horario se validan en Service.

### eventos

Campos: `id` PK; `iglesia_id` FK; `titulo` varchar(150); `descripcion` text nullable; `inicio_at` datetime; `fin_at` datetime; `lugar` varchar(150) nullable; `estado` varchar(30); `creado_por_usuario_id` FK; timestamps.  
Restricciones: CHECK `fin_at > inicio_at`; INDEX (`iglesia_id`, `inicio_at`). El calendario institucional es una vista de `sesiones`, `cultos` y `eventos`; no tiene tabla propia.

## 7. Comunicación

### anuncios

Campos: `id` PK; `iglesia_id` FK; `titulo` varchar(150); `contenido` text; `estado` varchar(30); `publicado_at` datetime nullable; `vence_at` datetime nullable; `creado_por_usuario_id` FK; timestamps.  
Restricciones: CHECK de rango de publicación; INDEX (`iglesia_id`, `estado`, `publicado_at`).

### notificaciones

Campos: `id` PK; `iglesia_id` FK; `titulo` varchar(150); `contenido` text; `tipo` varchar(30); `enviado_at` datetime nullable; `creado_por_usuario_id` FK; timestamps.  
Índices: INDEX (`iglesia_id`, `enviado_at`); INDEX `tipo`.

### notificacion_destinatarios

Campos: `id` PK; `notificacion_id` FK; `usuario_id` FK; `estado` varchar(30); `entregado_at` datetime nullable; `leido_at` datetime nullable; timestamps.  
Restricciones: UNIQUE (`notificacion_id`, `usuario_id`); INDEX (`usuario_id`, `estado`, `leido_at`).

### dispositivos_notificacion

Campos: `id` PK; `usuario_id` FK; `token_push` varchar(512); `plataforma` varchar(30); `nombre_dispositivo` varchar(120) nullable; `activo` boolean default true; `ultimo_uso_at` datetime nullable; timestamps.  
Restricciones: UNIQUE `token_push`; INDEX (`usuario_id`, `activo`).

## 8. Matriz de relaciones obligatorias

- Iglesia 1:N Miembro, Cargo, Sociedad, Aula, Curso, Tipo de Culto, Culto, Evento, Anuncio y Notificación.
- Miembro 1:0..1 Usuario; 1:N Historial de Membresía, Matrícula, Cargo asignado, Sociedad asignada y Participación de culto.
- Usuario N:M Rol mediante `usuario_roles`; Rol N:M Permiso mediante `rol_permisos`.
- Curso 1:N Lección y Programación Académica; Programación 1:N Sesión, Material, Tarea, Matrícula, Criterio y un Examen Final.
- Sesión N:M Lección mediante `sesion_lecciones`; Matrícula 1:N Asistencia, Entrega e Intento de Examen.
- Culto 1:N Bloque; Bloque 1:N Participación; cada participación pertenece a un miembro.
- Certificado pertenece a un miembro y tipo; puede relacionarse opcionalmente con una programación académica.

## 9. Reglas transversales que no sustituyen una FK

1. Un miembro con estado de membresía Inactivo puede iniciar sesión únicamente si su cuenta de usuario está activa; el estado de membresía regula elegibilidad institucional y académica, no la autenticación.
2. No se puede matricular a un miembro si la programación no tiene cupo, existe cruce de horarios o su estado no aparece en `programacion_estados_membresia_permitidos`.
3. Un docente o participante no puede tener actividades superpuestas cuando estas requieran su presencia.
4. Una asistencia solo puede relacionar una sesión con una matrícula de la misma programación; esta regla se valida en Service.
5. Una lección asignada a una sesión debe pertenecer al curso de la programación de esa sesión; se valida en Service.
6. Solo existe un historial de membresía vigente por miembro; una transición debe existir y estar activa en `transiciones_estado_membresia`, cierra el historial anterior y crea el nuevo dentro de la misma transacción.
7. Las sumas de `criterios_evaluacion` deben ser 100 y la nota final solo se recalcula mediante el Service académico.
8. Los registros de `auditorias` son de solo inserción y nunca se eliminan desde la aplicación.
9. Un certificado solo puede marcarse como firmado o autorizado si el miembro responsable tiene el cargo ministerial vigente que la política institucional exige (Pastor o Secretaria).
