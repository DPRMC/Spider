<?php

namespace Dprc\Spider;

use /** @noinspection PhpUndefinedNamespaceInspection */
    GuzzleHttp\Psr7\Request;

/**
 * Class Step
 * @package Dprc\Spider
 */
class Step {

    protected $url = ''; // The URL that the request will be directed to.
    protected $form_params = [];
    protected $method = 'get';
    /**
     * @var int The maximum number of seconds to allow for an entire transfer to take place before timing out. Set to 0 to wait indefinitely.
     */
    protected $timeout = 0;
    protected $headers = [];
    protected $body = '';
    protected $http_protocol_version = '1.1';
    protected $step_name = NULL; // Spiders can name each of the steps. Set outside of this object.
    public $failure_rules = [];
    public $next_steps_on_failure = []; // Index must match failure rule index.

    protected $local_file_path = NULL; // Want to save the output of this step to a file? Like an XLS?

    public function __construct() {

    }

    public static function instance() {
        return new static();
    }


    public function setMethod( $argMethod ) {
        $this->method = strtoupper( $argMethod );
    }

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


    public function setUrl( $argUrl ) {
        $this->url = $argUrl;
    }

    public function getUrl() {
        return $this->url;
    }

    public function getHost() {
        $aUrlParts = parse_url( $this->url );
        if ( isset( $aUrlParts[ 'host' ] ) ):
            return $aUrlParts[ 'host' ];
        endif;

        return FALSE;
    }

    /**
     * Returns an MD5 hash of the Url to be used as an index in the cookie_jars property of the Spider object.
     */
    public function getUrlHostHash() {
        $aUrlParts = parse_url( $this->url );

        return md5( $aUrlParts[ 'host' ] );
    }

    public function addFormParam( $argName, $argValue ) {
        $this->form_params[ $argName ] = $argValue;
    }

    public function getFormParam() {
        return $this->form_params;
    }

    public function getRequest() {
        return new Request( $this->method, $this->url, $this->headers, $this->body, $this->http_protocol_version );
    }

    /*
    public function addFailureRule($argRuleType, $argRuleParameters, $argNextStepOnFailure=null){
        switch($argRuleType):
            case 'regex':
                return $this->runFailureRuleRegEx($argRuleType, $argRuleParameters);
                break;
            default:
                throw new Exception("Unsupported failure rule type passed in: " . $argRuleType, -100);
                break;
        endswitch;
    }
    */


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

        //$this->log('Failure rule added. [' . count($this->failure_rules) . '] [' . $argFailureRuleIndex . '] ' . $argFailureRule->getRuleType() );
        return TRUE;
    }


    /**
     * @param $argStepName
     */
    public function setStepName( $argStepName ) {
        $this->step_name = $argStepName;
    }

    /**
     * @return null
     */
    public function getStepName() {
        return $this->step_name;
    }

    /**
     * @param string $argLocalFilepath
     */
    public function setLocalFilepath( $argLocalFilepath = '' ) {
        $this->local_file_path = $argLocalFilepath;
    }

    /**
     * @return null
     */
    public function getLocalFilepath() {
        return $this->local_file_path;
    }

    public function needsResponseSavedToLocalFile() {
        if ( $this->local_file_path ):
            return TRUE;
        endif;

        return FALSE;
    }

}