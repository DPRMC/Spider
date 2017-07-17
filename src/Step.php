<?php

namespace Dprc\Spider;

use /** @noinspection PhpUndefinedNamespaceInspection */
    GuzzleHttp\Psr7\Request;

/**
 * Class Step
 * @package Dprc\Spider
 */
class Step {

    /**
     * @var string The URL that the request will be directed to.
     */
    protected $url = '';

    /**
     * @var array
     */
    protected $form_params = [];

    /**
     * @var string get or post
     */
    protected $method = 'get';

    /**
     * @var int The maximum number of seconds to allow for an entire
     * transfer to take place before timing out.
     * Set to 0 to wait indefinitely.
     */
    protected $timeout = 0;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var string
     */
    protected $body = '';

    /**
     * @var string
     */
    protected $http_protocol_version = '1.1';

    /**
     * @var null Spiders can name each of the steps. Set outside of this object.
     */
    protected $step_name = NULL;

    /**
     * @var array
     */
    public $failure_rules = [];

    /**
     * @var array Index must match failure rule index.
     */
    public $next_steps_on_failure = [];

    /**
     * @var null Want to save the output of this step to a file? Like an XLS?
     */
    protected $local_file_path = NULL;

    /**
     * Step constructor.
     */
    public function __construct() {

    }

    public static function instance() {
        return new static();
    }


    /**
     * @param string $argMethod get or post. Case-insensitive.
     */
    public function setMethod( $argMethod ) {
        $this->method = strtoupper( $argMethod );
    }

    /**
     * @return string Getter method for the request type. GET or POST
     */
    public function getMethod() {
        return strtoupper( $this->method );
    }

    /**
     * @param int $timeout
     */
    public function setTimeout( $timeout ) {
        $this->timeout = (int)$timeout;
    }

    /**
     * @return int
     */
    public function getTimeout() {
        return $this->timeout;
    }


    /**
     * @param string $argUrl
     */
    public function setUrl( $argUrl ) {
        $this->url = $argUrl;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * @return bool
     */
    public function getHost() {
        $aUrlParts = parse_url( $this->url );
        if ( isset( $aUrlParts[ 'host' ] ) ):
            return $aUrlParts[ 'host' ];
        endif;

        return FALSE;
    }

    /**
     * Returns an MD5 hash of the Url to be used as an index in the cookie_jars
     * property of the Spider object.
     */
    public function getUrlHostHash() {
        $aUrlParts = parse_url( $this->url );

        return md5( $aUrlParts[ 'host' ] );
    }

    /**
     * @param $argName
     * @param $argValue
     */
    public function addFormParam( $argName, $argValue ) {
        $this->form_params[ $argName ] = $argValue;
    }

    /**
     * @return array
     */
    public function getFormParams() {
        return $this->form_params;
    }

    /**
     * @return Request
     */
    public function getRequest() {
        return new Request( $this->method, $this->url, $this->headers, $this->body, $this->http_protocol_version );
    }


    /**
     * @param FailureRule $argFailureRule
     * @param null $argFailureRuleIndex
     * @return bool
     */
    public function addFailureRule( $argFailureRule, $argFailureRuleIndex = NULL ) {
        $argFailureRule->setFailureRuleName( $argFailureRuleIndex );
        if ( $argFailureRuleIndex ):
            $this->failure_rules[ $argFailureRuleIndex ] = $argFailureRule;
        else:
            $this->failure_rules[] = $argFailureRule;
        endif;

        return TRUE;
    }


    /**
     * @param string $argStepName
     */
    public function setStepName( $argStepName ) {
        $this->step_name = $argStepName;
    }

    /**
     * @return string
     */
    public function getStepName() {
        return $this->step_name;
    }

    /**
     * @param string $argLocalFilePath
     */
    public function setLocalFilePath( $argLocalFilePath = '' ) {
        $this->local_file_path = $argLocalFilePath;
    }

    /**
     * @return null
     */
    public function getLocalFilePath() {
        return $this->local_file_path;
    }

    /**
     * @return bool
     */
    public function needsResponseSavedToLocalFile() {
        if ( $this->local_file_path ):
            return TRUE;
        endif;

        return FALSE;
    }

}