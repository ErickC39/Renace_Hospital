<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Convierte los nombres internos de la base de datos en texto para las personas.
 * Las pantallas y las descargas deben pasar por aquí todo código que venga de Oracle.
 * Si aparece un nombre nuevo sin traducción, se muestra legible, nunca tal cual.
 */
final class Etiquetas
{
    private const PERMISOS = [
        'catalogo.editar' => 'Editar catálogo médico',
        'catalogo.ver' => 'Ver catálogo médico',
        'cita.agendar' => 'Agendar citas',
        'cita.atender' => 'Atender citas',
        'cita.ver' => 'Ver citas',
        'habitacion.asignar' => 'Asignar habitaciones',
        'habitacion.ver' => 'Ver habitaciones',
        'inventario.ajustar' => 'Ajustar inventario',
        'inventario.ver' => 'Ver inventario',
        'nacimiento.registrar' => 'Registrar nacimientos',
        'paciente.editar' => 'Editar pacientes',
        'paciente.ver' => 'Ver pacientes',
        'registro.ver' => 'Ver bitácoras',
        'usuario.administrar' => 'Administrar usuarios',
        'venta.cobrar' => 'Cobrar ventas',
        'venta.ver' => 'Ver ventas',
        'visita.registrar' => 'Registrar visitantes',
        'visita.ver' => 'Ver visitantes',
    ];

    private const ROLES = [
        'administrador' => 'Administrador',
        'recepcion' => 'Recepción',
        'medico' => 'Médico',
        'enfermeria' => 'Enfermería',
        'cajero' => 'Cajero',
        'auditor' => 'Auditor',
    ];

    private const PROCESOS = [
        'acceso' => 'Registro del acceso',
        'login' => 'Revisión de la clave',
        'sp_categoria_enfermedad_guardar' => 'Guardar categoría de enfermedad',
        'sp_categoria_producto_guardar' => 'Guardar categoría de producto',
        'sp_aviso_marcar_dia' => 'Marcar avisos del día como leídos',
        'sp_aviso_marcar_leido' => 'Marcar aviso como leído',
        'sp_cita_agendar' => 'Agendar cita',
        'sp_cita_cancelar' => 'Cancelar cita',
        'sp_cita_dar_prioridad' => 'Dar prioridad a una cita',
        'sp_cita_marcar_ausentes' => 'Marcar citas ausentes',
        'sp_cita_reprogramar' => 'Reprogramar cita',
        'sp_cita_iniciar_atencion' => 'Iniciar atención',
        'sp_cita_registrar_llegada' => 'Registrar llegada',
        'sp_cita_registrar_resultado' => 'Registrar resultado de la cita',
        'sp_deduccion_guardar' => 'Guardar deducción',
        'sp_empleado_editar' => 'Editar empleado',
        'sp_empleado_registrar' => 'Registrar empleado',
        'sp_especialidad_guardar' => 'Guardar especialidad',
        'sp_enfermedad_guardar' => 'Guardar enfermedad',
        'sp_habitacion_estado' => 'Cambiar estado de habitación',
        'sp_habitacion_guardar' => 'Guardar habitación',
        'sp_horario_guardar' => 'Guardar horario de médico',
        'sp_hospitalizacion_ingresar' => 'Ingresar paciente a habitación',
        'sp_hospitalizacion_salida' => 'Dar salida de habitación',
        'sp_horario_quitar' => 'Quitar bloque de horario',
        'sp_inventario_registrar_entrada' => 'Registrar entrada de inventario',
        'sp_medicamento_con_producto' => 'Guardar medicamento con su producto',
        'sp_medicamento_guardar' => 'Guardar medicamento',
        'sp_medico_guardar' => 'Guardar médico',
        'sp_nacimiento_registrar' => 'Registrar nacimiento',
        'sp_paciente_registrar' => 'Registrar paciente',
        'sp_pedido_cobrar' => 'Cobrar pedido',
        'sp_pedido_crear' => 'Crear pedido',
        'sp_persona_registrar' => 'Registrar persona',
        'sp_producto_guardar' => 'Guardar producto',
        'sp_proveedor_guardar' => 'Guardar proveedor',
        'sp_puesto_crear' => 'Crear puesto',
        'sp_puesto_editar' => 'Editar puesto',
        'sp_recuperacion_completar' => 'Recuperar clave con código',
        'sp_recuperacion_fallo' => 'Código de recuperación equivocado',
        'sp_recuperacion_obtener' => 'Consultar código de recuperación',
        'sp_recuperacion_solicitar' => 'Pedir código para recuperar la clave',
        'sp_seguridad_cambiar_clave' => 'Cambiar clave',
        'sp_seguridad_crear_usuario' => 'Crear usuario',
        'sp_seguridad_editar_usuario' => 'Editar usuario',
        'sp_seguridad_obtener_credencial' => 'Validar credenciales',
        'sp_seguridad_registrar_acceso' => 'Registrar acceso',
        'sp_visita_registrar_entrada' => 'Registrar entrada de visitante',
        'sp_visita_registrar_salida' => 'Registrar salida de visitante',
    ];

