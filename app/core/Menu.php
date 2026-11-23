<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Opciones del menú y el permiso (lista_permiso.codigo) que exige cada una.
 * El layout muestra solo las que el rol del usuario tiene en permiso_por_rol.
 */
final class Menu
{
    /** @var list<array{grupo:string, texto:string, ruta:string, icono:string, permiso:string}> */
    public const OPCIONES = [
        ['grupo' => 'Atención', 'texto' => 'Citas', 'ruta' => '/citas', 'icono' => 'calendar2-check', 'permiso' => 'cita.ver'],
        ['grupo' => 'Atención', 'texto' => 'Médicos', 'ruta' => '/medicos', 'icono' => 'person-check', 'permiso' => 'cita.ver'],
        ['grupo' => 'Atención', 'texto' => 'Pacientes', 'ruta' => '/pacientes', 'icono' => 'people', 'permiso' => 'paciente.ver'],
        ['grupo' => 'Atención', 'texto' => 'Habitaciones', 'ruta' => '/habitaciones', 'icono' => 'hospital', 'permiso' => 'habitacion.ver'],
        ['grupo' => 'Atención', 'texto' => 'Nacimientos', 'ruta' => '/nacimientos', 'icono' => 'heart-pulse', 'permiso' => 'nacimiento.registrar'],
        ['grupo' => 'Atención', 'texto' => 'Prioridad de empleados', 'ruta' => '/prioridad', 'icono' => 'exclamation-triangle', 'permiso' => 'cita.agendar'],
        ['grupo' => 'Recepción', 'texto' => 'Visitantes', 'ruta' => '/visitas', 'icono' => 'door-open', 'permiso' => 'visita.ver'],
        ['grupo' => 'Catálogo médico', 'texto' => 'Medicamentos', 'ruta' => '/medicamentos', 'icono' => 'capsule', 'permiso' => 'catalogo.ver'],
        ['grupo' => 'Catálogo médico', 'texto' => 'Enfermedades', 'ruta' => '/enfermedades', 'icono' => 'clipboard2-pulse', 'permiso' => 'catalogo.ver'],
        ['grupo' => 'Ventas', 'texto' => 'Ventas', 'ruta' => '/ventas', 'icono' => 'receipt', 'permiso' => 'venta.ver'],
        ['grupo' => 'Ventas', 'texto' => 'Inventario', 'ruta' => '/inventario', 'icono' => 'box-seam', 'permiso' => 'inventario.ver'],
        ['grupo' => 'Administración', 'texto' => 'Empleados', 'ruta' => '/empleados', 'icono' => 'person-badge', 'permiso' => 'usuario.administrar'],
        ['grupo' => 'Administración', 'texto' => 'Usuarios y roles', 'ruta' => '/usuarios', 'icono' => 'shield-lock', 'permiso' => 'usuario.administrar'],
        ['grupo' => 'Administración', 'texto' => 'Bitácoras', 'ruta' => '/bitacoras', 'icono' => 'journal-text', 'permiso' => 'registro.ver'],
    ];

    /**
     * Filtra OPCIONES y deja solo las que el rol puede ver.
     *
     * @param list<string> $permisos Códigos de permiso del usuario (Auth::permissions()).
     * @return list<array{grupo:string, texto:string, ruta:string, icono:string, permiso:string}>
     */
    public static function para(array $permisos): array
    {
        $visibles = array_filter(
            self::OPCIONES,
            static fn(array $o): bool => in_array($o['permiso'], $permisos, true),
        );
        return array_values($visibles);
    }
}
