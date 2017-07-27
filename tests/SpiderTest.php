<?php

namespace Dprc\Spider\Tests;

use Dprc\Spider\Exceptions\IndexNotFoundInResponsesArray;
use Dprc\Spider\Spider;
use Dprc\Spider\Step;
use Dprc\Spider\Exceptions\DebugDirectoryNotWritable;
use Dprc\Spider\Exceptions\UnableToWriteResponseBodyInDebugFolder;

use GuzzleHttp\Client;
use org\bovigo\vfs\content\LargeFileContent;
use org\bovigo\vfs\vfsStream;

use Exception;
use ReflectionClass;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

class SpiderTest extends SpiderTestCase {

    public function testConstructor() {
        ini_set( 'memory_limit', -1 );
        $mockFileSystem = vfsStream::setup();

        $adapter         = new Local( $mockFileSystem->url(), 0 );
        $localFilesystem = new Filesystem( $adapter );

        $runFolderName      = 'run_' . date( 'YmdHis' );
        $pathToRunDirectory = $mockFileSystem->path( 'root' . DIRECTORY_SEPARATOR . $runFolderName );

        $dirWasCreated = $localFilesystem->createDir( $pathToRunDirectory );
        if ( $dirWasCreated === FALSE ):
            throw new Exception( "Flysystem->createDir() returned false." );
        endif;


        $spider = new Spider( $mockFileSystem->url(), TRUE );
        $this->assertInstanceOf( Spider::class, $spider );


        //$pathToRunDirectory = vfsStream::url( $spider->getPathToRunDirectory() );
        // This is a fix for Windows' DIRECTORY_SEPARATOR
        //$pathToRunDirectory = vfsStream::url(vfsStream::path( $spider->getPathToRunDirectory() ));
        //$pathToRunDirectory = $spider->getPathToRunDirectory();

        //var_dump($pathToRunDirectory);
        //$debugFileContents = $spider->getDebugLogContents();
        //var_dump($debugFileContents);

        //$this->assertTrue( is_dir( $pathToRunDirectory ) );
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

    public function testLog() {
        $spider     = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $message    = "This is a test log entry.";
        $logWritten = $this->invokeMethod( $spider, 'log', [ $message ] );
        $this->assertTrue( $logWritten );
    }

    public function testAddStepWithName() {
        $mockFileSystem = vfsStream::setup();
        vfsStream::setQuota( -1 );
        $spider   = new Spider( $mockFileSystem->url() );
        $step     = new Step();
        $stepName = 'testStep';

        $spider->addStep( $step, $stepName );
        $steps    = $spider->getSteps();
        $numSteps = count( $steps );
        $this->assertEquals( 1, $numSteps );
    }

    public function testAddStepWithNoName() {
        $spider   = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $step     = new Step();
        $stepName = 'testStepName';

        $spider->addStep( $step, $stepName );
        $steps    = $spider->getSteps();
        $numSteps = count( $steps );
        $this->assertEquals( 1, $numSteps );
    }

    public function testGetStep() {
        $spider   = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $step     = new Step();
        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );
        $step = $spider->getStep( $stepName );
        $this->assertInstanceOf( Step::class, $step );
    }

    public function testRemoveAllSteps() {
        $spider   = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $step     = new Step();
        $stepName = 'testStep';
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
        $spider             = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $responseBodyString = "This is the response body.";
        $stepName           = 'testStepName';

        $written = $this->invokeMethod( $spider, 'debugSaveResponseBodyInDebugFolder', [ $responseBodyString,
                                                                                         $stepName ] );
        $this->assertTrue( $written );

        $requestDebugFileName    = $this->invokeMethod( $spider, 'debugGetRequestDebugFileName', [ $stepName ] );
        $absolutePathToDebugFile = vfsStream::url( 'root' . DIRECTORY_SEPARATOR . $requestDebugFileName );

        $this->assertTrue( file_exists( $absolutePathToDebugFile ) );
    }

    public function testSaveResponseBodyInDebugFolderWithDebugTurnedOff() {
        $spider             = $this->getSpiderWithUnlimitedDiskSpace( FALSE );
        $responseBodyString = "This is the response body.";
        $stepName           = 'testStepName';

        $written = $this->invokeMethod( $spider, 'debugSaveResponseBodyInDebugFolder', [ $responseBodyString,
                                                                                         $stepName ] );
        $this->assertTrue( $written );
    }


    public function testSaveResponseBodyInDebugFolderDirectoryNotWritable() {
        $this->expectException( DebugDirectoryNotWritable::class );
        $spider             = $this->getSpiderWithLimitedDiskSpace( TRUE );
        $responseBodyString = "This is the response body that should never get written.";
        $stepName           = 'testStepName';

        $written = $this->invokeMethod( $spider, 'debugSaveResponseBodyInDebugFolder', [ $responseBodyString,
                                                                                         $stepName ] );
        $this->assertTrue( $written );

        //$requestDebugFileName    = $this->invokeMethod( $spider, 'debugGetRequestDebugFileName', [ $stepName ] );
        //$absolutePathToDebugFile = vfsStream::url( 'root' . DIRECTORY_SEPARATOR . $requestDebugFileName );
    }

    /**
     * This test should trigger an Exception if you try to write a file to the filesystem that won't fit.
     */
    public function testSaveResponseBodyInDebugFolderWriteFailure() {
        $this->expectException( UnableToWriteResponseBodyInDebugFolder::class );

        $mockFileSystem = vfsStream::setup( 'root' );

        $largeFile         = vfsStream::newFile( 'tooLarge.txt' )
                                      ->withContent( LargeFileContent::withKilobytes( 2 ) )
                                      ->at( $mockFileSystem );
        $mockFileSystemUrl = $mockFileSystem->url( 'root' );

        $spider = new Spider( $mockFileSystemUrl, TRUE );

        // Set the disk quota for my virtual file system to 1KB. The write should fail now since the file is 2 KB.
        vfsStream::setQuota( 1024 );
        $stepName = 'testStepName';

        $written = $this->invokeMethod( $spider, 'debugSaveResponseBodyInDebugFolder', [ file_get_contents( $largeFile->url() ),
                                                                                         $stepName ] );
    }


    public function testDebugGetLogPath() {
        $spider    = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $pathToLog = $this->invokeMethod( $spider, 'debugGetLogPath', [] );
        $this->assertNotEmpty( $pathToLog );
    }


    public function testSaveResponseToLocalFile() {
        $spider = $this->getSpiderWithUnlimitedDiskSpace( TRUE );

        $this->assertTrue( TRUE );
    }


    public function testNumResponses() {
        $spider = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $actual = $this->invokeMethod( $spider, 'numResponses', [] );
        $this->assertEquals( 0, $actual );
    }

    public function testGetLocalFilesWritten() {
        $spider               = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $localFilesWritten    = $spider->getLocalFilesWritten();
        $numLocalFilesWritten = count( $localFilesWritten );
        $this->assertEquals( 0, $numLocalFilesWritten );
    }

    public function testGetClient() {
        $spider = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $client = $spider->getClient();
        $this->assertInstanceOf( Client::class, $client );
    }


    public function testGetResponseFromInvalidStepName() {
        $this->expectException( IndexNotFoundInResponsesArray::class );
        $spider = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $spider->getResponse( 'invalidStepName' );
    }

    public function testGetResponseFromValidStepName() {
        $spider   = $this->getSpiderWithUnlimitedDiskSpace( TRUE );
        $step     = new Step();
        $step->setUrl( 'http://google.com' );
        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );
        $spider->run();
        print_r( $spider );
    }


}