<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Etiquetas;
use App\Core\OciDatabase;
use PHPUnit\Framework\TestCase;

/** Nombre con el que cada consulta queda en la bitácora de tiempos. */
final class NombreConsultaTest extends TestCase
{
    public function testUnaConsultaSeAnotaConSuVista(): void
    {
        $this->assertSame('vw_cita_agenda_dia', OciDatabase::nombreConsulta('SELECT hora FROM vw_cita_agenda_dia ORDER BY hora'));
    }

    public function testUnaConsultaQueUneVariasVistasLasNombraTodas(): void
    {
        $sql = "SELECT 'a' FROM vw_cita_sala_espera UNION ALL SELECT 'b' FROM VW_REGISTRO_AVISOS UNION ALL SELECT 'c' FROM vw_cita_sala_espera";

        $this->assertSame('vw_cita_sala_espera, vw_registro_avisos', OciDatabase::nombreConsulta($sql));
        $this->assertSame('Cita sala espera, Registro avisos', Etiquetas::proceso(OciDatabase::nombreConsulta($sql)));
    }

    public function testElErrorDeUnaPantallaSeMuestraTalCual(): void
    {
        $this->assertSame('Formulario /empleados', Etiquetas::proceso('Formulario /empleados'));
    }
}
