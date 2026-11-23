<?php

declare(strict_types=1);

/*
 * Todas las rutas del aplicativo, en un solo lugar.
 * Formato: $router->get('/ruta', [Controlador::class, 'metodo'], 'permiso_opcional');
 * Permiso: null = pública · Auth::SESION = basta con iniciar sesión · 'visita.ver' = código de lista_permiso
 * Las rutas fijas (/ventas/nuevo) van antes que las que llevan parámetro (/ventas/{id}).
 */

use App\Controllers\AuthController;
use App\Controllers\BitacoraController;
use App\Controllers\CitaController;
use App\Controllers\EmpleadoController;
use App\Controllers\EnfermedadController;
use App\Controllers\HabitacionController;
use App\Controllers\HomeController;
use App\Controllers\InventarioController;
use App\Controllers\MedicamentoController;
use App\Controllers\MedicoController;
use App\Controllers\MiClaveController;
use App\Controllers\NacimientoController;
use App\Controllers\PacienteController;
use App\Controllers\PrioridadController;
use App\Controllers\UsuarioController;
use App\Controllers\VentaController;
use App\Controllers\VisitaController;
use App\Core\Auth;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index'], Auth::SESION);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout'], Auth::SESION);
    $router->get('/mi-clave', [MiClaveController::class, 'formulario'], Auth::SESION);
    $router->post('/mi-clave', [MiClaveController::class, 'guardar'], Auth::SESION);

    // Ventas
    $router->get('/ventas', [VentaController::class, 'index'], 'venta.ver');
    $router->get('/ventas/nuevo', [VentaController::class, 'nuevo'], 'venta.cobrar');
    $router->post('/ventas/nuevo', [VentaController::class, 'crear'], 'venta.cobrar');
    $router->post('/ventas/{id}/cobrar', [VentaController::class, 'cobrar'], 'venta.cobrar');

    // Inventario
    $router->get('/inventario', [InventarioController::class, 'index'], 'inventario.ver');
    $router->get('/inventario/exportar', [InventarioController::class, 'exportar'], 'inventario.ver');
    $router->post('/inventario/entrada', [InventarioController::class, 'entrada'], 'inventario.ajustar');
    $router->post('/inventario/productos', [InventarioController::class, 'crearProducto'], 'inventario.ajustar');
    $router->post('/inventario/productos/{id}', [InventarioController::class, 'editarProducto'], 'inventario.ajustar');
    $router->get('/inventario/catalogos', [InventarioController::class, 'catalogos'], 'inventario.ajustar');
    $router->post('/inventario/proveedores', [InventarioController::class, 'crearProveedor'], 'inventario.ajustar');
    $router->post('/inventario/proveedores/{id}', [InventarioController::class, 'editarProveedor'], 'inventario.ajustar');
    $router->post('/inventario/categorias', [InventarioController::class, 'crearCategoria'], 'inventario.ajustar');
    $router->post('/inventario/categorias/{id}', [InventarioController::class, 'editarCategoria'], 'inventario.ajustar');

    // Visitantes
    $router->get('/visitas', [VisitaController::class, 'index'], 'visita.ver');
    $router->post('/visitas/entrada', [VisitaController::class, 'entrada'], 'visita.registrar');
    $router->post('/visitas/{id}/salida', [VisitaController::class, 'salida'], 'visita.registrar');

    // Citas
    $router->get('/citas', [CitaController::class, 'index'], 'cita.ver');
    $router->post('/citas/agendar', [CitaController::class, 'agendar'], 'cita.agendar');
    $router->post('/citas/{id}/llegada', [CitaController::class, 'llegada'], 'cita.agendar');
    $router->post('/citas/{id}/iniciar', [CitaController::class, 'iniciar'], 'cita.atender');
    $router->post('/citas/{id}/resultado', [CitaController::class, 'resultado'], 'cita.atender');

    // Pacientes
    $router->get('/pacientes', [PacienteController::class, 'index'], 'paciente.ver');
    $router->get('/pacientes/nuevo', [PacienteController::class, 'nuevo'], 'paciente.editar');
    $router->post('/pacientes/nuevo', [PacienteController::class, 'crear'], 'paciente.editar');

    // Nacimientos
    $router->get('/nacimientos', [NacimientoController::class, 'index'], 'nacimiento.registrar');
    $router->post('/nacimientos', [NacimientoController::class, 'registrar'], 'nacimiento.registrar');

    // Prioridad de empleados
    $router->get('/prioridad', [PrioridadController::class, 'index'], 'cita.agendar');
    $router->post('/prioridad', [PrioridadController::class, 'aplicar'], 'cita.agendar');

    // Habitaciones: verlas, asignarlas (ingreso, salida y estado) y registrarlas (administración)
    $router->get('/habitaciones', [HabitacionController::class, 'index'], 'habitacion.ver');
    $router->post('/habitaciones', [HabitacionController::class, 'crear'], 'usuario.administrar');
    $router->post('/habitaciones/estadias/{id}/salida', [HabitacionController::class, 'darSalida'], 'habitacion.asignar');
    $router->post('/habitaciones/{id}', [HabitacionController::class, 'editar'], 'usuario.administrar');
    $router->post('/habitaciones/{id}/ingreso', [HabitacionController::class, 'ingresar'], 'habitacion.asignar');
    $router->post('/habitaciones/{id}/estado', [HabitacionController::class, 'cambiarEstado'], 'habitacion.asignar');

    // Médicos y especialidades: los ve quien ve las citas; los administra quien administra usuarios
    $router->get('/medicos', [MedicoController::class, 'index'], 'cita.ver');
    $router->post('/medicos', [MedicoController::class, 'registrar'], 'usuario.administrar');
    $router->post('/medicos/especialidades', [MedicoController::class, 'crearEspecialidad'], 'usuario.administrar');
    $router->post('/medicos/especialidades/{id}', [MedicoController::class, 'editarEspecialidad'], 'usuario.administrar');
    $router->post('/medicos/horarios/{id}/quitar', [MedicoController::class, 'quitarHorario'], 'usuario.administrar');
    $router->post('/medicos/{id}', [MedicoController::class, 'guardar'], 'usuario.administrar');
    $router->post('/medicos/{id}/horario', [MedicoController::class, 'agregarHorario'], 'usuario.administrar');

    // Medicamentos
    $router->get('/medicamentos', [MedicamentoController::class, 'index'], 'catalogo.ver');
    $router->get('/medicamentos/nuevo', [MedicamentoController::class, 'formulario'], 'catalogo.editar');
    $router->post('/medicamentos/guardar', [MedicamentoController::class, 'guardar'], 'catalogo.editar');
    $router->get('/medicamentos/{id}/editar', [MedicamentoController::class, 'formulario'], 'catalogo.editar');

    // Enfermedades
    $router->get('/enfermedades', [EnfermedadController::class, 'index'], 'catalogo.ver');
    $router->get('/enfermedades/nueva', [EnfermedadController::class, 'nueva'], 'catalogo.editar');
    $router->post('/enfermedades/nueva', [EnfermedadController::class, 'guardar'], 'catalogo.editar');
    $router->get('/enfermedades/categorias', [EnfermedadController::class, 'categorias'], 'catalogo.editar');
    $router->post('/enfermedades/categorias', [EnfermedadController::class, 'crearCategoria'], 'catalogo.editar');
    $router->post('/enfermedades/categorias/{id}', [EnfermedadController::class, 'editarCategoria'], 'catalogo.editar');

    // Empleados
    $router->get('/empleados', [EmpleadoController::class, 'index'], 'usuario.administrar');
    $router->post('/empleados', [EmpleadoController::class, 'crear'], 'usuario.administrar');
    $router->post('/empleados/puestos', [EmpleadoController::class, 'crearPuesto'], 'usuario.administrar');
    $router->post('/empleados/puestos/{id}', [EmpleadoController::class, 'guardarPuesto'], 'usuario.administrar');
    $router->post('/empleados/{id}', [EmpleadoController::class, 'guardar'], 'usuario.administrar');

    // Usuarios y roles
    $router->get('/usuarios', [UsuarioController::class, 'index'], 'usuario.administrar');
    $router->post('/usuarios', [UsuarioController::class, 'crear'], 'usuario.administrar');
    $router->get('/usuarios/{id}/editar', [UsuarioController::class, 'editar'], 'usuario.administrar');
    $router->post('/usuarios/{id}', [UsuarioController::class, 'guardar'], 'usuario.administrar');
    $router->post('/usuarios/{id}/clave', [UsuarioController::class, 'restablecerClave'], 'usuario.administrar');

    // Bitácoras
    $router->get('/bitacoras', [BitacoraController::class, 'index'], 'registro.ver');
    $router->get('/bitacoras/exportar', [BitacoraController::class, 'exportar'], 'registro.ver');
};
