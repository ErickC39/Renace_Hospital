# Contratos entre el aplicativo y la base de datos

> Este documento es el acuerdo entre quien programa la BD y el aplicativo PHP.
> El aplicativo llama **exactamente** a estos nombres con estos parámetros; la BD los implementa así.
> Si algo cambia, se cambia **primero aquí**, luego en la BD y luego en el aplicativo.
>

## Usuarios de Oracle (VM `HOSPDB`, 21c XE)

| Usuario | Para qué |
|---|---|
| `HOSPITAL` | **Dueño** de todas las tablas, paquetes y vistas. El equipo corre los scripts sobre este esquema |
| `APP_HOSPITAL` | El aplicativo PHP. No es dueño de nada. Al conectarse trabaja sobre `HOSPITAL` (`CURRENT_SCHEMA`) |
| Usuarios del equipo | Desarrollo y consultas sobre el mismo sistema |

**Permisos que `APP_HOSPITAL` necesita (los da `HOSPITAL` o el DBA), y nada más:**

```sql
GRANT EXECUTE ON HOSPITAL.pkg_seguridad TO APP_HOSPITAL;
GRANT SELECT  ON HOSPITAL.vw_seguridad_usuario_sesion TO APP_HOSPITAL;
GRANT SELECT  ON HOSPITAL.vw_seguridad_permisos_rol   TO APP_HOSPITAL;
-- y lo mismo con cada pkg_ y vw_ nuevo de los demás módulos
```

Nunca `GRANT` sobre tablas: el aplicativo solo ejecuta paquetes y lee vistas (SPEC, supuesto 3).

## Cómo leer cada contrato

- **IN / OUT:** parámetros de entrada y salida del procedimiento. Todos empiezan con `p_`.
- **Errores:** códigos de `RAISE_APPLICATION_ERROR`. El mensaje se le muestra **tal cual al usuario**, así que debe estar en español claro y no puede incluir datos internos.
- **Vistas:** el aplicativo solo lee de ellas, con `SELECT` de columnas explícitas.
- **Cambios a la BD:** el aplicativo nunca hace INSERT, UPDATE ni DELETE directos (SPEC, supuesto 3).

---

## Seguridad (req. 10) · `pkg_seguridad` · errores -20001 a -20099

Responsable en la BD: **Erick**.

### Columnas que el aplicativo espera

| Tabla | Columnas que usa el aplicativo |
|---|---|
| `lista_rol` | `id_rol`, `nombre` (administrador, recepcion, medico, enfermeria, cajero, auditor) |
| `lista_permiso` | `id_permiso`, `codigo` (único, ej. `visita.registrar`), `descripcion` |
| `permiso_por_rol` | `id_rol`, `id_permiso` |
| `usuario` | `id_usuario`, `id_empleado`, `id_rol`, `nombre_usuario` (único), `clave` VARCHAR2(255), `estado` (`ACTIVO`/`INACTIVO`), `fecha_hora_ultimo_acceso` TIMESTAMP |
| `registro_accesos` | `id_registro`, `id_usuario` (dato simple, sin FK, §6.8), `nombre_usuario`, `evento`, `origen`, `fecha_hora` |

> **`lista_permiso.codigo`:** la guía describe el permiso como "sobre qué parte y qué acción". El aplicativo necesita además un **código corto** por permiso, para decidir qué menú y qué pantallas mostrar. La lista de códigos está al final de esta sección.

### Cómo se maneja la clave

Oracle no trae bcrypt, así que **la clave se cifra y se verifica en PHP** con `password_hash()` y `password_verify()`. La BD solo guarda el hash (unos 60 caracteres que empiezan con `$2y$`) y nunca ve la clave en texto plano. Así se cumple la estándar del proyecto.

### 1. `pkg_seguridad.sp_seguridad_obtener_credencial`

Devuelve lo necesario para que PHP verifique la clave.

