<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controlador;
use App\Core\Response;
use App\Core\View;
use App\Models\Inventario;

/** Inventario: existencias, entradas, productos, proveedores, categorías y exportación a Excel. */
final class InventarioController extends Controlador
{
    /** GET /inventario */
    public function index(): string
    {
        $inv = new Inventario();
        return View::render('inventario/index', [
            'titulo' => 'Inventario',
            'productos' => $inv->productos(consulta('q'), consulta('categoria')),
            'categorias' => $inv->categorias(),
            'proveedores' => $inv->proveedores(),
            'movimientos' => $inv->movimientosDeHoy(),
            'busqueda' => consulta('q'),
            'categoria' => consulta('categoria'),
        ]);
    }

    /** POST /inventario/entrada */
    public function entrada(): Response
    {
        $cantidad = (int) entrada('cantidad');
        if ((int) entrada('id_producto') <= 0 || (int) entrada('id_proveedor') <= 0 || $cantidad <= 0) {
            return $this->rechazar('Elige el producto y el proveedor, e indica una cantidad mayor que cero.', '/inventario');
        }
        return $this->ejecutar(
            static fn() => (new Inventario())->registrarEntrada(
                (int) entrada('id_producto'),
                (int) entrada('id_proveedor'),
                $cantidad,
                entrada('observacion'),
                (int) Auth::user()['id_usuario'],
            ),
            'Entrada registrada.',
            '/inventario',
        );
    }

    /** GET /inventario/catalogos · Proveedores y categorías de producto. */
    public function catalogos(): string
    {
        $inv = new Inventario();
        return View::render('inventario/catalogos', [
            'titulo' => 'Proveedores y categorías',
            'proveedores' => $inv->proveedores(),
            'categorias' => $inv->categorias(),
        ]);
    }

    /** POST /inventario/productos */
    public function crearProducto(): Response
    {
        return $this->guardarProducto(null);
    }

    /** POST /inventario/productos/{id} */
    public function editarProducto(string $id): Response
    {
        return $this->guardarProducto((int) $id);
    }

    /** POST /inventario/proveedores */
    public function crearProveedor(): Response
    {
        return $this->guardarProveedor(null);
    }

    /** POST /inventario/proveedores/{id} */
    public function editarProveedor(string $id): Response
    {
        return $this->guardarProveedor((int) $id);
    }

    /** POST /inventario/categorias */
    public function crearCategoria(): Response
    {
        return $this->guardarCategoria(null);
    }

    /** POST /inventario/categorias/{id} */
    public function editarCategoria(string $id): Response
    {
        return $this->guardarCategoria((int) $id);
    }

