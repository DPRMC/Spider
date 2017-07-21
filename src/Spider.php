<?php
// http://docs.guzzlephp.org/en/latest/quickstart.html
namespace Dprc\Spider;


use Dprc\Spider\Exceptions\UnableToSetVisibilityOfDebugRunDirectory;
use Exception;
use Dprc\Spider\Exceptions\FailureRuleTriggeredException;

use Dprc\Spider\Exceptions\DebugDirectoryAlreadySet;
use Dprc\Spider\Exceptions\DebugDirectoryNotSet;
use Dprc\Spider\Exceptions\DebugDirectoryNotWritable;
use Dprc\Spider\Exceptions\IndexNotFoundInResponsesArray;
use Dprc\Spider\Exceptions\UnableToCreateDebugRunDirectory;
use Dprc\Spider\Exceptions\UnableToWriteLogFile;
use Dprc\Spider\Exceptions\UnableToWriteResponseBodyInDebugFolder;
use Dprc\Spider\Exceptions\UnableToWriteResponseBodyToLocalFile;
use Dprc\Spider\Exceptions\UnableToFindStepWithStepName;

use Dprc\Spider\Step;

/**
 * http://guzzle3.readthedocs.io/http-client/client.html
 */
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\File;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;


/**
 * Class Spider
 * @package Dprc\Spider
 */
class Spider {
    /**
     *
     */
    const DEBUG_LOG_FILE_NAME = 'debug.log';
    /**
     * @var \GuzzleHttp\Cookie\CookieJar
     */
    public $cookie_jar;
    /**
     * @var Client The Guzzle HTTP client.
     */
    protected $client;
    /**
     * @var array An array of the responses from each step. Indexed by step name.
     */
    protected $responses = [];
    /**
     * @var array My Step objects indexed by step name.
     */
    protected $steps = [];
    /**
     * @var string Want to save the body of the response to a file? This is the absolute path to that location.
     */
    protected $sink;
    /**
     * @var string The absolute path to the debug directory.
     */
    protected $pathToDebugDirectory;
    /**
     * @var int Increment after every run_step() call
     */
    protected $numberOfStepsExecuted = 0;
    /**
     * @var bool Do you want to enable debugging for this spider.
     */
    protected $debug = FALSE;
    /**
     * @var Filesystem;
     */
    protected $debugFilesystem;
    /**
     * @var string Absolute file path to the debug log.
     */
    protected $debugLogPath;
    /**
     * @var array Have you been writing files to the local filesystem? Keep track of them here.
     */
    protected $localFilesWritten = [];
    /**
     * @var string $pathToRunDirectory The run directory where you can find the debug log and output from each Step the Spider took.
     */
    private $pathToRunDirectory;

    /**
     * Spider constructor.
     *
     * @param string $pathToStorage The absolute path to the directory where I will store all of my Spider run records.
     * @param bool   $debug         Do you want to enable debugging for this Spider.
     */
    public function __construct( $pathToStorage, $debug = FALSE ) {
        $this->debugSetFilesystem( $pathToStorage );
        $this->debugSetLogPath();
        $this->debugSetPathToRunDirectory();

        $this->debug = $debug;
        //        $this->client = new Client( [ // Base URI is used with relative requests
        //                                      //'base_uri' => 'example.com',
        //                                      // You can set any number of default request options.
        //                                      //'timeout'  => 2.0,
        //                                      //'cookies' => true
        //                                    ] );
        //
        //        $this->cookie_jar = new CookieJar();
    }