| Parámetro | Dir. | Tipo | Notas |
|---|---|---|---|
| `p_nombre_usuario` | IN | VARCHAR2 | Se compara sin distinguir mayúsculas (`LOWER`) |
| `p_id_usuario` | OUT | NUMBER | **NULL si el usuario no existe.** No lanza error, para no revelar qué usuarios existen |
| `p_clave_hash` | OUT | VARCHAR2 | Hash guardado |
| `p_estado` | OUT | VARCHAR2 | `ACTIVO` o `INACTIVO` |

Errores: `-20001` si `p_nombre_usuario` viene vacío.

### 2. `pkg_seguridad.sp_seguridad_registrar_acceso`

Anota un evento en `registro_accesos`. Si el evento es `LOGIN`, además actualiza `usuario.fecha_hora_ultimo_acceso`.

| Parámetro | Dir. | Tipo | Notas |
|---|---|---|---|
| `p_id_usuario` | IN | NUMBER | Puede ser NULL (intento con un usuario que no existe) |
| `p_nombre_usuario` | IN | VARCHAR2 | Lo que la persona escribió |
| `p_evento` | IN | VARCHAR2 | `LOGIN`, `LOGOUT`, `FALLIDO` o `CAMBIO_ROL` |
| `p_origen` | IN | VARCHAR2(100) | IP del navegador |

Errores: `-20002` si el evento no es uno de los cuatro permitidos. Este procedimiento **nunca debe fallar por datos faltantes** (estándar del proyecto.8).

### 3. `pkg_seguridad.sp_seguridad_crear_usuario`

Para la pantalla de administración y para el script de datos de ejemplo.

| Parámetro | Dir. | Tipo | Notas |
|---|---|---|---|
| `p_id_empleado` | IN | NUMBER | Debe existir en `empleado` |
| `p_nombre_usuario` | IN | VARCHAR2 | |
| `p_clave_hash` | IN | VARCHAR2 | Ya viene cifrada desde PHP |
| `p_id_rol` | IN | NUMBER | |
| `p_id_usuario` | OUT | NUMBER | Id generado |

Errores: `-20010` si el nombre de usuario ya existe, `-20011` si el empleado no existe, `-20012` si el rol no existe, `-20013` si ese empleado ya tiene usuario.

### 4. `pkg_seguridad.sp_seguridad_cambiar_clave`

| Parámetro | Dir. | Tipo |
|---|---|---|
| `p_id_usuario` | IN | NUMBER |
| `p_clave_hash` | IN | VARCHAR2 |

Errores: `-20020` si el usuario no existe.

### 5. Vista `vw_seguridad_usuario_sesion`

Una fila por usuario. Se usa al iniciar sesión, para mostrar el nombre y el rol.

| Columna | Origen |
|---|---|
| `id_usuario` | usuario |
| `nombre_usuario` | usuario |
| `nombre_completo` | persona: `nombre \|\| ' ' \|\| primer_apellido` |
| `id_rol` | usuario |
| `rol` | lista_rol.nombre |
| `estado` | usuario |

Consulta del aplicativo:
```sql
SELECT id_usuario, nombre_usuario, nombre_completo, id_rol, rol, estado
  FROM vw_seguridad_usuario_sesion
 WHERE id_usuario = :id_usuario
```

### 6. Vista `vw_seguridad_permisos_rol`

Una fila por cada permiso de cada rol.

| Columna | Origen |
|---|---|
| `id_rol` | permiso_por_rol |
| `codigo` | lista_permiso.codigo |

Consulta del aplicativo:
```sql
SELECT codigo
  FROM vw_seguridad_permisos_rol
 WHERE id_rol = :id_rol
```

### Códigos de permiso y qué rol los tiene

