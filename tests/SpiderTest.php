<?php

namespace Dprc\Spider\Tests;

use Dprc\Spider\Spider;
use Dprc\Spider\Step;
use Dprc\Spider\Exceptions\DebugDirectoryNotWritable;

use org\bovigo\vfs\vfsStream;

use Exception;

class SpiderTest extends SpiderTestCase {

    public function testConstructor() {
        $mockFileSystem = vfsStream::setup( 'root', 0777 );
        $spider         = new Spider( $mockFileSystem->url() );
        $this->assertInstanceOf( Spider::class, $spider );
    }

    public function testConstructorWithBadPathPermissions() {
        $this->expectException( Exception::class );
        $mockFileSystem = vfsStream::setup( 'root', 0000 );
        new Spider( $mockFileSystem->url() );
    }

    public function testConstructorWithNoDiskSpace() {
        $this->expectException( DebugDirectoryNotWritable::class );
        $mockFileSystem = vfsStream::setup( 'root', 0777 );
        vfsStream::setQuota( 2 );
        new Spider( $mockFileSystem->url() );
    }

    public function testAddStepWithName() {
        $mockFileSystem = vfsStream::setup();
        $spider         = new Spider( $mockFileSystem->url() );
        $step           = new Step();
        $stepName       = 'testStep';

        $spider->addStep( $step, $stepName );
        $steps    = $spider->getSteps();
        $numSteps = count( $steps );
        $this->assertEquals( 1, $numSteps );
    }

    public function testAddStepWithNoName() {
        $mockFileSystem = vfsStream::setup();
        $spider         = new Spider( $mockFileSystem->url() );
        $step           = new Step();
        $stepName       = 'testStepName';

        $spider->addStep( $step, $stepName );
        $steps    = $spider->getSteps();
        $numSteps = count( $steps );
        $this->assertEquals( 1, $numSteps );
    }

    public function testGetStep() {
        $mockFileSystem = vfsStream::setup();
        $spider         = new Spider( $mockFileSystem->url() );
        $step           = new Step();
        $stepName       = 'testStep';
        $spider->addStep( $step, $stepName );
        $step = $spider->getStep( $stepName );
        $this->assertInstanceOf( Step::class, $step );
    }

    public function testRemoveAllSteps() {
        $mockFileSystem = vfsStream::setup();
        $spider         = new Spider( $mockFileSystem->url() );
        $step           = new Step();
        $stepName       = 'testStep';
        $spider->addStep( $step, $stepName );
        $spider->removeAllSteps();
        $steps    = $spider->getSteps();
        $numSteps = count( $steps );
        $this->assertEquals( 0, $numSteps );
    }


    //    public function testGetRequestDebugFileName() {
    //        $mockFileSystem = vfsStream::setup();
    //        $spider         = new Spider( $mockFileSystem->url() );
    //        $stepName       = 'test';
    //        $time           = time();
    //        $expected       = 'request_' . $time . '_' . $stepName . '.dprc';
    //        $debugFileName  = $spider->debugGetRequestDebugFileName( $stepName );
    //        $this->assertEquals( $expected, $debugFileName );
    //    }

    public function testSaveResponseBodyInDebugFolder() {
        $mockFileSystem    = vfsStream::setup();
        $mockFileSystemUrl = $mockFileSystem->url();
        echo "\n\n\n";
        var_dump( $mockFileSystemUrl );
        echo "\n\n\n";
        $spider             = new Spider( $mockFileSystemUrl, TRUE );
        $responseBodyString = "This is the response body.";
        $stepName           = 'testStepName';

        var_dump( $spider );

        //$spider->debugSaveResponseBodyInDebugFolder( $responseBodyString, $stepName );
        $this->invokeMethod( $spider, 'debugSaveResponseBodyInDebugFolder', [ $responseBodyString,
                                                                              $stepName ] );

        $requestDebugFileName = $this->invokeMethod( $spider, 'debugGetRequestDebugFileName', [ $stepName ] );
        var_dump( $requestDebugFileName );
        //$fileName = $mockFileSystem->url( $spider->debugGetRequestDebugFileName( $stepName ) );
        $fileName = vfsStream::url( $requestDebugFileName );
        var_dump( $fileName );
        var_dump( vfsStream::url( 'README.md' ) );
        var_dump( file( vfsStream::url( 'README.md' ) ) );

        $this->assertTrue( file_exists( $fileName ) );
    }

    public function testSaveResponseToLocalFile() {
        $this->assertTrue( TRUE );
    }


}