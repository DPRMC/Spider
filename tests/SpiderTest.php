<?php

namespace Dprc\Spider\Tests;

use Dprc\Spider\Spider;
use Dprc\Spider\Step;
use Dprc\Spider\Exceptions\DebugDirectoryNotWritable;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Exception;

class SpiderTest extends TestCase {

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

        $spider->addStep( $step );
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


    public function testGetRequestDebugFileName() {
        $mockFileSystem = vfsStream::setup();
        $spider         = new Spider( $mockFileSystem->url() );
        $stepName       = 'test';
        $time           = time();
        $expected       = 'request_' . $time . '_' . $stepName . '.dprc';
        $debugFileName  = $spider->getRequestDebugFileName( $stepName );
        $this->assertEquals( $expected, $debugFileName );
    }

    public function testSaveResponseBodyInDebugFolder() {
        $mockFileSystem     = vfsStream::setup();
        $spider             = new Spider( $mockFileSystem->url() );
        $responseBodyString = "This is the response body.";
        $stepName           = 'testStepName';
        $spider->saveResponseBodyInDebugFolder( $responseBodyString, $stepName );
        $fileName = $mockFileSystem->url( $spider->getRequestDebugFileName( $stepName ) );
        $this->assertTrue( file_exists( $fileName ) );
    }


}