    /**
     * I'm using the Flysystem package to manage my debug directory.
     * This method is called from the constructor.
     * It makes sure the directory exists and is writable before I start running the Spider.
     *
     * @param string $pathToStorage The path to the directory where I store all of my run_x folders.
     *
     * @throws DebugDirectoryNotWritable
     */
    private function debugSetFilesystem( $pathToStorage ) {
        $this->pathToDebugDirectory = $pathToStorage;
        //$adapter = new Local( $pathToStorage, LOCK_SH );
        $adapter               = new Local( $pathToStorage, 0 );
        $this->debugFilesystem = new Filesystem( $adapter );

        // Now make sure that we can write to the file system.
        try {
            $timestamp = date( 'Y-m-d H:i:s' );
            //$debugFileWritten = $this->debugFilesystem->write( "root" . DIRECTORY_SEPARATOR . self::DEBUG_LOG_FILE_NAME, "\n[$timestamp] " . "Log file created.");
            $debugFileWritten = $this->debugFilesystem->write( self::DEBUG_LOG_FILE_NAME, "poop" );
            if ( $debugFileWritten === FALSE ):
                throw new Exception( "Unable to write to the debuglog file." );
            endif;
            var_dump( $this->getDebugLogContents() );

            $readMeFileWritten = $this->debugFilesystem->write( "root" . DIRECTORY_SEPARATOR . "README.txt", "This file was auto-generated. This directory contains debug files created by a Dprc\Spider" );
            if ( $readMeFileWritten === false ):
                throw new DebugDirectoryNotWritable( "I was unable to write to my debug directory at: " . $pathToStorage );
            endif;
        } catch ( Exception $e ) {
            print_r( $e->getMessage() );
            throw new DebugDirectoryNotWritable( "I was unable to write to my debug directory at: " . $pathToStorage, 100, $e );
        }
    }

    public function getDebugLogContents() {
        $path = $this->debugGetLogPath();

        return $this->debugFilesystem->read( $path );
    }

    /**
     * @return string The path to the debug log file.
     */
    protected function debugGetLogPath() {

        return $this->debugLogPath;
    }

    /**
     * If debugging is turned on, then file at this path will have a ton of useful debugging info.
     */
    private function debugSetLogPath() {
        $this->debugLogPath = $this->pathToDebugDirectory . DIRECTORY_SEPARATOR . self::DEBUG_LOG_FILE_NAME;
    }

    /**
     * @throws UnableToCreateDebugRunDirectory
     */
    private function debugSetPathToRunDirectory() {
        $runFolderName      = 'run_' . date( 'YmdHis' );
        $pathToRunDirectory = $this->pathToDebugDirectory . DIRECTORY_SEPARATOR . $runFolderName;
        $dirWasCreated      = $this->debugFilesystem->createDir( $pathToRunDirectory );
        if ( $dirWasCreated === false ):
            throw new UnableToCreateDebugRunDirectory( "Flysystem->createDir() returned false." );
        endif;
        $dirWasSetToPrivate = $this->debugFilesystem->setVisibility( $pathToRunDirectory, 'private' );
        if ( $dirWasSetToPrivate === false ):
            throw new UnableToSetVisibilityOfDebugRunDirectory( "Flysystem->setVisibility() was not able to chmod the dir." );
        endif;
        $this->pathToRunDirectory = $pathToRunDirectory;
        $this->log( "Debug Run directory of the Spider was set to: " . $this->pathToRunDirectory );


    }

    /**
     * @param $message
     *
     * @return bool
     * @throws UnableToWriteLogFile
     */
    private function log( $message ) {
        if ( ! $this->debug ):
            return FALSE;
        endif;

        $timestamp  = date( 'Y-m-d H:i:s' );
        $logWritten = file_put_contents( $this->debugGetLogPath(), "\n[$timestamp] " . $message, FILE_APPEND );
        if ( $logWritten ):
            return TRUE;
        endif;
        throw new UnableToWriteLogFile( "Unable to write to the log file at " . $this->debugLogPath );
    }

    /**
     * A Spider can't do much without steps to follow.
     * Add Step objects to this Spider, and it will try to run each Step in order.
     *
     * @param \Dprc\Spider\Step $stepObject The Step object that was created in the calling code.
     * @param string            $stepName   Used as a key in the array of steps
     */
    public function addStep( $stepObject, $stepName ) {
        // It's useful for a Step to know what it's Spider has named it.
        $stepObject->setStepName( $stepName );

        $this->steps[ $stepName ] = $stepObject;

        $this->log( 'Step added. [' . $this->numSteps() . '] [' . $stepName . '] ' . $stepObject->getUrl() );
    }

