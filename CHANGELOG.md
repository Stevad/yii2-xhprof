Changelog
===

#### dev-master

- ... here is new notes ...


#### 1.0.0 - 16.11.2017

- First stable release
- Refactoring of the codebase. Important changes:
    - renamed `manualStart` to `autoStart`, `manualStop` to `autoStop` and changed logic for this flags
    - removed ability to set own ID for profiling run
    - changed namespace from `stevad\yii2xhprof` to `stevad\xhprof`
- Added option `ignoredFunctions` to set list of ignored functions during profiling session
- Changed logic of auto-profiling: start during bootstrap and stop in PHP shutdown function
- Minimal `yii2-debug` version is 2.0.6
- Added note about using XHProf on PHP 7.x


#### 0.2.0 - 03.04.2017

- Added profiling of the console commands.


#### 0.1.1 - 18.10.2015

- Fixed bad path to runtime directory to store xhprof reports for extension.


#### 0.1.0 - 18.10.2015

First public release