| Código | Para qué | adm | rec | med | enf | caj | aud |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `visita.ver` | Ver quién está dentro y el historial de visitas | ✔ | ✔ | | ✔ | | ✔ |
| `visita.registrar` | Registrar entradas y salidas | ✔ | ✔ | | | | |
| `cita.ver` | Ver la agenda | ✔ | ✔ | ✔ | ✔ | | ✔ |
| `cita.agendar` | Crear, mover o cancelar citas | ✔ | ✔ | | | | |
| `cita.atender` | Iniciar y finalizar la atención, diagnosticar | ✔ | | ✔ | | | |
| `paciente.ver` | Ver la ficha, el historial y los antecedentes | ✔ | ✔ | ✔ | ✔ | | |
| `paciente.editar` | Registrar o editar personas y pacientes | ✔ | ✔ | ✔ | | | |
| `nacimiento.registrar` | Registrar nacimientos | ✔ | | ✔ | ✔ | | |
| `catalogo.ver` | Ver medicamentos, enfermedades y tratamientos | ✔ | | ✔ | ✔ | ✔ | |
| `catalogo.editar` | Editar el catálogo médico | ✔ | | | | | |
| `venta.ver` | Ver pedidos y facturas | ✔ | | | | ✔ | ✔ |
| `venta.cobrar` | Crear pedidos, cobrar y facturar | ✔ | | | | ✔ | |
| `inventario.ver` | Ver existencias | ✔ | | | | ✔ | ✔ |
| `inventario.ajustar` | Registrar entradas y ajustes de inventario | ✔ | | | | | |
| `registro.ver` | Consultar las bitácoras (req. 11) | ✔ | | | | | ✔ |
| `usuario.administrar` | Crear usuarios y cambiar roles | ✔ | | | | | |

### Flujo del login (para entender el orden)

```
PHP                                            Oracle
 │ sp_seguridad_obtener_credencial('ana') ───▶ busca en usuario
 │ ◀── p_id_usuario, p_clave_hash, p_estado
 │ password_verify(clave, hash)  (en PHP)
 │   ├─ falla o INACTIVO → sp_seguridad_registrar_acceso(.., 'FALLIDO', ip)
 │   └─ ok → sp_seguridad_registrar_acceso(.., 'LOGIN', ip)
 │          SELECT … FROM vw_seguridad_usuario_sesion
 │          SELECT codigo FROM vw_seguridad_permisos_rol
 ▼ guarda usuario, rol y permisos en la sesión
```

---

# Objetos de Oracle que usa cada pantalla

## Base común: marco de la aplicación

| Objeto | Columnas o parámetros | Estado |
|---|---|---|
| `vw_cita_sala_espera` | Ver Citas | Creada |
| `vw_registro_avisos` | Ver Bitácoras | Creada |

Si esas vistas todavía no existen, el contador no aparece y la página sigue funcionando.

## Req. 1: Ventas

| Objeto | Columnas o parámetros |
|---|---|
| `vw_venta_pedido` | `id_pedido`, `cliente`, `numero_documento`, `empleado`, `estado`, `hora_pedido`, `es_hoy`, `subtotal`, `total_deducciones`, `total_pagar`, `numero_factura` |
| `vw_venta_detalle_pedido` | `id_pedido`, `codigo`, `producto`, `cantidad`, `precio_unitario`, `subtotal` |
| `vw_venta_deduccion_pedido` | `id_pedido`, `nombre`, `tipo_valor`, `valor`, `monto_aplicado` |
| `vw_venta_deduccion` | `id_deduccion`, `nombre`, `tipo_valor`, `valor` |
| `vw_venta_producto` | `id_producto`, `codigo`, `nombre`, `precio_venta`, `existencias` |
| `vw_persona_buscar` | `id_persona`, `numero_documento`, `nombre_completo` |
| `pkg_venta.sp_pedido_crear` | IN `p_numero_documento`, `p_id_usuario`, `p_lineas` (`12:2,15:1`), `p_deducciones` (`3,4`) · OUT `p_id_pedido`. Crea todo el pedido en una sola transacción |
| `pkg_venta.sp_pedido_cobrar` | IN `p_id_pedido`, `p_metodo_pago`, `p_monto`, `p_referencia` · OUT `p_numero_factura` |

Valores de `estado`: `EN_COBRO` y `PAGADO`. El monto de una deducción lo calcula la BD.

