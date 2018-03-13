yii2-xhprof
=================

Simple extension to use XHProf with Yii Framework 2.x. This is the updated version of [yii-xhprof](https://github.com/stevad/yii-xhprof) extension for Yii Framework 1.x.

Bundled with debug panel for official [yii2-debug](https://github.com/yiisoft/yii2-debug) extension.

By default profile starts during bootstrap process and stops in PHP shutdown function. You can change this behavior and manually start and stop profiler.

For license information check the [LICENSE](LICENSE.md) file.

Tested on Yii Framework v2.0.6+.

Installation
-------------

This extension is available at [packagist.org](http://packagist.org/stevad/yii2-xhprof) and can be installed via composer by following command:

`composer require --dev stevad/yii2-xhprof`.

Minimal configuration to enable profiler:

```php
return [
    'bootstrap' => [
        'xhprof'
    ],
    'components' => [
        'xhprof' => [
            'class' => 'stevad\xhprof\XHProfComponent',
            'libPath' => '/full/path/to/xhprof_lib',
            'htmlReportBaseUrl' => 'http://url.to/xhprof_html',
        ],
    ],
];
```

To use bundled debug panel - update configuration next way:

```php
return [
    'bootstrap' => [
        'debug',
        'xhprof'
    ],
    'components' => [
        'debug' => [
            // ... other debug config options ...
            'panels' => [
                'xhprof' => [
                    'class' => 'stevad\xhprof\XHProfPanel'
                ]
            ]
        ],
    ],
];
```

_Note:_ `xhprof` should be after `debug` in `preload` section to be able to ignore requests to debug module pages.

Required XHProf files (library files - `xhprof_lib` and UI - `xhprof_html`) you can find in this [GitHub repo](https://github.com/phacility/xhprof).

Component configuration
-------------

Extension provide next configuration options for Yii component:

- `enabled` - enable/disable profiler component.
- `reportPath` - filesystem path or path alias to the directory to store JSON file with previous profiler runs. Default: `@runtime/xhprof`
- `maxReportsCount` - number of profile reports to store. Default: `25`.
- `autoStart` - flag to automatically start profiler during application bootstrap process. Default: `true`.
- `autoStop` - flag to automatically stop profiler in PHP shutdown function. Default: `true`.
- `forceStop` - flag to force stop profiler during PHP shutdown if `autoStop` is disabled and profiler was manually started and is still running. Default: `true`.
- `triggerGetParam` - name of the GET param to manually trigger profiler to start. Default: no value, profiler runs on each request.
- `showOverlay` - flag to display overlay on page with links to report and callgraph result for current profiler run (if allowed). Not required to be `true` if you are using bundled panel for yii2-debug extension. Default: `true`.
- `libPath` - direct filesystem path or path alias to the directory with `xhprof_lib` contents.
- `htmlReportBaseUrl` - URL path to the directory with XHProf UI contents (`xhprof_html`). Default: `/xhprof_html` (assuming you have this folder inside your document root).
- `flagNoBuiltins` - enable/disable XHPROF_FLAGS_NO_BUILTINS flag for profiler ([XHProf constants](http://php.net/manual/xhprof.constants.php)). Default: `true`.
- `flagCpu` - enable/disable XHPROF_FLAGS_CPU flag for profiler ([XHProf constants](http://php.net/manual/xhprof.constants.php)). Default: `false`.
- `flagMemory` - enable/disable XHPROF_FLAGS_MEMORY flag for profiler ([XHProf constants](http://php.net/manual/xhprof.constants.php)). Default: `true`.
- `ignoredFunctions` - list of functions to ignore during profiling. Empty by default. *Note*: this option is not supported by `tideways_xhprof` extension.
- `blacklistedRoutes` - list of routes which profiler should ignore. Allowed wildcard `*` which means 'any alphanumeric value and one of this: `/`, `.`, `_`, `-`. Default: `['debug*']` (to ignore requests to the debug extension pages).

XHProf class configuration
-------------

Component from extension use own developed simple wrapper for `xhprof_enable` / `xhprof_disable` functions: [XHProf](XHProf.php) class. This class provide functionality to start/stop profiling, save reports and get URLs for reports with XHProf library by Facebook.

Available configuration options (applicable for `configure` method, see example below):

- `flagNoBuiltins` - enable/disable XHPROF_FLAGS_NO_BUILTINS flag for profiler ([XHProf constants](http://php.net/manual/xhprof.constants.php)). Default: `true`.
- `flagCpu` - enable/disable XHPROF_FLAGS_CPU flag for profiler ([XHProf constants](http://php.net/manual/xhprof.constants.php)). Default: `false`.
- `flagMemory` - enable/disable XHPROF_FLAGS_MEMORY flag for profiler ([XHProf constants](http://php.net/manual/xhprof.constants.php)). Default: `true`.
- `ignoredFunctions` - list of functions to ignore during profiling ([xhprof_enable() function](http://php.net/manual/ru/function.xhprof-enable.php)). Empty by default.
- `libPath` - direct filesystem path to the directory with `xhprof_lib` contents.
- `htmlUrlPath` - URL path to the directory with XHProf UI contents (`xhprof_html`).
- `runNamespace` - predefined value of namespace for current profiler run.

All options can be changed with setters (e.g. `setFlagCpu(<value>)`). For more details see the source code of the class.

Manual profiling
-------------

If you disable auto start (`autoStart` option) or stop (`autoStop` option) you can place code to start/stop profiler in any place of your code and be able to see report and callgraph result via overlay or debug panel.

To manual start you need to write some kind of next code:

```php
// create and configure instance of XHProf class
\stevad\xhprof\XHProf::getInstance()->configure(array(
    'flagNoBuiltins' => true,
    'flagCpu' => false,
    'flagMemory' => true,
    'runNamespace' => 'my-cool-namespace',
    'libPath' => '/var/www/html/xhprof/xhprof_lib',
    'htmlUrlPath' => 'http://test.local/xhprof/xhprof_html'
));

// start profiler
\stevad\xhprof\XHProf::getInstance()->run();
```

To manual stop you need to write next code:

```php
// stop profiler and get URLs to results and callgraph
$urls = \stevad\xhprof\XHProf::getInstance()->stop();

// Links:
// $urls[\stevad\xhprof\XHProf::TYPE_REPORT]
// $urls[\stevad\xhprof\XHProf::TYPE_CALLGRAPH]
```

_Note:_ If you use `XHProf` class (with or without this extension) - all profile results can be found on XHProf UI page (it's by default by xhprof developers).


Note for PHP 7 and XHProf
-------------

If you want to use this extension with PHP 7, you can use next forked version of xhprof extension: https://github.com/rustjason/xhprof.
This extension was tested on PHP 7.1.11 with extension built from provided repository.

Another better option - is to use officially ported xhprof extension by [tideways.io](https://tideways.io) - https://github.com/tideways/php-xhprof-extension.


Author
-------------

Copyright (c) 2015-2018 by Stevad.
