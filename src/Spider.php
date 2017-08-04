<?php
// http://docs.guzzlephp.org/en/latest/quickstart.html
namespace DPRMC\Spider;

use DPRMC\Spider\Exceptions\DebugLogFileDoesNotExist;
use DPRMC\Spider\Exceptions\ReadMeFileDoesNotExists;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use DPRMC\Spider\Exceptions\FailureRuleTriggeredException;
use DPRMC\Spider\Exceptions\ReadMeFileNotWritten;
use DPRMC\Spider\Exceptions\IndexNotFoundInResponsesArray;
use DPRMC\Spider\Exceptions\UnableToWriteResponseBodyInDebugFolder;
use DPRMC\Spider\Exceptions\UnableToWriteResponseBodyToLocalFile;
use DPRMC\Spider\Exceptions\UnableToFindStepWithStepName;

/**
 * http://guzzle3.readthedocs.io/http-client/client.html
 */

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;


/**
 * Class Spider
 * @package Dprc\Spider
 */
class Spider {

    /**
     * @var bool Do you want to enable debugging for this spider.
     */
    protected $debug = false;

    /**
     * If I have debug turned on, then this file will contain timestamped lines to let me see some useful debug info.
     */
    const DEBUG_LOG_FILE_NAME = 'debug.log';

    /**
     * I add a readme file to the root directory for this spider. It gives me a reminder of what this Spider is for
     * when looking around the filesystem. Additionally, it lets me attempt to write to the main directory. If there is
     * a problem with the permissions, it's nice to find out early.
     */
    const README_FILE_NAME = 'README.md';

    /**
     * @var \GuzzleHttp\Cookie\CookieJar The Spider holds all of the cookies for all the Steps in one place.
     */
    public $cookie_jar;

    /**
     * @var Client The Guzzle HTTP client that handles sending all of the Requests.
     */
    protected $client;

    /**
     * @var array An array of the Responses from each step. Indexed by step name.
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
     * @var string The absolute path to the folder where I will store every "run_*" folder for this Spider.
     */
    protected $pathToDebugDirectory;

    /**
     * @var int Increment after every run_step() call
     */
    protected $numberOfStepsExecuted = 0;


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
     * @var string $runDirectoryName The name of the run folder. Follows the format "run_{yyyymmddhhmmss}"
     */
    protected $runDirectoryName;

    /**
     * @var string $pathToRunDirectory The run directory where you can find the debug log and output from each Step the
     *      Spider took.
     */
    protected $pathToRunDirectory;


    /**
     * Spider constructor.
     *
     * @param string $pathToStorage The absolute path to the folder where I will store all of my Spider "run_*" folders.
     * @param bool   $debug         Do you want to enable debugging for this Spider.
     */
    public function __construct( $pathToStorage, $debug = false ) {
        $this->debug = $debug;
        $this->createFilesystem( $pathToStorage );
        if ( true === $this->debug ):
            $this->createLogFile();
            $this->createRunDirectory();
        endif;


        $this->client = new Client( [ // Base URI is used with relative requests
                                      //'base_uri' => 'example.com',
                                      // You can set any number of default request options.
                                      //'timeout'  => 2.0,
                                      //'cookies' => true
                                    ] );

        $this->cookie_jar = new CookieJar();
    }

