<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Opciones del menú, agrupadas por departamento, y el permiso (lista_permiso.codigo) que exige cada una.
 * El layout muestra solo las que el rol del usuario tiene en permiso_por_rol: cada departamento es un
 * botón desplegable, o un enlace directo si al rol le queda un solo módulo en él.
 */
final class Menu
{
    /** Departamentos en el orden de la barra, con su ícono (Bootstrap Icons). */
    public const GRUPOS = [
        'Atención' => 'clipboard2-pulse',
        'Recepción' => 'door-open',
        'Catálogo médico' => 'journal-medical',
        'Farmacia' => 'bag-plus',
        'Administración' => 'gear',
    ];

    /** @var list<array{grupo:string, texto:string, ruta:string, icono:string, permiso:string, descripcion:string}> */
    public const OPCIONES = [
        ['grupo' => 'Atención', 'texto' => 'Citas', 'ruta' => '/citas', 'icono' => 'calendar2-check', 'permiso' => 'cita.ver', 'descripcion' => 'Agenda, llegada y atención'],
        ['grupo' => 'Atención', 'texto' => 'Pacientes', 'ruta' => '/pacientes', 'icono' => 'people', 'permiso' => 'paciente.ver', 'descripcion' => 'Expediente y antecedentes'],
        ['grupo' => 'Atención', 'texto' => 'Médicos', 'ruta' => '/medicos', 'icono' => 'person-check', 'permiso' => 'cita.ver', 'descripcion' => 'Especialidades, horarios y citas asignadas'],
        ['grupo' => 'Atención', 'texto' => 'Habitaciones', 'ruta' => '/habitaciones', 'icono' => 'hospital', 'permiso' => 'habitacion.ver', 'descripcion' => 'Ocupación, ingresos y salidas'],
        ['grupo' => 'Atención', 'texto' => 'Nacimientos', 'ruta' => '/nacimientos', 'icono' => 'heart-pulse', 'permiso' => 'nacimiento.registrar', 'descripcion' => 'Registro del recién nacido'],
        ['grupo' => 'Atención', 'texto' => 'Prioridad de empleados', 'ruta' => '/prioridad', 'icono' => 'exclamation-triangle', 'permiso' => 'cita.agendar', 'descripcion' => 'Citas de emergencia para el personal'],
        ['grupo' => 'Recepción', 'texto' => 'Visitantes', 'ruta' => '/visitas', 'icono' => 'door-open', 'permiso' => 'visita.ver', 'descripcion' => 'Entradas y salidas de visitantes'],
        ['grupo' => 'Catálogo médico', 'texto' => 'Medicamentos', 'ruta' => '/medicamentos', 'icono' => 'capsule', 'permiso' => 'catalogo.ver', 'descripcion' => 'Uso, restricciones y horas de aplicación'],
        ['grupo' => 'Catálogo médico', 'texto' => 'Enfermedades', 'ruta' => '/enfermedades', 'icono' => 'clipboard2-pulse', 'permiso' => 'catalogo.ver', 'descripcion' => 'Tratamientos y categorías'],
        ['grupo' => 'Farmacia', 'texto' => 'Ventas', 'ruta' => '/ventas', 'icono' => 'receipt', 'permiso' => 'venta.ver', 'descripcion' => 'Pedidos, cobros y facturas'],
        ['grupo' => 'Farmacia', 'texto' => 'Inventario', 'ruta' => '/inventario', 'icono' => 'box-seam', 'permiso' => 'inventario.ver', 'descripcion' => 'Existencias, entradas y proveedores'],
        ['grupo' => 'Administración', 'texto' => 'Empleados', 'ruta' => '/empleados', 'icono' => 'person-badge', 'permiso' => 'usuario.administrar', 'descripcion' => 'Personal y puestos'],
        ['grupo' => 'Administración', 'texto' => 'Usuarios y roles', 'ruta' => '/usuarios', 'icono' => 'shield-lock', 'permiso' => 'usuario.administrar', 'descripcion' => 'Accesos y permisos'],
        ['grupo' => 'Administración', 'texto' => 'Bitácoras', 'ruta' => '/bitacoras', 'icono' => 'journal-text', 'permiso' => 'registro.ver', 'descripcion' => 'Rastro de todo lo que pasa'],
    ];

    /**
     * Filtra OPCIONES y deja solo las que el rol puede ver.
     *
     * @param list<string> $permisos Códigos de permiso del usuario (Auth::permissions()).
     * @return list<array{grupo:string, texto:string, ruta:string, icono:string, permiso:string, descripcion:string}>
     */
    public static function para(array $permisos): array
    {
        $visibles = array_filter(
            self::OPCIONES,
            static fn(array $o): bool => in_array($o['permiso'], $permisos, true),
        );
        return array_values($visibles);
    }

    /**
     * Las opciones que el rol puede ver, por departamento y en el orden de GRUPOS.
     * Los departamentos sin ninguna opción visible no aparecen.
     *
     * @param list<string> $permisos
     * @return array<string, list<array{grupo:string, texto:string, ruta:string, icono:string, permiso:string, descripcion:string}>>
     */
    public static function porGrupo(array $permisos): array
    {
        $grupos = array_fill_keys(array_keys(self::GRUPOS), []);
        foreach (self::para($permisos) as $opcion) {
            $grupos[$opcion['grupo']][] = $opcion;
        }
        return array_filter($grupos, static fn(array $opciones): bool => $opciones !== []);
    }
}
