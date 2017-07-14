<?php

namespace Dprc\Spider;

use Illuminate\Support\ServiceProvider;

class SpiderServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = FALSE;

    /**
     * Bootstrap the application events.
     * @return void
     */
    public function boot() {
        $this->package( 'dprc/spider' );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {

        $this->app->booting( function () {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias( 'Spider', 'Dprc\Spider\Facades\Spider' );
        } );


        $this->app[ 'spider' ] = $this->app->share( function ( $app ) {
            return new Spider;
        } );

        $this->app[ 'step' ] = $this->app->share( function ( $app ) {
            return new Step;
        } );

        $this->app[ 'failure_rule' ] = $this->app->share( function ( $app ) {
            return new FailureRule;
        } );

        $this->app[ 'success_rule' ] = $this->app->share( function ( $app ) {
            return new SuccessRule;
        } );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {
        return [ 'spider' ];
    }

}