    /**
     * A Spider can't do much without steps to follow.
     * Add Step objects to this Spider, and it will try to run each Step in order.
     *
     * @param \DPRMC\Spider\Step $stepObject The Step object that was created in the calling code.
     * @param string             $stepName   Used as a key in the array of steps
     */
    public function addStep( $stepObject, $stepName ) {
        // It's useful for a Step to know what it's Spider has named it.
        $stepObject->setStepName( $stepName );

        $this->steps[ $stepName ] = $stepObject;

        $this->log( 'Step added. [' . $this->numSteps() . '] [' . $stepName . '] ' . $stepObject->getUrl() );
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
     * I'm using the Flysystem package to manage my debug directory.
     * This method is called from the constructor.
     * It makes sure the directory exists and is writable before I start running the Spider.
     *
     * @param string $pathToDebugDirectory The path to the directory where I store all of my run_x folders.
     *
     * @throws ReadMeFileNotWritten
     */
    private function createFilesystem( $pathToDebugDirectory ) {
        $this->pathToDebugDirectory = $pathToDebugDirectory;
        //$adapter = new Local( $pathToStorage, LOCK_SH );
        $adapter               = new Local( $pathToDebugDirectory, 0 );
        $this->debugFilesystem = new Filesystem( $adapter );

        // Now make sure that we can write to the file system.
        try {
            $this->createReadMeFile();
        } catch ( ReadMeFileNotWritten $e ) {
            // Rethrow so it can be handled higher up.
            throw $e;
        } catch ( Exception $e ) {
            throw new ReadMeFileNotWritten( "I was unable to write to my debug directory at: " . $pathToDebugDirectory . ", because [" . $e->getMessage() . "]", 100, $e );
        }
    }


    private function createReadMeFile() {
        $timestamp = date( 'Y-m-d H:i:s' );
        if ( ! $this->debugFilesystem->has( self::README_FILE_NAME ) ):
            $readmeFileWritten = $this->debugFilesystem->write( self::README_FILE_NAME, "[$timestamp] " . "README.md file created." );
            if ( false === $readmeFileWritten ):
                throw new ReadMeFileNotWritten( "Unable to write to the " . self::README_FILE_NAME . " file." );
            endif;
        endif;
    }


    /**
     * @return string The contents of the README file as a string.
     * @throws \DPRMC\Spider\Exceptions\ReadMeFileDoesNotExists
     */
    public function getReadMeFileContents() {
        if ( ! $this->debugFilesystem->has( self::README_FILE_NAME ) ):
            throw new ReadMeFileDoesNotExists();
        endif;

        return $this->debugFilesystem->read( self::README_FILE_NAME );
    }

    /**
     * @return bool|false|string
     * @throws \DPRMC\Spider\Exceptions\DebugLogFileDoesNotExist
     */
    public function getDebugLogFileContents() {
        if ( ! $this->debugFilesystem->has( self::DEBUG_LOG_FILE_NAME ) ):
            throw new DebugLogFileDoesNotExist( "The debug file does not exist. Did you forget to turn debugging on?" );
        endif;

        return $this->debugFilesystem->read( self::DEBUG_LOG_FILE_NAME );
    }


    private function createRunDirectory() {
        $this->runDirectoryName = 'run_' . date( 'YmdHis' );
        $this->debugFilesystem->createDir( $this->runDirectoryName );
        $this->debugFilesystem->setVisibility( $this->runDirectoryName, 'private' );
        $this->log( "Debug Run directory of the Spider was set to: " . $this->runDirectoryName );
    }

    /**
     * If debugging is turned on, then the file at this path will have a ton of useful debugging info.
     */
    protected function createLogFile() {
        $contents = "[" . date( "Y-m-d H:i:s" ) . "] Debug Log file created.";

        return $this->debugFilesystem->write( self::DEBUG_LOG_FILE_NAME, $contents );
    }


    /**
     * @param $message
     *
     * @return bool
     */
    private function log( $message ) {
        if ( false === $this->debug ):
            return false;
        endif;

        $timestamp       = date( 'Y-m-d H:i:s' );
        $logFileContents = $this->debugFilesystem->read( self::DEBUG_LOG_FILE_NAME );
        $logFileContents .= "\n[$timestamp] " . $message;

        return $this->debugFilesystem->put( self::DEBUG_LOG_FILE_NAME, $logFileContents );
    }


    private function numSteps() {
        return count( $this->steps );
    }


    /**
     * @param \DPRMC\Spider\Step $step
     *
     * @return \GuzzleHttp\Psr7\Response
     * @throws \Exception
     */
    protected function runStep( $step ) {
        // Initialize the response array
        $this->log( "    Initializing the element for the response with index [" . $step->getStepName() . "]" );
        $stepName                     = $step->getStepName();
        $this->responses[ $stepName ] = null;


        $this->numberOfStepsExecuted++;
        $request = $step->getRequest();

        $this->log( "    Called step->getRequest()" );

        $sendParameters = [ 'form_params'     => $step->getFormParams(),
                            'allow_redirects' => true,
                            'cookies'         => $this->cookie_jar,
                            'debug'           => false,
                            'timeout'         => $step->getTimeout(),
                            'sink'            => null,
        ];

        $this->log( "    Set the array for sendParameters" );

        // Do we want the output of this request saved to a file?
        if ( $this->sink ):
            $sendParameters[ 'sink' ] = $this->getSink();
        endif;

        $this->log( "    Set the sink to " . $sendParameters[ 'sink' ] );

        try {
            $this->log( "    Executing this->client->send()" );
            $response = $this->client->send( $request, $sendParameters );
            $this->addResponse( $stepName, $response );
            $this->debugSaveResponseBodyInDebugFolder( $response->getBody(), $this->numberOfStepsExecuted . '_' . $step->getStepName() );
            //$this->saveResponseToLocalFile( $response->getBody(), $step );
        } catch ( ConnectException $e ) {
            $this->log( "    A ConnectException was thrown when the client sent the request [" . $e->getMessage() . "]" );
            $this->sink = null;
            throw $e;
        } catch ( Exception $e ) {
            $this->log( "    An Exception was thrown when the client sent the request... " . $e->getMessage() );
            throw new Exception( "There was an Exception (" . get_class( $e ) . ") thrown when the client sent the request.", -300, $e );
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

        $this->setSink( null );

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
        if ( false === $this->debug ) {
            return false;
        }

        $debugFileName = $this->debugGetRequestDebugFileName( $stepName );

        try {
            $written = $this->debugFilesystem->write( $debugFileName, $responseBodyString );

            if ( false === $written ):
                throw new UnableToWriteResponseBodyInDebugFolder( "Unable to write the response body to the debug file for step: " . $stepName );
            endif;
        } catch ( Exception $e ) {
            throw new UnableToWriteResponseBodyInDebugFolder( "Unable to write the response body to the debug file for step: " . $stepName );
        }


        return true;
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

    private function numResponses() {
        return count( $this->responses );
    }

    /**
     * @param string            $responseBody
     * @param \Dprc\Spider\Step $step
     *
     * @return bool|int
     * @throws Exception
     */
    public function saveResponseToLocalFile( $responseBody = '', $step ) {

        //$localFilePath = $step->getLocalFilePath();
        $bytesWritten = file_put_contents( $localFilePath, $responseBody, FILE_APPEND );
        if ( false === $bytesWritten ):
            throw new UnableToWriteResponseBodyToLocalFile( "Unable to write the response body to the local file: " . $localFilePath );
        endif;
        $this->localFilesWritten[ $step->getStepName() ] = $localFilePath;

        return $bytesWritten;
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

    /**
     * @param string $argStepName
     *
     * @return mixed
     */
    public function getResponseBody( $argStepName ) {
        /**
         * @var Response $response
         */
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
}