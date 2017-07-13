<?php

namespace Dprc\Spider;


class FailureRule {
    protected $failure_rule_name = 'default_failure_rule_name'; // The Step will use this as the index in the failure_rules array.

    /**
     * @var string Right now the only test I am doing is a regex. It could be anything though.
     */
    protected $rule_type = NULL;


    protected $rule_parameters = NULL;

    public function __construct() {

    }

    public static function instance() {
        return new static();
    }

    public function setFailureRuleName( $argFailureRuleName ) {
        $this->failure_rule_name = $argFailureRuleName;
    }

    public function getFailureRuleName() {
        return $this->failure_rule_name;
    }

    /**
     * @param string $argRuleType Right now the only one defined is regex.
     */
    public function setRuleType( $argRuleType ) {
        $this->rule_type = $argRuleType;
    }

    /**
     * @return string
     */
    public function getRuleType() {
        return $this->rule_type;
    }

    public function setRuleParameters( $argRuleParameters ) {
        $this->rule_parameters = $argRuleParameters;
    }

    public function getRuleParameters() {
        return $this->rule_parameters;
    }

    /**
     * @param $response
     * @param bool $debug
     * @return bool
     * @throws \Exception
     */
    public function run( $response, $debug = FALSE ) {
        $result = FALSE;
        switch ( $this->rule_type ):
            case 'regex':
                $result = $this->runFailureRuleRegEx( $this->rule_parameters, $response->getBody() );
                break;
            default:
                break;
        endswitch;

        if ( $result === TRUE ): // Failure.
            throw new \Exception( $this->failure_rule_name, -100 );
        endif;

        return TRUE;
    }


    /**
     * @param $argPattern
     * @param $argString
     * @return bool
     */
    protected function runFailureRuleRegEx( $argPattern, $argString ) {
        if ( preg_match( '/' . $argPattern . '/', $argString ) === 1 ):
            return TRUE;
        endif;

        return FALSE;
    }
}