    private const ACCIONES = ['INSERT' => 'Creación', 'UPDATE' => 'Modificación', 'DELETE' => 'Eliminación'];

    private const EVENTOS = [
        'LOGIN' => 'Inicio de sesión',
        'LOGOUT' => 'Cierre de sesión',
        'FALLIDO' => 'Intento fallido',
        'CAMBIO_ROL' => 'Cambio de rol',
        'RECUPERACION_PEDIDA' => 'Pidió código para recuperar la clave',
        'RECUPERACION_FALLIDA' => 'Código de recuperación equivocado',
        'RECUPERACION_OK' => 'Recuperó su clave con un código',
    ];

    /** Pasos que la base anota con su nombre interno. */
    private const PASOS = [
        'inicio' => 'Inicio',
        'fin' => 'Fin',
        'obtener_credencial' => 'Clave consultada',
        'registrar_login' => 'Entrada al sistema',
        'registrar_logout' => 'Salida del sistema',
        'registrar_fallido' => 'Intento fallido',
        'registrar_cambio_rol' => 'Cambio de rol',
    ];

    private const RESULTADOS = ['OK' => 'OK', 'ERROR' => 'Error', 'ADVERTENCIA' => 'Advertencia', 'ROLLBACK' => 'Revertido'];

    /** Tildes de las palabras que los nombres internos escriben sin ellas. */
    private const TILDES = [
        'aplicacion' => 'aplicación', 'area' => 'área', 'atencion' => 'atención', 'categoria' => 'categoría',
        'clinica' => 'clínica', 'clinico' => 'clínico', 'codigo' => 'código', 'deduccion' => 'deducción',
        'descripcion' => 'descripción', 'dia' => 'día', 'diagnostico' => 'diagnóstico', 'direccion' => 'dirección',
        'duracion' => 'duración', 'enfermeria' => 'enfermería', 'gestacion' => 'gestación',
        'identificacion' => 'identificación', 'maximo' => 'máximo', 'medico' => 'médico', 'medicos' => 'médicos',
        'minimo' => 'mínimo', 'numero' => 'número', 'observacion' => 'observación', 'pais' => 'país',
        'presentacion' => 'presentación', 'recepcion' => 'recepción', 'sesion' => 'sesión', 'telefono' => 'teléfono',
        'ultimo' => 'último', 'via' => 'vía',
    ];

    public static function permiso(string $codigo): string
    {
        return self::PERMISOS[$codigo] ?? self::legible(str_replace('.', ' ', $codigo));
    }

    public static function rol(string $nombre): string
    {
        return self::ROLES[$nombre] ?? self::legible($nombre);
    }

    /** Acepta el nombre con o sin paquete: pkg_venta.sp_pedido_crear o sp_pedido_crear. */
    public static function proceso(string $nombre): string
    {
        if (str_contains($nombre, '/')) {
            return $nombre;  // errores del aplicativo: "Formulario /empleados"
        }
        if (str_contains($nombre, ', ')) {
            return implode(', ', array_map(self::proceso(...), explode(', ', $nombre)));
        }
        $corto = substr((string) strrchr('.' . $nombre, '.'), 1);
        return self::PROCESOS[$corto] ?? self::legible($corto);
    }