    /** GET /inventario/exportar · Descarga el inventario en CSV para Excel. */
    public function exportar(): Response
    {
        $salida = fopen('php://temp', 'w+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Código', 'Producto', 'Categoría', 'Uso', 'Existencias', 'Mínimo', 'Costo', 'Precio de venta'], ';', '"', '');
        foreach ((new Inventario())->productos() as $p) {
            fputcsv($salida, array_map('csv_seguro', [$p['codigo'], $p['nombre'], $p['categoria'], $p['uso'], $p['existencias'], $p['stock_minimo'], $p['costo'], $p['precio_venta']]), ';', '"', '');
        }
        rewind($salida);
        return new Response((string) stream_get_contents($salida), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="inventario.csv"',
        ]);
    }

    private function guardarProducto(?int $id): Response
    {
        $datos = self::producto();
        if (is_string($datos)) {
            return $this->rechazar($datos, '/inventario');
        }
        return $this->ejecutar(
            static fn() => (new Inventario())->guardarProducto($id, $datos),
            $id === null ? 'Producto agregado. Sus existencias empiezan en cero: súmalas con una entrada.' : 'Producto guardado.',
            '/inventario',
        );
    }

    private function guardarProveedor(?int $id): Response
    {
        $datos = self::proveedor();
        if (is_string($datos)) {
            return $this->rechazar($datos, '/inventario/catalogos');
        }
        return $this->ejecutar(
            static fn() => (new Inventario())->guardarProveedor($id, $datos),
            $id === null ? 'Proveedor agregado.' : 'Proveedor guardado.',
            '/inventario/catalogos',
        );
    }

    private function guardarCategoria(?int $id): Response
    {
        $nombre = entrada('nombre_categoria');
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            return $this->rechazar('Indica el nombre de la categoría (hasta 100 caracteres).', '/inventario/catalogos');
        }
        return $this->ejecutar(
            static fn() => (new Inventario())->guardarCategoria($id, $nombre),
            $id === null ? 'Categoría agregada.' : 'Categoría guardada.',
            '/inventario/catalogos',
        );
    }

    /**
     * Datos de un producto tal como llegan del formulario, ya revisados.
     * También los usa el formulario de medicamentos para crear su producto (sin categoría).
     *
     * @return array{id_categoria_producto: int, codigo: string, nombre: string, descripcion: string, costo: float, precio_venta: ?float, stock_minimo: int, se_vende: string}|string
     */
    public static function producto(bool $conCategoria = true): array|string
    {
        $seVende = isset($_POST['se_vende']) ? 'S' : 'N';
        $precio = entrada('precio_venta');
        $datos = [
            'id_categoria_producto' => (int) entrada('id_categoria_producto'),
            'codigo' => mb_strtoupper(entrada('codigo')),
            'nombre' => entrada('nombre_producto'),
            'descripcion' => entrada('descripcion'),
            'costo' => numero(entrada('costo')),
            'precio_venta' => $precio === '' ? null : numero($precio),
            'stock_minimo' => (int) entrada('stock_minimo'),
            'se_vende' => $seVende,
        ];
        if ($datos['codigo'] === '' || $datos['nombre'] === '' || ($conCategoria && $datos['id_categoria_producto'] <= 0)) {
            return $conCategoria ? 'Indica la categoría, el código y el nombre del producto.' : 'Indica el código y el nombre del producto.';
        }
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,29}$/', $datos['codigo'])) {
            return 'El código solo lleva letras, números y guiones (hasta 30 caracteres).';
        }
        if (!self::esMonto(entrada('costo')) || ($precio !== '' && !self::esMonto($precio))) {
            return 'El costo y el precio de venta deben ser montos de cero o más.';
        }
        if ($seVende === 'S' && $precio === '') {
            return 'Un producto que se vende necesita precio de venta.';
        }
        if (!ctype_digit(entrada('stock_minimo'))) {
            return 'El mínimo de existencias debe ser un número entero, cero o más.';
        }
        return $datos;
    }

    /**
     * Datos del proveedor, con la cédula jurídica (10 dígitos) y el teléfono (8 dígitos) revisados.
     *
     * @return array{nombre: string, cedula_juridica: string, telefono: string, correo: string, direccion: string}|string
     */
    private static function proveedor(): array|string
    {
        $cedula = (string) preg_replace('/\D/', '', entrada('cedula_juridica'));
        $telefono = (string) preg_replace('/\D/', '', entrada('telefono'));
        $datos = [
            'nombre' => entrada('nombre_proveedor'),
            'cedula_juridica' => $cedula,
            'telefono' => $telefono,
            'correo' => mb_strtolower(entrada('correo')),
            'direccion' => entrada('direccion'),
        ];
        if ($datos['nombre'] === '') {
            return 'Indica el nombre del proveedor.';
        }
        if (strlen($cedula) !== 10) {
            return 'La cédula jurídica debe tener 10 dígitos, por ejemplo 3-101-123456.';
        }
        if ($telefono !== '' && strlen($telefono) !== 8) {
            return 'El teléfono debe tener 8 dígitos.';
        }
        if ($datos['correo'] !== '' && filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return 'El correo no tiene un formato válido.';
        }
        return $datos;
    }

    private static function esMonto(string $valor): bool
    {
        return preg_match('/^\d{1,10}([.,]\d{1,2})?$/', $valor) === 1;
    }
}
