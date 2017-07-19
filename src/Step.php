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
    protected $formParams = [];

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
    protected $httpProtocolVersion = '1.1';

    /**
     * @var string Spiders can name each of the steps. Set outside of this object.
     */
    protected $stepName;

    /**
     * @var array
     */
    public $failureRules = [];

    /**
     * @var array Index must match failure rule index.
     */
    public $nextStepsOnFailure = [];

    /**
     * @var null Want to save the output of this step to a file? Like an XLS?
     */
    protected $localFilePath = NULL;

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
        $this->formParams[ $argName ] = $argValue;
    }

    /**
     * @return array
     */
    public function getFormParams() {
        return $this->formParams;
    }

    /**
     * @return Request
     */
    public function getRequest() {
        return new Request( $this->method, $this->url, $this->headers, $this->body, $this->httpProtocolVersion );
    }


    /**
     * @param FailureRule $failureRule
     * @param null $failureRuleName Used as the index for this failure rule in the failureRules array.
     */
    public function addFailureRule( $failureRule, $failureRuleName = NULL ) {
        $failureRule->setFailureRuleName( $failureRuleName );
        if ( $failureRuleName ):
            $this->failureRules[ $failureRuleName ] = $failureRule;
        else:
            $this->failureRules[] = $failureRule;
        endif;
    }


    /**
     * @param string $stepName
     */
    public function setStepName( $stepName ) {
        $this->stepName = $stepName;
    }

    /**
     * @return string Return the name given to this Step.
     */
    public function getStepName() {
        return $this->stepName;
    }




}