<?php

namespace DPRMC\Spider\Tests;

use DPRMC\Spider\Exceptions\DebugLogFileDoesNotExist;
use DPRMC\Spider\Exceptions\IndexNotFoundInResponsesArray;
use DPRMC\Spider\Exceptions\ReadMeFileDoesNotExists;
use DPRMC\Spider\Spider;
use DPRMC\Spider\Step;
use DPRMC\Spider\Exceptions\ReadMeFileNotWritten;
use DPRMC\Spider\Exceptions\UnableToWriteResponseBodyInDebugFolder;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use org\bovigo\vfs\content\LargeFileContent;
use org\bovigo\vfs\vfsStream;
use Exception;
use GuzzleHttp\Exception\ConnectException;


class SpiderMethodsTest extends SpiderTestCase {

    protected $sinkRoot;
    protected $sinkFilePath;
    protected $sink;

    /**
     * @var Spider $spider
     */
    protected $spider;


    public function testSetSink() {

        // setup
        $sinkRoot = __DIR__ . DIRECTORY_SEPARATOR . 'files';
        $sink     = $sinkRoot . DIRECTORY_SEPARATOR . 'test.txt';
        if ( ! file_exists( $sinkRoot ) ):
            mkdir( $sinkRoot, 0777 );
        endif;

        $spider = $this->getSpiderWithUnlimitedDiskSpace( true );
        $spider->setSink( $sink );
        $sinkFromSpider = $spider->getSink();
        $this->assertEquals( $sink, $sinkFromSpider );

        $step = new Step();
        $step->setUrl( 'http://google.com' );
        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );
        $spider->run();

        // teardown
        unset( $spider );
        unset( $step );
        unlink( $sink );

        rmdir( $sinkRoot );
    }

    /**
     * @group methods
     */
    public function testGetSink() {
        $step = new Step();
        $step->setUrl( 'http://google.com' );
        $stepName = 'testStep';
        $this->spider->addStep( $step, $stepName );
        $this->spider->run();
    }

    public function testLog() {
        $spider     = $this->getSpiderWithUnlimitedDiskSpace( true );
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
        $spider   = $this->getSpiderWithUnlimitedDiskSpace( true );
        $step     = new Step();
        $stepName = 'testStepName';

        $spider->addStep( $step, $stepName );
        $steps    = $spider->getSteps();
        $numSteps = count( $steps );
        $this->assertEquals( 1, $numSteps );
    }

    public function testGetStep() {
        $spider   = $this->getSpiderWithUnlimitedDiskSpace( true );
        $step     = new Step();
        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );
        $step = $spider->getStep( $stepName );
        $this->assertInstanceOf( Step::class, $step );
    }

    public function testRemoveAllSteps() {
        $spider   = $this->getSpiderWithUnlimitedDiskSpace( true );
        $step     = new Step();
        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );
        $spider->removeAllSteps();
        $steps    = $spider->getSteps();
        $numSteps = count( $steps );
        $this->assertEquals( 0, $numSteps );
    }

    public function testSaveResponseBodyInDebugFolder() {
        $spider             = $this->getSpiderWithUnlimitedDiskSpace( true );
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
        $spider             = $this->getSpiderWithUnlimitedDiskSpace( false );
        $responseBodyString = "This is the response body.";
        $stepName           = 'testStepName';

        $written = $this->invokeMethod( $spider, 'debugSaveResponseBodyInDebugFolder', [ $responseBodyString,
                                                                                         $stepName ] );
        $this->assertTrue( $written );
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

    public function testSaveResponseBodyInDebugFolderDirectoryNotWritable() {
        $this->expectException( ReadMeFileNotWritten::class );
        $spider             = $this->getSpiderWithLimitedDiskSpace( true );
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

        $spider = new Spider( $mockFileSystemUrl, true );

        // Set the disk quota for my virtual file system to 1KB. The write should fail now since the file is 2 KB.
        vfsStream::setQuota( 1024 );
        $stepName = 'testStepName';

        $written = $this->invokeMethod( $spider, 'debugSaveResponseBodyInDebugFolder', [ file_get_contents( $largeFile->url() ),
                                                                                         $stepName ] );
    }

    public function testSaveResponseToLocalFile() {
        $spider = $this->getSpiderWithUnlimitedDiskSpace( true );

        $this->assertTrue( true );
    }

    public function testNumResponses() {
        $spider = $this->getSpiderWithUnlimitedDiskSpace( true );
        $actual = $this->invokeMethod( $spider, 'numResponses', [] );
        $this->assertEquals( 0, $actual );
    }

    public function testGetLocalFilesWritten() {
        $spider               = $this->getSpiderWithUnlimitedDiskSpace( true );
        $localFilesWritten    = $spider->getLocalFilesWritten();
        $numLocalFilesWritten = count( $localFilesWritten );
        $this->assertEquals( 0, $numLocalFilesWritten );
    }

    public function testGetClient() {
        $spider = $this->getSpiderWithUnlimitedDiskSpace( true );
        $client = $spider->getClient();
        $this->assertInstanceOf( Client::class, $client );
    }

    public function testGetResponseFromInvalidStepName() {
        $this->expectException( IndexNotFoundInResponsesArray::class );
        $spider = $this->getSpiderWithUnlimitedDiskSpace( true );
        $spider->getResponse( 'invalidStepName' );
    }

    public function testGetResponseFromValidStepName() {
        $spider = $this->getSpiderWithUnlimitedDiskSpace( true );
        $step   = new Step();
        $step->setUrl( 'http://google.com' );
        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );
        $spider->run();
        $response = $spider->getResponse( $stepName );
        $this->assertInstanceOf( Response::class, $response );
    }

    protected function setUp() {
        $this->sinkRoot = __DIR__ . DIRECTORY_SEPARATOR . 'files';
        if ( ! file_exists( $this->sinkRoot ) ):
            mkdir( $this->sinkRoot, 0777 );
        endif;

        $this->sinkFilePath = $this->sinkRoot . DIRECTORY_SEPARATOR . 'test.txt';
        if ( file_exists( $this->sinkFilePath ) ):
            unlink( $this->sinkFilePath );
        endif;

        $this->sink = fopen( $this->sinkFilePath, 'w+' );

        $this->spider = $this->getSpiderWithUnlimitedDiskSpace( true );
    }

    protected function tearDown() {
        fclose( $this->sink );

        if ( file_exists( $this->sinkFilePath ) ):
            unlink( $this->sinkFilePath );
        endif;

        if ( file_exists( $this->sinkRoot ) ):
            rmdir( $this->sinkRoot );
        endif;
    }


}