    private function numSteps() {
        return count( $this->steps );
    }

    /**
     * @return array An array of all of the responses from each of the steps.
     * @throws \Exception
     */
    public function run() {
        $this->log( "Inside spider->run()" );

        // Run all of the steps. Catch, log, and rethrow any Exceptions thrown from the run_step() method.
        foreach ( $this->steps as $index => $step ):
            try {
                $this->log( "Started step #" . $index );
                $response = $this->runStep( $step );
                $this->log( "[Finished step #" . $index . "] " . substr( $response->getBody(), 0, 50 ) );
            } catch ( Exception $e ) {
                $this->log( "Exception (" . get_class( $e ) . ") in spider->run(): " . $e->getMessage() . " " . $e->getFile() . ':' . $e->getLine() );
                throw $e;
            }
        endforeach;

        // Prep the Spider to have more Steps added.
        // The alternative would be to maintain a pointer in the steps array to keep track of where we are.
        $this->log( "Removing [" . $this->numSteps() . "] steps that were completed from this spider." );
        $this->steps = [];
        $this->log( "Exiting spider->run() and returning " . $this->numResponses() . " HTTP client responses." );

        return $this->responses;
    }

    /**
     * @param \Dprc\Spider\Step $step
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws \Exception
     */
    protected function runStep( $step ) {
        // Initialize the response array
        $this->log( "    Initializing the element for the response with index [" . $step->getStepName() . "]" );
        $stepName                     = $step->getStepName();
        $this->responses[ $stepName ] = NULL;


        $this->numberOfStepsExecuted++;
        $request = $step->getRequest();

        $this->log( "    Called step->getRequest()" );

        $sendParameters = [ 'form_params'     => $step->getFormParams(),
                            'allow_redirects' => TRUE,
                            'cookies'         => $this->cookie_jar,
                            'debug'           => FALSE,
                            'timeout'         => $step->getTimeout() ];

        $this->log( "    Set the array for sendParameters" );

        // Do we want the output of this request saved to a file?
        if ( $this->sink ):
            $sendParameters[ 'sink' ] = $this->getSink();
        endif;

        $this->log( "    Set the sink" );

        try {
            $this->log( "    Executing this->client->send()" );
            $response = $this->client->send( $request, $sendParameters );
            $this->addResponse( $stepName, $response );
            $this->debugSaveResponseBodyInDebugFolder( $response->getBody(), $this->numberOfStepsExecuted . '_' . $step->getStepName() );
            $this->saveResponseToLocalFile( $response->getBody(), $step );
        } catch ( \GuzzleHttp\Exception\ConnectException $e ) {
            $this->log( "    A ConnectException was thrown when the client sent the request [" . $e->getMessage() . "]" );
            $this->sink = NULL;
            throw new Exception( "There was a ConnectException thrown when the client sent the request. [" . $e->getMessage() . "]", -200, $e );
        } catch ( Exception $e ) {
            $this->log( "    An Exception was thrown when the client sent the request... " . $e->getMessage() );
            throw new Exception( "There was an Exception (of unknown type) thrown when the client sent the request.", -300, $e );
        }


        // Based on the Response, determine if any of the failure rules were triggered.
        foreach ( $step->failureRules as $index => $failureRule ):
            try {
                /**
                 * @var FailureRule $failureRule ;
                 */
                $failureRule->run( $response, $this->debug );
            } catch ( FailureRuleTriggeredException $exception ) {
                $this->log( "    A failure rule was triggered: " . $exception->getMessage() );
                throw $exception;
            } catch ( Exception $exception ) {
                $this->log( "    A failure rule was run, but there was an exception: " . $exception->getMessage() );
                throw $exception;
            }
        endforeach;

        $this->setSink( NULL );

        return $response;
    }

    /**
     * @return string Absolute path to the sink.
     */
    public function getSink() {
        return $this->sink;
    }

