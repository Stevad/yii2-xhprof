<?php

namespace stevad\xhprof;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\ErrorException;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use yii\web\View;

/**
 * XHProf application component for Yii Framework 2.x.
 * Uses original XHProf UI to display results.
 *
 * Designed to profile application from bootstrap until executing PHP shutdown function. You can also manually start
 * and stop profiler in any place of your code. By default component save last 25 reports. You can see them in bundled
 * debug panel for official yii2-debug extension (https://github.com/yiisoft/yii2-debug). All reports are also
 * available by default in XHProf UI (e.g. http://some.path.to/xhprof_html)
 *
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 * @date 16.11.2017
 */
class XHProfComponent extends \yii\base\Component implements BootstrapInterface
{
    /**
     * Enable/disable component in Yii
     *
     * @var bool
     */
    public $enabled = true;

    /**
     * Direct filesystem path or path alias to directory with reports file
     *
     * @var string
     */
    public $reportPath = '@runtime/xhprof';

    /**
     * How many reports to store in history file
     *
     * @var integer
     */
    public $maxReportsCount = 25;

    /**
     * Flag to automatically start profiling during component bootstrap. Set to false if you want to manually start
     * XHProf and start profiling.
     *
     * @var bool
     */
    public $autoStart = true;

    /**
     * Flag to automatically stop running profiling session in shutdown function
     *
     * @var bool
     */
    public $autoStop = true;

    /**
     * Force terminate profile process in shutdown function if it is still running with disabled `autoStop` flag
     *
     * @var bool
     */
    public $forceStop = true;

    /**
     * Set value to trigger profiling only by specified GET param with any value
     *
     * @var string
     */
    public $triggerGetParam;

    /**
     * If this component is used without yii2-debug extension, set true to show overlay with links to report and
     * callgraph. Otherwise, set false and add panel to yii2-debug (see readme for more details).
     *
     * @var bool
     */
    public $showOverlay = true;

    /**
     * Direct filesystem path or path alias to the 'xhprof_lib' directory
     *
     * @var string
     */
    public $libPath;

    /**
     * URL path to XHProf html reporting files without leading slash
     *
     * @var string
     */
    public $htmlReportBaseUrl = '/xhprof_html';

    /**
     * Enable/disable flag XHPROF_FLAGS_NO_BUILTINS (see http://php.net/manual/ru/xhprof.constants.php)
     *
     * @var bool
     */
    public $flagNoBuiltins = true;

    /**
     * Enable/disable flag XHPROF_FLAGS_CPU (see http://php.net/manual/ru/xhprof.constants.php)
     * Default: false. Reason - some overhead in calculation on linux OS
     *
     * @var bool
     */
    public $flagCpu = false;

    /**
     * Enable/disable flag XHPROF_FLAGS_MEMORY (see http://php.net/manual/ru/xhprof.constants.php)
     *
     * @var bool
     */
    public $flagMemory = true;

    /**
     * List of functions to ignore during profiling (http://php.net/manual/ru/function.xhprof-enable.php)
     *
     * @var array
     */
    public $ignoredFunctions = [];

    /**
     * List of routes to not run xhprof on.
     *
     * @var array
     */
    public $blacklistedRoutes = ['debug*'];

    /**
     * Current report details
     *
     * @var array
     */
    private $reportInfo;

    /**
     * Path to the temporary directory with reports
     *
     * @var string
     */
    private $reportSavePath;


    public function init()
    {
        Yii::setAlias('@yii2-xhprof', __DIR__);

        parent::init();
    }

    /**
     * Initialize component and check path to xhprof library files. Start profiling and add overlay (if allowed
     * by configuration).
     *
     * @return void
     * @throws ErrorException
     */
    public function bootstrap($app)
    {
        if (!$this->enabled
            || ($this->triggerGetParam !== null && $app->request->getQueryParam($this->triggerGetParam) === null)
            || $this->isRouteBlacklisted()
        ) {
            return;
        }

        if (empty($this->libPath)) {
            throw new ErrorException('Lib path cannot be empty');
        }

        $libPath = $this->libPath;
        if (\strpos($libPath, '@') === 0) {
            $libPath = Yii::getAlias($libPath);
        }

        XHProf::getInstance()->configure([
            'flagNoBuiltins' => $this->flagNoBuiltins,
            'flagCpu' => $this->flagCpu,
            'flagMemory' => $this->flagMemory,
            'ignoredFunctions' => $this->ignoredFunctions,
            'runNamespace' => Yii::$app->id,
            'libPath' => $libPath,
            'htmlUrlPath' => $this->getReportBaseUrl(),
        ]);

        if ($this->autoStart) {
            XHProf::getInstance()->run();
        }

        if ($this->showOverlay && !$app->request->isAjax) {
            OverlayAsset::register($app->view);
            $app->view->on(View::EVENT_END_BODY, [$this, 'appendResultsOverlay']);
        }

        \register_shutdown_function([$this, 'stopProfiling']);
    }

