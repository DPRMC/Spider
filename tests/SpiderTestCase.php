<?php

namespace Dprc\Spider\Tests;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use Dprc\Spider\Spider;

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

    /**
     * @param bool $debug
     *
     * @return Spider
     */
    protected function getSpiderWithUnlimitedDiskSpace( $debug = FALSE ) {
        $mockFileSystem    = vfsStream::setup( 'root' );
        $mockFileSystemUrl = $mockFileSystem->url( 'root' );
        vfsStream::setQuota( -1 );

        return new Spider( $mockFileSystemUrl, TRUE );
    }

    /**
     * Create a mock filesystem with VERY limited space.
     *
     * @param bool $debug
     *
     * @return Spider
     */
    protected function getSpiderWithLimitedDiskSpace( $debug = FALSE ) {
        $mockFileSystem    = vfsStream::setup( 'root' );
        $mockFileSystemUrl = $mockFileSystem->url( 'root' );
        vfsStream::setQuota( 1 );

        return @new Spider( $mockFileSystemUrl, TRUE );
    }
}