<?php

namespace ctf0\Lingo;

use Illuminate\Support\ServiceProvider;

class LingoServiceProvider extends ServiceProvider
{
    protected $file;

    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        $this->file = $this->app['files'];

        $this->packagePublish();
        $this->registerMacro();

        // append extra data
        if (!$this->app['cache']->store('file')->has('ct-lingo')) {
            $this->autoReg();
        }
    }

    protected function registerMacro()
    {
        $this->app['router']->macro('setGroupNamespace', function ($namesapce = null) {
            $lastGroupStack = array_pop($this->groupStack);
            if ($lastGroupStack !== null) {
                array_set($lastGroupStack, 'namespace', $namesapce);
                $this->groupStack[] = $lastGroupStack;
            }

            return $this;
        });
    }

    /**
     * [packagePublish description].
     *
     * @return [type] [description]
     */
    public function packagePublish()
    {
        // resources
        $this->publishes([
            __DIR__ . '/resources/assets' => resource_path('assets/vendor/Lingo'),
        ], 'assets');

        // views
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'Lingo');
        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/Lingo'),
        ], 'views');

        // trans
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'Lingo');
        $this->publishes([
            __DIR__ . '/resources/lang' => resource_path('lang/vendor/Lingo'),
        ], 'trans');

        $this->viewComp();
    }

    protected function viewComp()
    {
        $path = resource_path('lang/vendor/Lingo');

        if ($this->file->exists($path)) {
            $current   = $this->app->getLocale();
            $fall_back = $this->app['config']->get('app.fallback_locale');
            $file_name = 'messages.php';

            $trans = file_exists("$path/$current/$file_name")
               ? include "$path/$current/$file_name"
               : include "$path/$fall_back/$file_name";

            return view()->composer('Lingo::*', function ($view) use ($trans) {
                $view->with(['lingo_trans' => json_encode($trans)]);
            });
        }

        view()->composer('Lingo::*', function ($view) {
            $view->with(['lingo_trans' => json_encode([])]);
        });
    }

    /**
     * [autoReg description].
     *
     * @return [type] [description]
     */
    protected function autoReg()
    {
        // routes
        $route_file = base_path('routes/web.php');
        $search     = 'Lingo';

        if ($this->checkExist($route_file, $search)) {
            $data = "\n// Lingo\nctf0\Lingo\LingoRoutes::routes();";

            $this->file->append($route_file, $data);
        }

        // mix
        $mix_file = base_path('webpack.mix.js');
        $search   = 'Lingo';

        if ($this->checkExist($mix_file, $search)) {
            $data = "\n// Lingo\nmix.sass('resources/assets/vendor/Lingo/sass/style.scss', 'public/assets/vendor/Lingo/style.css')";

            $this->file->append($mix_file, $data);
        }

        // run check once
        $this->app['cache']->store('file')->rememberForever('ct-lingo', function () {
            return 'added';
        });
    }

    /**
     * [checkExist description].
     *
     * @param [type] $file   [description]
     * @param [type] $search [description]
     *
     * @return [type] [description]
     */
    protected function checkExist($file, $search)
    {
        return $this->file->exists($file) && !str_contains($this->file->get($file), $search);
    }

    /**
     * Register any package services.
     */
    public function register()
    {
        $this->app->register(\Themsaid\Langman\LangmanServiceProvider::class);
        $this->app->register(\ctf0\PackageChangeLog\PackageChangeLogServiceProvider::class);
    }
}