    /** "ESTADO_CITA" → "Estado cita", "HORARIO_MEDICO" → "Horario médico". */
    public static function legible(string $nombre): string
    {
        $texto = (string) preg_replace('/^(sp|vw|pkg|trg|lista)_/', '', mb_strtolower(trim($nombre)));
        $palabras = array_map(
            static fn(string $p): string => self::TILDES[$p] ?? $p,
            preg_split('/[\s_]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );
        $unido = implode(' ', $palabras);
        return mb_strtoupper(mb_substr($unido, 0, 1)) . mb_substr($unido, 1);
    }

    /**
     * "ID_PEDIDO=5; ESTADO=EN_COBRO; " → "Estado: En cobro".
     * Oculta los identificadores internos y cualquier dato de claves.
     */
    public static function valores(string $texto): string
    {
        preg_match_all('/(?:^|;\s*)([A-Z][A-Z0-9_]*)=(.*?)(?=;\s*[A-Z][A-Z0-9_]*=|;\s*$|$)/s', $texto, $campos, PREG_SET_ORDER);
        $partes = [];
        foreach ($campos as [, $columna, $valor]) {
            if ($valor === '' || preg_match('/^ID_|CLAVE|HASH|TOKEN/', $columna) === 1) {
                continue;
            }
            $partes[] = self::legible($columna) . ': ' . match (true) {
                // Montos en colones: precios, costos, totales, pagos y salarios.
                preg_match('/PRECIO|COSTO|TOTAL|MONTO|SALARIO/', $columna) === 1 && is_numeric(str_replace(',', '.', $valor)) => colones($valor),
                preg_match('/^[A-Z]{2,}(_[A-Z]+)*$/', $valor) === 1 => self::legible($valor),
                default => $valor,
            };
        }
        return implode(' · ', $partes);
    }

    /** "PEDIDO 5" → "Pedido 5". Las demás referencias (cédula, usuario) quedan igual. */
    public static function referencia(string $referencia): string
    {
        if (preg_match('/^AVISOS (\d{4})-(\d{2})-(\d{2})$/', $referencia, $m) === 1) {
            return "Avisos del {$m[3]}/{$m[2]}/{$m[1]}";
        }
        return preg_match('/^[A-Z]+ \d+$/', $referencia) === 1 ? self::legible($referencia) : $referencia;
    }

    /** Los mensajes propios del sistema se muestran; los errores internos de Oracle, no. */
    public static function mensaje(string $mensaje): string
    {
        $linea = trim(strtok($mensaje, "\r\n") ?: '');
        if (preg_match('/^ORA-20\d{3}:\s*(.*)$/', $linea, $propio) === 1) {
            return $propio[1];
        }
        return str_starts_with($linea, 'ORA-') ? 'Error interno de la aplicación.' : $linea;
    }

    /** Texto de una celda de las bitácoras, para la pantalla y para el CSV. */
    public static function bitacora(string $columna, string $valor): string
    {
        return match ($columna) {
            'proceso', 'objeto', 'nombre_objeto' => self::proceso($valor),
            'tabla', 'tipo_aviso', 'tema' => self::legible($valor),
            'accion' => self::ACCIONES[$valor] ?? self::legible($valor),
            'evento' => self::EVENTOS[$valor] ?? self::legible($valor),
            'resultado' => self::RESULTADOS[$valor] ?? self::legible($valor),
            'valor_anterior', 'valor_nuevo' => self::valores($valor),
            'tipo_objeto' => stripos($valor, 'vista') !== false ? 'Consulta' : 'Operación',
            'referencia' => self::referencia($valor),
            'mensaje' => self::mensaje($valor),
            'paso' => self::PASOS[$valor] ?? (preg_match('/^[a-z_]+$/', $valor) === 1 ? self::legible($valor) : $valor),
            'leido' => $valor === 'S' ? 'Leído' : 'Sin leer',
            default => $valor,
        };
    }

    /** "sp_pedido_crear: inicio" → "Crear pedido: inicio". */
    public static function tituloRastro(string $titulo): string
    {
        [$proceso, $paso] = explode(': ', $titulo, 2) + [1 => ''];
        return self::proceso($proceso) . ($paso === '' ? '' : ': ' . $paso);
    }
}
