<?php

namespace Dprc\Spider\Facades;

use Illuminate\Support\Facades\Facade;

class SuccessRule extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'success_rule';
    }

}