## Req. 2: Inventario

| Objeto | Columnas o parámetros |
|---|---|
| `vw_inventario_producto` | `id_producto`, `codigo`, `nombre`, `categoria`, `uso`, `existencias`, `stock_minimo`, `costo`, `precio_venta`, `id_categoria_producto`, `descripcion`, `se_vende` (`S`/`N`) |
| `vw_inventario_categoria` | `nombre`, `id_categoria_producto`, `productos` |
| `vw_inventario_movimiento` | `hora`, `tipo_movimiento` (`Entrada`, `Salida` o `Ajuste`), `producto`, `cantidad`, `observacion`, `es_hoy` |
| `vw_inventario_proveedor` | `id_proveedor`, `nombre`, `cedula_juridica`, `telefono`, `correo`, `direccion` |
| `pkg_inventario.sp_inventario_registrar_entrada` | IN `p_id_producto`, `p_id_proveedor`, `p_cantidad`, `p_observacion`, `p_id_usuario` |
| `pkg_inventario.sp_producto_guardar` | IN `p_id_producto` (NULL = nuevo), `p_id_categoria_producto`, `p_codigo`, `p_nombre`, `p_descripcion`, `p_costo`, `p_precio_venta` (obligatorio si se vende), `p_stock_minimo`, `p_se_vende` (`S`/`N`) · OUT `p_id_guardado` |
| `pkg_inventario.sp_proveedor_guardar` | IN `p_id_proveedor` (NULL = nuevo), `p_nombre`, `p_cedula_juridica` (10 dígitos; se guarda `3-101-123456`), `p_telefono` (8 dígitos o vacío), `p_correo`, `p_direccion` · OUT `p_id_guardado` |
| `pkg_inventario.sp_categoria_producto_guardar` | IN `p_id_categoria_producto` (NULL = nueva), `p_nombre` · OUT `p_id_guardado` |

> **Decisión para el equipo:** la imagen muestra el uso “Ambos”, pero `PRODUCTO.SE_VENDE` solo distingue sí o no. Con el modelo actual, `uso` solo puede ser `Venta` o `Uso médico`. Si quieren “Ambos”, hace falta otra columna en `PRODUCTO`. El código funciona con cualquiera de los dos casos.

## Req. 3: Visitantes

| Objeto | Columnas o parámetros |
|---|---|
| `vw_visita_dentro` | `id_visita`, `visitante`, `numero_documento`, `motivo_visita`, `area`, `destino`, `hora_entrada`, `minutos` |
| `vw_visita_salida_hoy` | `visitante`, `motivo_visita`, `hora_entrada`, `hora_salida`, `minutos` |
| `vw_visita_area` | `id_area`, `nombre` |
| `vw_visita_empleado` | `id_empleado`, `nombre_completo` |
| `vw_persona_buscar` | `id_persona`, `numero_documento`, `nombre_completo` |
| `pkg_persona.sp_persona_registrar` | IN `p_numero_documento`, `p_nombre`, `p_apellidos` · OUT `p_id_persona` |
| `pkg_visita.sp_visita_registrar_entrada` | IN `p_id_persona`, `p_motivo_visita`, `p_id_area`, `p_id_empleado_recibe`, `p_observacion` · OUT `p_id_visita` |
| `pkg_visita.sp_visita_registrar_salida` | IN `p_id_visita` |

`destino` sale de `VISITA.OBSERVACION`, porque el modelo no tiene una columna aparte para la habitación. `minutos` lo calcula la vista contra `SYSTIMESTAMP`.

## Req. 4: Citas

