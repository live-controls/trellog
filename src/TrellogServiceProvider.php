<?php

namespace LiveControls\Trellog;

use Illuminate\Support\ServiceProvider;

class TrellogServiceProvider extends ServiceProvider
{
  public function register()
  {
    $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'trellog');
  }

  public function boot()
  {
    if($this->app->runningInConsole()){
      $this->publishes([
        __DIR__.'/../config/config.php' => config_path('trellog.php'),
      ], 'trellog.config');

    }
  }
}
