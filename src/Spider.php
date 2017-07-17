<?php
// http://docs.guzzlephp.org/en/latest/quickstart.html
namespace Dprc\Spider;

use Dprc\Spider\Exceptions\DebugDirectoryNotSet;
use Dprc\Spider\Exceptions\IndexNotFoundInResponsesArray;
use Dprc\Spider\Exceptions\UnableToCreateDebugDirectory;
use Dprc\Spider\Exceptions\UnableToWriteLogFile;
use Dprc\Spider\Exceptions\UnableToWriteResponseBodyInDebugFolder;
use Dprc\Spider\Exceptions\UnableToWriteResponseBodyToLocalFile;
use Exception;

use Dprc\Spider\Exceptions\DebugDirectoryAlreadySet;
use Dprc\Spider\Step;

/**
 * http://guzzle3.readthedocs.io/http-client/client.html
 */
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\File;


/**
 * Class Spider
 * @package Dprc\Spider
 */
class Spider {
    /**
     * @var Client|null
     */
    protected $client = NULL;

    /**
     * @var \GuzzleHttp\Cookie\CookieJar|null
     */
    public $cookie_jar = NULL;

    /**
     * @var array
     */
    protected $responses = [];

    /**
     * @var array
     */
    protected $steps = []; // An array of Step objects

    /**
     * @var null Want to save the body of the response to a file?
     */
    protected $sink = NULL;

    /**
     * @var null
     */
    protected $debug_directory = NULL;

    /**
     * @var int Increment after every run_step() call
     */
    protected $number_of_steps_executed = 0;

    /**
     * @var bool
     */
    protected $debug = FALSE;

    /**
     * @var string Absolute file path to the debug log.
     */
    protected $debug_log = NULL;

    /**
     * @var array Have you been writing files to the local filesystem? Keep track of them here.
     */
    protected $local_files_written = [];

    /**
     * Spider constructor.
     * @param bool $debug
     */
    public function __construct( $debug = FALSE ) {
        $this->debug  = $debug;
        $this->client = new Client( [ // Base URI is used with relative requests
                                      //'base_uri' => 'example.com',
                                      // You can set any number of default request options.
                                      //'timeout'  => 2.0,
                                      //'cookies' => true
                                    ] );

        $this->cookie_jar = new CookieJar();
    }


    /**
     * @return static
     */
    public static function instance( $debug ) {
        return new static( $debug );
    }


    /**
     * @param Step $argStepObject
     * @param null $argStepName - Used as a key in the array of steps
     * @return bool
     */
    public function addStep( $argStepObject, $argStepName ) {
        $argStepObject->setStepName( $argStepName );
        if ( $argStepName ):
            $this->steps[ $argStepName ] = $argStepObject;
        else:
            $this->steps[] = $argStepObject;
        endif;
        $this->log( 'Step added. [' . count( $this->steps ) . '] [' . $argStepName . '] ' . $argStepObject->getUrl() );

        return TRUE;
    }

    /**
     * @param $argStepName
     * @return \Dprc\Spider\Step The Step object referenced by array index: $argStepName
     */
    public function getStep( $argStepName ) {
        return $this->steps[ $argStepName ];
    }

    /**
     * @return array The entire array of Step objects.
     */
    public function getSteps() {
        return $this->steps;
    }


    public function getStepHost( $argStepName ) {
        return $this->steps[ $argStepName ]->getHost();
    }

    /**
     * Do you want to save the output of the request to a file?
     * Set the absolute local destination file path.
     * @param $argAbsoluteFilePath
     */
    public function setSink( $argAbsoluteFilePath ) {
        $this->sink = $argAbsoluteFilePath;
    }

    /**
     * @return string Absolute path to the sink.
     */
    public function getSink() {
        return $this->sink;
    }

    /**
     *
     */
    public function removeAllSteps() {
        $this->steps = [];
    }


