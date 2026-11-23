<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\BusinessException;
use App\Core\Formato;
use App\Core\HttpException;
use App\Core\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Teléfonos y nombres que se escriben a mano, ids de las rutas y errores de datos de Oracle. */
final class FormatoTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function telefonos(): array
    {
        return [
            'con guion' => ['8888-0000', '8888-0000'],
            'solo dígitos' => ['22223333', '2222-3333'],
            'con espacios' => [' 8888 0000 ', '8888-0000'],
            'vacío' => ['', ''],
            '7 dígitos' => ['8888-000', null],
            '9 dígitos' => ['8888-00000', null],
            'con letras' => ['8888-OOOO', null],
            'con código de país' => ['+506 8888-0000', null],
        ];
    }

    #[DataProvider('telefonos')]
    public function testElTelefonoTieneOchoDigitos(string $escrito, ?string $guardado): void
    {
        $this->assertSame($guardado, Formato::telefono($escrito));
    }

    /** @return array<string, array{string, bool}> */
    public static function nombres(): array
    {
        return [
            'simple' => ['Ana', true],
            'con tildes y eñe' => ['Ñeco Solís', true],
            'compuesto con guion' => ['María-José', true],
            'con apóstrofo' => ["O'Neil", true],
            'con número' => ['Ana2', false],
            'solo números' => ['12345', false],
            'empieza con espacio o símbolo' => ['-Ana', false],
            'vacío' => ['', false],
            'con arroba' => ['ana@hospital', false],
        ];
    }

    #[DataProvider('nombres')]
    public function testLosNombresNoLlevanNumeros(string $nombre, bool $valido): void
    {
        $this->assertSame($valido, Formato::esNombre($nombre));
    }

    public function testUnIdDeRutaQueNoEsNumeroDa404(): void
    {
        $router = (new Router())->post('/inventario/productos/{id}', [self::class, 'x'], 'inventario.ajustar');

        $this->assertSame(['id' => '7'], $router->match('POST', '/inventario/productos/7')['params']);
        try {
            $router->match('POST', '/inventario/productos/abc');
            $this->fail('Debió dar 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status);
        }
    }

    /** @return array<string, array{int}> */
    public static function erroresDeDatos(): array
    {
        return ['dato obligatorio vacío' => [1400], 'valor fuera de la lista' => [2290], 'registro con dependientes' => [2292]];
    }

    #[DataProvider('erroresDeDatos')]
    public function testLosErroresDeDatosSeExplicanSinDetalleTecnico(int $codigo): void
    {
        $e = BusinessException::desdeOracle(['code' => $codigo, 'message' => "ORA-0{$codigo}: HOSPITAL.PRODUCTO.PRECIO_VENTA"]);

        $this->assertInstanceOf(BusinessException::class, $e);
        $this->assertStringNotContainsString('ORA-', $e->getMessage());
        $this->assertStringNotContainsString('HOSPITAL', $e->getMessage());
    }
}