| Objeto | Columnas o parámetros |
|---|---|
| `vw_cita_agenda_dia` | `id_cita`, `id_medico`, `medico`, `especialidad`, `consultorio`, `hora_programada`, `duracion_minutos`, `apellido_paciente`, `estado`, `es_prioritaria` |
| `vw_cita_sala_espera` | `id_cita`, `paciente`, `medico`, `tipo_cita`, `hora_programada`, `minutos_espera`, `cita_previa_demora` (`S`/`N`) |
| `vw_cita_proxima_llegada` | `id_cita`, `paciente`, `medico`, `hora_programada` |
| `vw_cita_en_atencion` | `id_cita`, `paciente`, `medico`, `tipo_cita`, `fecha_agenda`, `hora_llegada`, `minutos_espera`, `hora_inicio` |
| `vw_cita_medico` | `id_medico`, `nombre` (“Dr. Javier Mora · Cardiología”) |
| `vw_cita_espacio_libre` | `id_medico`, `fecha` (`YYYY-MM-DD`), `hora` (`HH24:MI`) |
| `pkg_cita.sp_cita_agendar` | IN `p_id_persona`, `p_id_medico`, `p_fecha_hora`, `p_id_usuario` · OUT `p_id_cita`. Vuelve a comprobar el espacio y actualiza la próxima revisión |
| `pkg_cita.sp_cita_registrar_llegada` | IN `p_id_cita` |
| `pkg_cita.sp_cita_iniciar_atencion` | IN `p_id_cita` |
| `pkg_cita.sp_cita_registrar_resultado` | IN `p_id_cita`, `p_observacion`, `p_indicaciones`, `p_requiere_control`, `p_fecha_control`, `p_detalle_control` |

Valores de `estado`: `PROGRAMADA`, `EN_ESPERA`, `EN_ATENCION`, `ATENDIDA`. `p_fecha_hora` llega como texto `YYYY-MM-DD HH24:MI`.

## Req. 5: Pacientes

| Objeto | Columnas o parámetros |
|---|---|
| `vw_paciente_lista` | `id_paciente`, `nombre_completo`, `numero_documento`, `resumen` |
| `vw_paciente_ficha` | `id_paciente`, `nombre_completo`, `numero_documento`, `edad`, `sexo`, `tipo_sangre`, `ultima_cita`, `proxima_revision`, `contacto_emergencia`, `telefono_emergencia`, `estado`, `total_controles` |
| `vw_paciente_antecedente` | `id_paciente`, `tipo_antecedente`, `descripcion`, `fecha`, `origen` |
| `vw_paciente_historial` | `id_paciente`, `fecha`, `medico`, `resumen`, `origen` |
| `pkg_paciente.sp_paciente_registrar` | IN `p_numero_documento`, `p_nombre`, `p_apellidos`, `p_fecha_nacimiento`, `p_sexo`, `p_tipo_sangre`, `p_contacto_emergencia`, `p_telefono_emergencia`, `p_id_usuario` · OUT `p_id_paciente` |

`origen` trae el texto que se muestra en la etiqueta, por ejemplo `Cita C-01017` o `Nacimiento`. `resumen` es la línea corta de la lista, por ejemplo `Próxima revisión 18 nov.`.

## Req. 6: Nacimientos

| Objeto | Columnas o parámetros |
|---|---|
| `vw_nacimiento_madre` | `id_paciente`, `nombre` (“Valeria Campos Mora · 1-1502-0448”) |
| `vw_nacimiento_medico` | `id_medico`, `nombre` |
| `vw_nacimiento_reciente` | `bebe`, `madre`, `medico`, `fecha_hora`, `tipo_parto`, `semanas_gestacion`, `peso_gr`, `talla_cm`, `apgar_minuto_1`, `apgar_minuto_5`. Solo los últimos 7 días |
| `pkg_nacimiento.sp_nacimiento_registrar` | IN `p_id_paciente_madre`, `p_id_medico`, `p_nombre_bebe`, `p_apellidos_bebe`, `p_sexo`, `p_fecha_hora`, `p_tipo_parto`, `p_semanas_gestacion`, `p_peso_gr`, `p_talla_cm`, `p_apgar_minuto_1`, `p_apgar_minuto_5`, `p_observacion`, `p_id_usuario` · OUT `p_id_paciente` |

