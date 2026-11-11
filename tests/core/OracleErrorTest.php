<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\BusinessException;
use PHPUnit\Framework\TestCase;

final class OracleErrorTest extends TestCase
{
    public function testErrorPropioSeLimpiaParaElUsuario(): void
    {
        $e = BusinessException::desdeOracle([
            'code' => 20201,
            'message' => "ORA-20201: No hay cupo para ese horario\nORA-06512: at \"HOSPITAL.PKG_CITA\", line 40",
        ]);

        $this->assertInstanceOf(BusinessException::class, $e);
        $this->assertSame('No hay cupo para ese horario', $e->getMessage());
        $this->assertSame(-20201, $e->oracleCode);
    }

    public function testErrorDelSistemaNoEsDeNegocio(): void
    {
        $e = BusinessException::desdeOracle(['code' => 600, 'message' => 'ORA-00600: internal error violated']);

        $this->assertFalse($e instanceof BusinessException);
        $this->assertSame(600, $e->getCode());
    }
}