    /**
     * @return array An array of all of the responses from each of the steps.
     * @throws \Exception
     */
    public function run() {
        $this->log( "Inside spider->run()" );

        // Will throw exception if the debug directory is not set.
        $this->getDebugDirectory();

        // Run all of the steps. Catch, log, and rethrow any Exceptions thrown from the run_step() method.
        foreach ( $this->steps as $index => $step ):
            try {
                $this->log( "Started step #" . $index );
                $response = $this->run_step( $step );
                $this->log( "[Finished step #" . $index . "] " . substr( $response->getBody(), 0, 50 ) );
            } catch ( Exception $e ) {
                $this->log( "Exception (" . get_class( $e ) . ") in spider->run(): " . $e->getMessage() . " " . $e->getFile() . ':' . $e->getLine() );
                throw $e;
            }
        endforeach;

        $this->log( "Removing [" . count( $this->steps ) . "] steps that were completed from this spider." );

        // Prep the Spider to have more Steps added.
        // The alternative would be to maintain a pointer in the steps array to keep track of where we are.
        $this->steps = [];
        $this->log( "Exiting spider->run() and returning " . count( $this->responses ) . " HTTP client responses." );

        return $this->responses;
    }


    /**
     * @param string $argStepName The index of the Response that we want.
     * @return mixed
     * @throws IndexNotFoundInResponsesArray
     */
    public function getResponse( $argStepName ) {
        if ( isset( $this->responses[ $argStepName ] ) ):
            return $this->responses[ $argStepName ];
        endif;
        throw new IndexNotFoundInResponsesArray( "There is no index in the responses array called: " . $argStepName );
    }

    public function getResponseBody( $argStepName ) {
        $response = $this->getResponse( $argStepName );

        return $response->getBody();
    }

    /**
     * @param \Dprc\Spider\Step $step
     * @param bool $debug
     * @return Response
     * @throws \Exception
     */
    protected function run_step( $step ) {
        // Initialize the response array
        $this->log( "    Initializing the element for the response with index [" . $step->getStepName() . "]" );
        $step_name = $step->getStepName();
        if ( $step_name ):
            $this->responses[ $step_name ] = NULL;
        endif;


        $this->number_of_steps_executed++;
        $request = $step->getRequest();

        $this->log( "    Called step->getRequest()" );

        $aSendParameters = [ 'form_params'     => $step->getFormParams(),
                             'allow_redirects' => TRUE,
                             'cookies'         => $this->cookie_jar,
                             'debug'           => FALSE,
                             'timeout'         => $step->getTimeout() ];

        $this->log( "    Set the array for aSendParameters" );

        // Do we want the output of this request saved to a file?
        if ( $this->sink ):
            $aSendParameters[ 'sink' ] = $this->getSink();
        endif;

        $this->log( "    Set the sink" );

        try {
            $this->log( "    Executing this->client->send()" );
            $response = $this->client->send( $request, $aSendParameters );
        } catch ( \GuzzleHttp\Exception\ConnectException $e ) {
            $this->log( "    A ConnectException was thrown when the client sent the request [" . $e->getMessage() . "]" );
            $this->sink = NULL;
            throw new Exception( "There was a ConnectException thrown when the client sent the request. [" . $e->getMessage() . "]", -200, $e );
        } catch ( Exception $e ) {
            $this->log( "    An Exception was thrown when the client sent the request... " . $e->getMessage() );
            throw new Exception( "There was a generic Exception thrown when the client sent the request.", -300, $e );
        }


        if ( $step_name ):
            $this->responses[ $step_name ] = $response;
        else:
            $this->responses[] = $response;
        endif;

        foreach ( $step->failure_rules as $index => $failure_rule ):
            try {
                /**
                 * @var FailureRule $failure_rule ;
                 */
                $failure_rule->run( $response, $this->debug );
            } catch ( Exception $e ) {
                $this->log( "    A failure rule was triggered: " . $e->getMessage() );
                $ex = new Exception( "Failure rule step: " . $e->getMessage(), $e );
                throw $ex;
            }
        endforeach;

        $this->saveResponseBodyInDebugFolder( $response->getBody(), $this->number_of_steps_executed . '_' . $step->getStepName() );
        $this->saveResponseToLocalFile( $response->getBody(), $step );

        $this->sink = NULL;

        return $response;
    }


    /**
     * @return Client|null
     */
    public function &getClient() {
        return $this->client;
    }


