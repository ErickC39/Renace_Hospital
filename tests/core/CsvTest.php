<?php

declare(strict_types=1);

namespace Tests\Core;

use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    public function testUnTextoConFormulaSeNeutraliza(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", csv_seguro('=HYPERLINK("x")'));
        $this->assertSame("'@SUMA(A1)", csv_seguro('@SUMA(A1)'));
    }

    public function testUnaComillaEscapadaNoRompeLaCeldaDelCsv(): void
    {
        $archivo = fopen('php://temp', 'w+');
        fputcsv($archivo, [csv_seguro('a\";=cmd'), 'x'], ';', '"', '');
        rewind($archivo);

        $this->assertSame("\"a\\\"\";=cmd\";x\n", stream_get_contents($archivo));
    }

    public function testLosNumerosYTextosNormalesNoCambian(): void
    {
        $this->assertSame('-20001', csv_seguro(-20001));
        $this->assertSame('Corsaav', csv_seguro('Corsaav'));
        $this->assertSame('', csv_seguro(null));
    }
}
