<?php
namespace Dprc\Spider;

use Dprc\Spider\Exceptions\UndefinedFailureRuleType;
use GuzzleHttp\Psr7\Response;
use Exception;

class FailureRule {
    protected $failureRuleName = 'default_failure_rule_name'; // The Step will use this as the index in the failure_rules array.

    /**
     * @var string Right now the only test I am doing is a regex. It could be anything though.
     */
    protected $ruleType = NULL;


    /**
     * @var null
     */
    protected $ruleParameters;

    /**
     * FailureRule constructor.
     */
    public function __construct() {

    }

    /**
     * @return static
     */
    public static function instance() {
        return new static();
    }

    /**
     * @param $argFailureRuleName
     */
    public function setFailureRuleName( $argFailureRuleName ) {
        $this->failureRuleName = $argFailureRuleName;
    }

    /**
     * @return string
     */
    public function getFailureRuleName() {
        return $this->failureRuleName;
    }

    /**
     * @param string $argRuleType Right now the only one defined is regex.
     */
    public function setRuleType( $argRuleType ) {
        $this->ruleType = $argRuleType;
    }

    /**
     * @return string
     */
    public function getRuleType() {
        return $this->ruleType;
    }

    /**
     * @param $argRuleParameters
     */
    public function setRuleParameters( $argRuleParameters ) {
        $this->ruleParameters = $argRuleParameters;
    }

    /**
     * @return null
     */
    public function getRuleParameters() {
        return $this->ruleParameters;
    }

    /**
     * @param Response $response
     * @param bool $debug
     * @return bool
     * @throws Exception
     */
    public function run( $response, $debug = FALSE ) {

        switch ( $this->ruleType ):
            case 'regex':
                $result = $this->runFailureRuleRegEx( $this->ruleParameters, $response->getBody() );
                break;
            default:
                throw new UndefinedFailureRuleType( "You attempted to run a failure rule type of [" . $this->ruleType . "]" );
                break;
        endswitch;
        // TRUE represents a Failure here.
        if ( $result === TRUE ):
            throw new Exception( $this->failureRuleName, -100 );
        endif;

        return TRUE;
    }


    /**
     * @param string $argPattern The regex pattern.
     * @param string $argString
     * @return bool
     */
    protected function runFailureRuleRegEx( $argPattern, $argString ) {
        if ( preg_match( '/' . $argPattern . '/', $argString ) === 1 ):
            return TRUE;
        endif;

        return FALSE;
    }
}