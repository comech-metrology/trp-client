<?php

use PHPUnit\Framework\TestCase;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @covers CoMech\TRP\API
 */
final class APITest extends TestCase
{
    public function testCheckCertValidityOnConstruction()
    {
        $this->expectException(\CoMech\TRP\Exception::class);

	$test = new \CoMech\TRP\API("invalid-cert");
    }

}
