<?php

namespace Dprc\Spider\Tests;

use PHPUnit\Framework\TestCase;

class SpiderTestCase extends TestCase {
    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod( &$object, $methodName, array $parameters = [] ) {
        $reflection = new \ReflectionClass( get_class( $object ) );
        $method     = $reflection->getMethod( $methodName );
        $method->setAccessible( TRUE );

        return $method->invokeArgs( $object, $parameters );
    }
}