`p_talla_cm` llega como texto con punto decimal. Conviértelo en la BD con `TO_NUMBER(p_talla_cm, '999D9', 'NLS_NUMERIC_CHARACTERS=''.,''')`. `p_fecha_hora` llega como `YYYY-MM-DDTHH24:MI`.

## Req. 7: Prioridad de empleados

| Objeto | Columnas o parámetros |
|---|---|
| `vw_prioridad_reciente` | `id_cita`, `empleado`, `numero_documento`, `puesto`, `medico`, `motivo`, `hora_asignada`, `duracion_minutos`, `hora_aplicada` |
| `vw_prioridad_cita_movida` | `id_cita_prioritaria`, `paciente`, `apellido_paciente`, `telefono`, `hora_anterior`, `hora_nueva`, `motivo`, `aviso_leido` (`S`/`N`) |
| `vw_cita_medico` | Ver Citas |
| `pkg_cita.sp_cita_dar_prioridad` | IN `p_numero_documento`, `p_id_medico`, `p_motivo`, `p_id_usuario` · OUT `p_id_cita`. Valida que sea empleado activo, elige la hora, mueve las citas (`CITA_MOVIDA`) y escribe un aviso por paciente (`REGISTRO_AVISOS`) |

## Req. 8: Medicamentos

| Objeto | Columnas o parámetros |
|---|---|
| `vw_medicamento_lista` | `id_medicamento`, `id_producto`, `codigo`, `principio_activo`, `concentracion`, `presentacion`, `via_aplicacion`, `indicaciones_uso`, `restricciones`, `requiere_receta`, `existencias` |
| `vw_medicamento_hora` | `id_medicamento`, `hora` (`HH24:MI`) |
| `vw_medicamento_tratamiento` | `id_medicamento`, `codigo_cie10`, `enfermedad`, `dosis`, `frecuencia_horas`, `duracion_dias` |
| `vw_medicamento_producto` | `id_producto`, `nombre`. Productos de la categoría medicamento que todavía no tienen ficha |
| `pkg_catalogo.sp_medicamento_guardar` | IN `p_id_medicamento` (NULL = nuevo), `p_id_producto`, `p_principio_activo`, `p_concentracion`, `p_presentacion`, `p_via_aplicacion`, `p_indicaciones_uso`, `p_restricciones`, `p_requiere_receta`, `p_horas` (`06:00,14:00,22:00`) |
| `pkg_catalogo.sp_medicamento_con_producto` | IN `p_codigo`, `p_nombre_producto`, `p_costo`, `p_precio_venta`, `p_stock_minimo`, `p_se_vende` y los mismos datos de la ficha que `sp_medicamento_guardar` · OUT `p_id_medicamento`. Crea el producto en la categoría Medicamentos y la ficha en una sola operación |

> **Para revisar en la BD:** el MER marca que `HORARIO_MEDICAMENTO` apunta a `MEDICAMENTO`, sin paciente ni receta. Por eso esta pantalla muestra horas de referencia; el horario real de cada paciente queda en la receta.

## Req. 9: Enfermedades

| Objeto | Columnas o parámetros |
|---|---|
| `vw_enfermedad_tratamiento` | `id_enfermedad`, `codigo_cie10`, `enfermedad`, `categoria`, `id_medicamento`, `medicamento`, `dosis`, `frecuencia_horas`, `duracion_dias`. Una fila por tratamiento |
| `vw_enfermedad_categoria` | `id_categoria_enfermedad`, `nombre`, `enfermedades` |
| `pkg_catalogo.sp_categoria_enfermedad_guardar` | IN `p_id_categoria_enfermedad` (NULL = nueva), `p_nombre` · OUT `p_id_guardado` |
| `pkg_catalogo.sp_enfermedad_guardar` | IN `p_id_categoria_enfermedad`, `p_codigo_cie10`, `p_nombre`, `p_descripcion`, `p_tratamiento_general`, `p_id_medicamento`, `p_dosis`, `p_frecuencia_horas`, `p_duracion_dias` · OUT `p_id_enfermedad` |

## Req. 10: Usuarios y roles

