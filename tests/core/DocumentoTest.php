<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Documento;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Formato oficial de los documentos de identidad de Costa Rica. */
final class DocumentoTest extends TestCase
{
    /** @return array<string, array{string, string, ?string}> */
    public static function casos(): array
    {
        return [
            'cédula junta' => ['CEDULA', '111111111', '1-1111-1111'],
            'cédula con guiones' => ['CEDULA', '1-1111-1111', '1-1111-1111'],
            'cédula con espacios' => ['CEDULA', '2 0456 0789', '2-0456-0789'],
            'cédula forma vieja con cero' => ['CEDULA', '01-1111-1111', '1-1111-1111'],
            'cédula corta' => ['CEDULA', '11111111', null],
            'cédula que empieza en cero' => ['CEDULA', '011111111', null],
            'cédula con letras' => ['CEDULA', '1-1111-111A', null],
            'DIMEX de 12' => ['DIMEX', '155812345678', '155812345678'],
            'DIMEX de 11 con espacios' => ['DIMEX', '15581 234567', '15581234567'],
            'DIMEX con cero al inicio' => ['DIMEX', '055812345678', null],
            'DIMEX de 10' => ['DIMEX', '1558123456', null],
            'DIMEX con letras' => ['DIMEX', '15581234567A', null],
            'pasaporte en minúsculas' => ['PASAPORTE', 'b1234 5678', 'B12345678'],
            'pasaporte solo números' => ['PASAPORTE', '123456789', '123456789'],
            'pasaporte de 10' => ['PASAPORTE', 'AB12345678', null],
            'pasaporte solo letras' => ['PASAPORTE', 'ABCDEFG', null],
            'pasaporte con símbolos' => ['PASAPORTE', 'B1234#678', null],
            'tipo desconocido' => ['NITE', '1234567890', null],
        ];
    }

    #[DataProvider('casos')]
    public function testFormatoOficial(string $tipo, string $numero, ?string $esperado): void
    {
        $this->assertSame($esperado, Documento::normalizar($tipo, $numero));
    }

    public function testUnaBusquedaSinTipoProbaraCadaFormaPosible(): void
    {
        $this->assertSame(['1-1111-1111', '111111111'], Documento::paraBuscar('111111111'));
        $this->assertSame(['155812345678'], Documento::paraBuscar('155812345678'));
        $this->assertSame(['101'], Documento::paraBuscar('101'));
    }
}
