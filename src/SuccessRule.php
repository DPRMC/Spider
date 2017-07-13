<?php

namespace Dprc\Spider;


class SuccessRule {
    public function __construct() {

    }

    public static function instance() {
        return new static();
    }
}