| Objeto | Columnas o parámetros | Estado |
|---|---|---|
| `pkg_seguridad.sp_seguridad_crear_usuario` | Como está en `contratos-bd.md` | Creado |
| `pkg_seguridad.sp_seguridad_cambiar_clave` | Como está en `contratos-bd.md` | Creado |
| `pkg_seguridad.sp_seguridad_registrar_acceso` | Evento `CAMBIO_ROL` | Creado |
| `vw_seguridad_permisos_rol` | `id_rol`, `codigo` | Creado |
| `pkg_seguridad.sp_seguridad_editar_usuario` | IN `p_id_usuario`, `p_nombre_usuario`, `p_id_rol`, `p_estado`. Errores: `-20010` si el nombre ya existe, `-20012` si el rol no existe | Creado |
| `vw_seguridad_usuario_lista` | `id_usuario`, `nombre_usuario`, `nombre_completo`, `puesto`, `id_rol`, `rol`, `estado`, `ultimo_acceso` | Creado |
| `vw_seguridad_rol` | `id_rol`, `nombre` | Creado |
| `vw_seguridad_permiso` | `codigo` | Creado |
| `vw_seguridad_empleado_libre` | `id_empleado`, `nombre` (“Daniela Fallas Mora · Cajera”). Empleados sin usuario | Creado |

El formulario de crear depende de `EMPLEADO`: sin esa tabla, `vw_seguridad_empleado_libre` no se puede crear.

### Empleados (`/empleados`, permiso `usuario.administrar`) · `pkg_empleado` · errores -20901 a -20949

| Objeto | Columnas o parámetros | Estado |
|---|---|---|
| `pkg_empleado.sp_empleado_registrar` | IN `p_numero_documento`, `p_nombre`, `p_apellidos`, `p_sexo` (`M`/`F`/`I`), `p_fecha_nacimiento`, `p_telefono`, `p_correo`, `p_direccion`, `p_id_puesto`, `p_fecha_ingreso` (fechas `YYYY-MM-DD`); OUT `p_id_empleado`. Si la cédula ya existe, reutiliza la persona y solo completa sus datos vacíos | Pendiente (script 160) |
| `pkg_empleado.sp_empleado_editar` | IN `p_id_empleado` + los mismos datos (sin cédula) + `p_estado`. Si queda `INACTIVO`, su usuario también se inactiva | Pendiente (script 160) |
| `pkg_empleado.sp_puesto_crear` | IN `p_nombre`, `p_salario_hora`; OUT `p_id_puesto` | Pendiente (script 160) |
| `pkg_empleado.sp_puesto_editar` | IN `p_id_puesto`, `p_nombre`, `p_salario_hora` | Pendiente (script 160) |
| `vw_empleado_lista` | `id_empleado`, `numero_documento`, `nombre`, `apellidos`, `nombre_completo`, `sexo`, `fecha_nacimiento`, `telefono`, `correo`, `direccion`, `id_puesto`, `puesto`, `fecha_ingreso` (fechas `YYYY-MM-DD`), `estado`, `nombre_usuario`, `rol`, `estado_usuario` | Pendiente (script 160) |
| `vw_empleado_puesto` | `id_puesto`, `nombre`, `salario_hora`, `empleados` (activos) | Pendiente (script 160) |

Errores: `-20901` faltan datos, `-20902` puesto inexistente, `-20903` fechas inválidas o futuras, `-20904` ya es empleado, `-20905` sexo inválido, `-20906` estado inválido, `-20907` empleado inexistente, `-20910` a `-20912` nombre o salario del puesto.

### Tipos de documento (script 190)