    /**
     * Check if current route is blacklisted (should not be processed)
     *
     * @return bool
     */
    private function isRouteBlacklisted()
    {
        $result = false;
        $routes = $this->blacklistedRoutes;
        if (\is_a(Yii::$app, yii\web\Application::className())) {
            $requestRoute = Yii::$app->getUrlManager()->parseRequest(Yii::$app->getRequest())[0];
        } else {
            $request = Yii::$app->getRequest()->getParams();
            if (!isset($request[0])) {
                return true;
            }
            $requestRoute = $request[0];
        }

        foreach ($routes as $route) {
            $route = \str_replace('*', '([a-zA-Z0-9\/\-\._]{0,})', \str_replace('/', '\/', '^' . $route));
            if (\preg_match("/{$route}/", $requestRoute) !== 0) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Configure XHProf instance and start profiling
     *
     * @return void
     */
    public function beginProfiling()
    {
        XHProf::getInstance()->run();
    }

    /**
     * Stop profiling and save report
     *
     * @return void
     */
    public function stopProfiling()
    {
        $XHProf = XHProf::getInstance();

        if ($XHProf->isStarted()
            && $XHProf->getStatus() === XHProf::STATUS_RUNNING
            && ($this->autoStop || (!$this->autoStop && $this->forceStop))
        ) {
            $XHProf->stop();
        }

        if ($this->isActive()) {
            $this->saveReport();
        }
    }

    /**
     * Get base URL part to the XHProf UI
     *
     * @return string
     */
    public function getReportBaseUrl()
    {
        if (\strpos($this->htmlReportBaseUrl, '/') === 0) {
            return Yii::$app->getRequest()->getAbsoluteUrl() . $this->htmlReportBaseUrl;
        }

        return $this->htmlReportBaseUrl;
    }

    /**
     * Get if component enabled and xhprof profiler is currently started
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->enabled && XHProf::getInstance()->isStarted();
    }

    /**
     * Save current report to history file and check size of the history.
     *
     * @return void
     */
    private function saveReport()
    {
        $reports = $this->loadReports();
        $reports[] = $this->getReportInfo();

        if (\count($reports) > $this->maxReportsCount) {
            \array_shift($reports);
        }

        $reportsFile = "{$this->getReportSavePath()}/reports.json";
        \file_put_contents($reportsFile, Json::encode($reports));
    }

    /**
     * Load list of previous reports from JSON file
     *
     * @return array
     */
    public function loadReports()
    {
        $reportsFile = "{$this->getReportSavePath()}/reports.json";
        $reports = [];

        if (\is_file($reportsFile)) {
            $reports = Json::decode(\file_get_contents($reportsFile));
        }

        return $reports;
    }

    /**
     * Get reports save path
     *
     * @return string
     */
    public function getReportSavePath()
    {
        if ($this->reportSavePath === null) {
            $path = $this->reportPath;
            if ($path === null) {
                $path = Yii::$app->getRuntimePath() . '/xhprof';
            } elseif (\strpos($path, '@') === 0) {
                $path = Yii::getAlias($path);
            }

            if (!\is_dir($path)) {
                FileHelper::createDirectory($path, 0777, true);
            }

            $this->reportSavePath = $path;
        }

        return $this->reportSavePath;
    }

    /**
     * Get report details for current profiling process. Info consists of:
     * - unique run identifier (runId)
     * - namespace for run (ns, current application ID by default)
     * - requested URL (url)
     * - time of request (time)
     *
     * @return array key-valued list
     */
    public function getReportInfo()
    {
        if (!$this->isActive()) {
            return [
                'enabled' => false,
                'runId' => null,
                'ns' => null,
                'url' => null,
                'time' => null,
            ];
        }

        if ($this->reportInfo === null) {
            if (\is_a(Yii::$app, yii\web\Application::className())) {
                $request = Yii::$app->getRequest();
                $url = $request->getHostInfo() . $request->getUrl();
            } else {
                $url = Yii::$app->controller->id . '/' . Yii::$app->controller->action->id;
            }
            $this->reportInfo = [
                'enabled' => true,
                'runId' => XHProf::getInstance()->getRunId(),
                'ns' => XHProf::getInstance()->getRunNamespace(),
                'url' => $url,
                'time' => \microtime(true),
            ];
        }

        return $this->reportInfo;
    }

    /**
     * Add code to display own overlay with links to report and callgraph for current profile run
     *
     * @return void
     */
    public function appendResultsOverlay()
    {
        $XHProf = XHProf::getInstance();
        if (!$XHProf->isStarted()) {
            return;
        }

        $data = $this->getReportInfo();
        $reportUrl = $XHProf->getReportUrl($data['runId'], $data['ns']);
        $callgraphUrl = $XHProf->getCallgraphUrl($data['runId'], $data['ns']);

        echo <<<EOD
<script type="text/javascript">
(function() {
    var overlay = document.createElement('div');
    overlay.setAttribute('id', 'xhprof-overlay');
    overlay.innerHTML = '<div class="xhprof-header">XHProf</div><a href="{$reportUrl}" target="_blank">Report</a><a href="{$callgraphUrl}" target="_blank">Callgraph</a>';
    document.getElementsByTagName('body')[0].appendChild(overlay);
})();
</script>
EOD;
    }
}
