<?php namespace Icto\Bigbluebutton;

use Illuminate\Support\ServiceProvider;

class BigbluebuttonServiceProvider extends ServiceProvider {

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/bigbluebutton.php' => config_path('bigbluebutton.php')
        ]);
    }

    public function register()
    {
        $this->app['bigbluebutton'] = $this->app->share(function() {
            $config = $this->app['config']->get('bigbluebutton');
            return new BigbluebuttonApi($config);
        });
    }
}
