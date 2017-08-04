<?php

namespace DPRMC\Spider\Tests;

use Exception;
use GuzzleHttp\Exception\ConnectException;
use DPRMC\Spider\Step;

class SpiderRunTest extends SpiderTestCase {


    /**
     * @link http://httpstat.us/ A service used to return HTTP status codes of your choice.
     */
    public function testRun_ShouldThrowConnectException() {
        $this->expectException( ConnectException::class );
        $spider = $this->getSpiderWithUnlimitedDiskSpace();
        $step   = new Step();
        //$step->setUrl( 'http://httpstat.us/522' );
        $step->setUrl( 'www.google.com:81' );
        $step->setTimeout( 1 );

        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );
        $spider->run();
    }


    public function testRun_ShouldThrowException() {
        // SET UP
        $sinkRoot = __DIR__ . DIRECTORY_SEPARATOR . 'files';
        $sink     = $sinkRoot . DIRECTORY_SEPARATOR . 'test.txt';
        if ( ! file_exists( $sinkRoot ) ):
            mkdir( $sinkRoot, 0777 );
        endif;

        $this->expectException( Exception::class );
        $spider = $this->getSpiderWithSetDiskSpace();
        $step   = new Step();
        //$step->setUrl( 'http://httpstat.us/522' );
        $step->setUrl( 'www.google.com:81' );
        $step->setTimeout( 1 );

        $stepName = 'testStep';
        $spider->addStep( $step, $stepName );

        // FORCE EXCEPTION
        unlink( $sink );
        rmdir( $sinkRoot );

        $spider->run();
    }


}