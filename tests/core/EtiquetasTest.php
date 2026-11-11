<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Etiquetas;
use PHPUnit\Framework\TestCase;

/** Ningún nombre interno de la base de datos debe llegar a las pantallas. */
final class EtiquetasTest extends TestCase
{
    public function testTraducePermisosYRoles(): void
    {
        $this->assertSame('Registrar nacimientos', Etiquetas::permiso('nacimiento.registrar'));
        $this->assertSame('Enfermería', Etiquetas::rol('enfermeria'));
    }

    public function testUnNombreNuevoSaleLegibleAunqueNoEsteTraducido(): void
    {
        $this->assertSame('Horario médico', Etiquetas::legible('HORARIO_MEDICO'));
        $this->assertSame('Reporte mensual', Etiquetas::proceso('pkg_nuevo.sp_reporte_mensual'));
    }

    public function testElProcesoSeTraduceConOSinPaquete(): void
    {
        $this->assertSame('Crear pedido', Etiquetas::proceso('sp_pedido_crear'));
        $this->assertSame('Crear pedido', Etiquetas::proceso('pkg_venta.sp_pedido_crear'));
    }

    public function testLosValoresOcultanIdentificadoresYClaves(): void
    {
        $this->assertSame(
            'Estado: En cobro · Nota: a; b',
            Etiquetas::valores('ID_PEDIDO=5; ESTADO=EN_COBRO; CLAVE_HASH=abc; NOTA=a; b; '),
        );
    }

    public function testLosMontosDeLaBitacoraSeVenEnColones(): void
    {
        $this->assertSame("Total pagar: ₡1\u{00A0}500,50 · Cantidad: 3", Etiquetas::valores('TOTAL_PAGAR=1500.5; CANTIDAD=3; '));
    }

    public function testElMensajePropioSeMuestraYElInternoNo(): void
    {
        $this->assertSame('No hay cupo.', Etiquetas::mensaje("ORA-20210: No hay cupo.\nORA-06512: at line 1"));
        $this->assertSame(
            'Error interno de la aplicación.',
            Etiquetas::mensaje('ORA-00001: unique constraint (HOSPITAL.UK_USUARIO) violated'),
        );
    }

    public function testCeldasYRastroDeBitacora(): void
    {
        $this->assertSame('Creación', Etiquetas::bitacora('accion', 'INSERT'));
        $this->assertSame('Intento fallido', Etiquetas::bitacora('evento', 'FALLIDO'));
        $this->assertSame('Pedido 5', Etiquetas::bitacora('referencia', 'PEDIDO 5'));
        $this->assertSame('Consulta', Etiquetas::bitacora('tipo_objeto', 'Vista'));
        $this->assertSame('Crear pedido: inicio', Etiquetas::tituloRastro('sp_pedido_crear: inicio'));
    }
}