    /**
     * Do you want to save the output of the request to a file?
     * Set the absolute local destination file path.
     *
     * @param $argAbsoluteFilePath
     */
    public function setSink( $argAbsoluteFilePath ) {
        $this->sink = $argAbsoluteFilePath;
    }

    /**
     * @param string   $stepName
     * @param Response $response
     */
    private function addResponse( $stepName, $response ) {
        $this->responses[ $stepName ] = $response;
    }

    /**
     * @param $responseBodyString
     * @param $stepName
     *
     * @return bool
     * @throws UnableToWriteResponseBodyInDebugFolder
     */
    private function debugSaveResponseBodyInDebugFolder( $responseBodyString, $stepName ) {
        if ( FALSE === $this->debug ) {
            return FALSE;
        }

        $debugFileName = $this->debugGetRequestDebugFileName( $stepName );

        try {
            $written = $this->debugFilesystem->write( $debugFileName, $responseBodyString );

            if ( FALSE === $written ):
                throw new UnableToWriteResponseBodyInDebugFolder( "Unable to write the response body to the debug file for step: " . $stepName );
            endif;
        } catch ( Exception $e ) {
            throw new UnableToWriteResponseBodyInDebugFolder( "Unable to write the response body to the debug file for step: " . $stepName );
        }


        return TRUE;
    }

    /**
     * Assembles a debug file name based off the time and step name.
     *
     * @param string $stepName
     *
     * @return string
     */
    public function debugGetRequestDebugFileName( $stepName ) {
        return 'request_' . time() . '_' . $stepName . '.dprc';
    }

    /**
     * @param string            $responseBody
     * @param \Dprc\Spider\Step $step
     *
     * @return bool|int
     * @throws Exception
     */
    public function saveResponseToLocalFile( $responseBody = '', $step ) {

        $localFilePath = $step->getLocalFilePath();
        $bytesWritten  = file_put_contents( $localFilePath, $responseBody, FILE_APPEND );
        if ( FALSE === $bytesWritten ):
            throw new UnableToWriteResponseBodyToLocalFile( "Unable to write the response body to the local file: " . $localFilePath );
        endif;
        $this->localFilesWritten[ $step->getStepName() ] = $localFilePath;

        return $bytesWritten;
    }

    private function numResponses() {
        return count( $this->responses );
    }

    /**
     * @param string $stepName
     *
     * @return \Dprc\Spider\Step The Step object referenced by array index: $argStepName
     * @throws UnableToFindStepWithStepName
     */
    public function getStep( $stepName ) {
        if ( ! isset( $this->steps[ $stepName ] ) ):
            throw new UnableToFindStepWithStepName( "Step not found under: " . $stepName );
        endif;

        return $this->steps[ $stepName ];
    }

    /**
     * @return array The entire array of Step objects.
     */
    public function getSteps() {
        return $this->steps;
    }

    /**
     * @param $stepName
     *
     * @return string
     */
    public function getStepHost( $stepName ) {
        return $this->steps[ $stepName ]->getHost();
    }

    /**
     *
     */
    public function removeAllSteps() {
        $this->steps = [];
    }

    public function getResponseBody( $argStepName ) {
        $response = $this->getResponse( $argStepName );

        return $response->getBody();
    }

    /**
     * @param string $argStepName The index of the Response that we want.
     *
     * @return mixed
     * @throws IndexNotFoundInResponsesArray
     */
    public function getResponse( $argStepName ) {
        if ( isset( $this->responses[ $argStepName ] ) ):
            return $this->responses[ $argStepName ];
        endif;
        throw new IndexNotFoundInResponsesArray( "There is no index in the responses array called: " . $argStepName );
    }

    /**
     * @return Client|null
     */
    public function &getClient() {
        return $this->client;
    }

    /**
     * Called from the Command or
     * @return array
     */
    public function getLocalFilesWritten() {
        return $this->localFilesWritten;
    }

    /**
     * A getter for this Spider's run directory.
     * @return string
     */
    public function getPathToRunDirectory() {
        return $this->pathToRunDirectory;
    }


}