    /**
     * @param $argResponseBodyString
     * @param $argStepName
     * @return bool
     * @throws UnableToWriteResponseBodyInDebugFolder
     */
    protected function saveResponseBodyInDebugFolder( $argResponseBodyString, $argStepName ) {
        if ( $this->debug == FALSE ) {
            return FALSE;
        }
        $sAbsoluteFilePath = $this->debug_directory . '/' . $this->getRequestDebugFileName( $argStepName );
        $bytes_written     = file_put_contents( $sAbsoluteFilePath, $argResponseBodyString, FILE_APPEND );
        if ( $bytes_written === FALSE ):
            throw new UnableToWriteResponseBodyInDebugFolder( "Unable to write the response body to the debug file for step: " . $argStepName );
        endif;
    }

    /**
     * @param string $argResponseBody
     * @param \Dprc\Spider\Step $argStep
     * @return bool|int
     * @throws Exception
     */
    protected function saveResponseToLocalFile( $argResponseBody = '', $argStep ) {

        if ( !$argStep->needsResponseSavedToLocalFile() ):
            return FALSE;
        endif;
        $localFilePath = $argStep->getLocalFilePath();
        $bytes_written = file_put_contents( $localFilePath, $argResponseBody, FILE_APPEND );
        if ( $bytes_written === FALSE ):
            throw new UnableToWriteResponseBodyToLocalFile( "Unable to write the response body to the local file: " . $localFilePath );
        endif;
        $this->local_files_written[ $argStep->getStepName() ] = $localFilePath;

        return $bytes_written;
    }


    /**
     * @param string $argStepName
     * @return string
     */
    protected function getRequestDebugFileName( $argStepName ) {
        return 'request_' . time() . '_' . $argStepName . '.dprc';
    }


    /**
     * Called from the Command or
     * @return array
     */
    public function getLocalFilesWritten() {
        return $this->local_files_written;
    }

    /**
     * @param $message
     * @return bool
     * @throws UnableToWriteLogFile
     */
    public function log( $message ) {
        if ( !$this->debug ):
            return FALSE;
        endif;

        $timestamp  = date( 'Y-m-d H:i:s' );
        $logWritten = file_put_contents( $this->debug_log, "\n[$timestamp] " . $message, FILE_APPEND );
        if ( $logWritten ):
            return TRUE;
        endif;
        throw new UnableToWriteLogFile( "Unable to write to the log file at " . $this->debug_log );
    }

    /**
     * @param $argNewDirectory
     * @return null|string
     * @throws \Exception
     */
    public function setDebugDirectory( $argNewDirectory ) {
        if ( File::isDirectory( $this->debug_directory ) ):
            throw new DebugDirectoryAlreadySet( "The debug directory is already set at: " . $this->debug_directory );
        endif;

        $pathToDebugDirectory = storage_path() . '/spider/' . $argNewDirectory . '/run_' . date( 'YmdHis' );
        if ( !file_exists( $pathToDebugDirectory ) ):
            try {
                $result = File::makeDirectory( $pathToDebugDirectory, 0775, TRUE );
                chmod( $pathToDebugDirectory, 0777 );
            } catch ( Exception $e ) {
                throw new UnableToCreateDebugDirectory( "Unable to create debug directory for the DPRC Spider [" . $pathToDebugDirectory . '] ' . $e->getCode() . ' ' . $e->getMessage(), NULL, $e );
            }
        endif;
        $this->debug_directory = $pathToDebugDirectory;

        $this->setDebugLogPath();

        $this->log( "Debug directory of the Spider was set to: " . $this->debug_directory );

        return $this->debug_directory;
    }


    /**
     * @return string
     */
    public function getDebugLogPath() {
        return $this->debug_log;
    }

    /**
     *
     */
    public function setDebugLogPath() {
        $this->debug_log = $this->debug_directory . '/debug.log';
    }

    /**
     * The dir that contains all of our debug logs gets full in a hurry.
     * If debug is turned off, then delete these directories.
     */
    public function deleteDebugDirectory() {
        $success = File::deleteDirectory( $this->debug_directory );

        return $success;
    }


    /**
     * @return null
     * @throws DebugDirectoryNotSet
     */
    public function getDebugDirectory() {
        if ( !File::isDirectory( $this->debug_directory ) ):
            throw new DebugDirectoryNotSet( "The debug directory needs to be set before you can use it." );
        endif;

        return $this->debug_directory;
    }

}