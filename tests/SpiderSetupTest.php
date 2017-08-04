<?php

namespace DPRMC\Spider\Tests;

use DPRMC\Spider\Exceptions\ReadMeFileDoesNotExists;
use DPRMC\Spider\Spider;
use DPRMC\Spider\Exceptions\ReadMeFileNotWritten;
use org\bovigo\vfs\vfsStream;
use Exception;

/**
 * Class SpiderSetupTest
 * @package DPRMC\Spider\Tests
 */
class SpiderSetupTest extends SpiderTestCase {

    /**
     * @throws \Exception
     * @group setup
     */
    public function testConstructor() {
        $mockFileSystem = vfsStream::setup();
        $spider         = new Spider( $mockFileSystem->url(), true );
        $this->assertInstanceOf( Spider::class, $spider );
    }

    /**
     * @group setup
     */
    public function testConstructor_CanWriteLogFile() {
        $mockFileSystem  = vfsStream::setup();
        $spider          = new Spider( $mockFileSystem->url(), true );
        $logFileContents = $spider->getDebugLogFileContents();
        $this->assertNotEmpty( $logFileContents );
    }


    /**
     * @group setup
     */
    public function testConstructor_WithBadPathPermissions() {
        $this->expectException( Exception::class );
        $mockFileSystem = vfsStream::setup( 'root', 0000 );
        new Spider( $mockFileSystem->url() );
    }

    /**
     * @group setup
     */
    public function testConstructor_WithNoDiskSpace() {
        $this->expectException( ReadMeFileNotWritten::class );
        $mockFileSystem = vfsStream::setup( 'root', 0777 );
        vfsStream::setQuota( 2 );
        new Spider( $mockFileSystem->url() );
    }

    /**
     * @group setup
     */
    public function testConstructor_CanWriteReadMeFile() {
        $mockFileSystem     = vfsStream::setup();
        $spider             = new Spider( $mockFileSystem->url(), false );
        $readmeFileContents = $spider->getReadMeFileContents();
        $this->assertNotEmpty( $readmeFileContents );
    }

    /**
     * I delete the readme file before it's read from to force the Exception.
     * @group setup
     */
    public function testGetReadMeFileContents_ThrowsExceptionWhenMissingFile() {
        $this->expectException( ReadMeFileDoesNotExists::class );
        $mockFileSystem = vfsStream::setup();
        $spider         = new Spider( $mockFileSystem->url(), false );
        unlink( $mockFileSystem->url() . '/' . Spider::README_FILE_NAME );
        $spider->getReadMeFileContents();
    }
}