`pkg_persona.fn_persona_documento(p_tipo_documento, p_numero_documento)` deja cada documento con su formato oficial o da `-20804`: `CEDULA` → `1-1111-1111`, `DIMEX` → 11 o 12 dígitos sin cero inicial, `PASAPORTE` → 6 a 9 letras o números en mayúsculas. `sp_persona_registrar`, `sp_paciente_registrar` y `sp_empleado_registrar` reciben `p_tipo_documento` (por omisión `CEDULA`); `sp_empleado_editar` recibe `p_tipo_documento` y `p_numero_documento` (si llega vacío, conserva el que había; `-20909` si es de otra persona). Empleados: solo `CEDULA` o `DIMEX` (`-20908`). `vw_empleado_lista` agrega `tipo_documento`. El aplicativo aplica las mismas reglas en `App\Core\Documento`.

### Bitácoras completas · `pkg_bitacora` (script 170)

`pkg_registro` sigue sin permiso para el aplicativo. Lo que el aplicativo anota pasa por `pkg_bitacora`:

| Objeto | Parámetros | Lo usa |
|---|---|---|
| `pkg_bitacora.sp_bitacora_error` | IN `p_tema`, `p_objeto` (“Formulario /empleados”), `p_codigo` (403, 404 con sesión, 419, 422, 500, 503), `p_mensaje`, `p_detalle` (técnico, no se muestra) | Formularios rechazados y páginas de error |
| `pkg_bitacora.sp_bitacora_tiempos` | IN `p_lista` = `Vista\|vw_nombre\|12.345;...` | Tiempos de las consultas; viajan con la primera consulta de la página siguiente |
| `pkg_bitacora.sp_bitacora_usuario_bloqueado` | IN `p_nombre_usuario`, `p_origen` | Quinto intento fallido de clave |

En Oracle: cada procedimiento con pasos `inicio` y `fin` anota su tiempo (`Procedimiento`); los triggers `trg_inventario_aviso_stock`, `trg_empleado_aviso_inactivo` y `trg_usuario_aviso_inactivo` generan avisos, y la tarea `JOB_REVISAR_DEMORAS` avisa cada 5 minutos de esperas de más de 45 minutos. Tipos de aviso: `CITA_MOVIDA`, `STOCK_BAJO`, `DEMORA`, `USUARIO_BLOQUEADO`, `USUARIO_INACTIVADO`, `EMPLEADO_INACTIVADO`.

## Req. 11: Bitácoras

Todas filtran por `fecha` (`YYYY-MM-DD`) y formatean la hora con `TO_CHAR(fecha_hora, 'HH24:MI:SS.FF3')`, sin cambiar el tipo de la columna.

| Vista | Columnas |
|---|---|
| `vw_registro_pasos` | `fecha`, `hora`, `proceso`, `referencia`, `paso`, `resultado` (`OK`, `ERROR`, `ADVERTENCIA`) |
| `vw_registro_cambios` | `fecha`, `hora`, `tabla`, `accion`, `valor_anterior`, `valor_nuevo`, `usuario_bd` |
| `vw_registro_errores` | `fecha`, `hora`, `tema`, `objeto`, `codigo`, `mensaje`, `usuario_bd` |
| `vw_registro_accesos` | `fecha`, `hora`, `nombre_usuario`, `evento`, `origen` |
| `vw_registro_tiempos` | `fecha`, `hora`, `tipo_objeto`, `nombre_objeto`, `duracion_ms` |
| `vw_registro_avisos` | `fecha`, `hora`, `tipo_aviso`, `persona`, `detalle`, `leido` (`S`/`N`) |
| `vw_registro_rastro` | `referencia`, `fecha`, `hora`, `clase` (`inicio`, `paso`, `error`, `fin`), `titulo`, `detalle`, `antes`, `despues` |

`vw_registro_avisos` debe unir `REGISTRO_AVISOS` con `PERSONA`, porque esa tabla solo guarda `ID_PERSONA`. `vw_registro_rastro` junta las bitácoras por referencia: conviene acordar con el equipo que todo procedimiento escriba la misma referencia (por ejemplo `PEDIDO 1038`).

`proceso` trae solo el nombre del procedimiento (`sp_cita_agendar`).

Las `CLOB` de `registro_cambios` deben salir con `DBMS_LOB.SUBSTR(valor, 500)`, porque `select()` espera texto.
