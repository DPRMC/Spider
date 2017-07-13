<?php

namespace Dprc\Spider\Facades;

use Illuminate\Support\Facades\Facade;

class Step extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'step';
    }

}