<?php

namespace stevad\yii2xhprof;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\ErrorException;
use yii\helpers\Json;
use yii\web\Application;
use yii\web\View;

/**
 * XHProf application component for Yii Framework 2.x.
 * Uses original XHProf UI to display results.
 *
 * Designed to profile application from `Application::EVENT_BEFORE_REQUEST` to `Application::EVENT_AFTER_REQUEST`
 * events. You can also manually start and stop profiler in any place of your code. By default component save
 * last 25 reports. You can see them in bundled debug panel for official yii2-debug extension
 * (https://github.com/yiisoft/yii2-debug). All reports are also available by default in XHProf UI
 * (e.g. http://some.path.to/xhprof_html)
 *
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 */
class XHProfComponent extends \yii\base\Component implements BootstrapInterface
{
    /**
     * Enable/disable component in Yii
     * @var bool
     */
    public $enabled = true;

    /**
     * Path alias to directory with reports file
     * @var string
     */
    public $reportPathAlias = 'application.runtime.xhprof';

    /**
     * How many reports to store in history file
     * @var integer
     */
    public $maxReportsCount = 25;

    /**
     * Set true to manually create instance of XHProf object and start profiling. Disabled by default, profile
     * is started on `Application::EVENT_BEFORE_REQUEST` event
     * @var bool
     */
    public $manualStart = false;

    /**
     * Set true to manually stop profiling. Disabled by default, profile is stopped on
     * `Application::EVENT_AFTER_REQUEST` event.
     * @var bool
     */
    public $manualStop = false;

    /**
     * Force terminate profile process on `Application::EVENT_AFTER_REQUEST` event if it is still running with
     * enabled manual stop
     * @var bool
     */
    public $forceStop = true;

    /**
     * Set value to trigger profiling only by specified GET param with any value
     * @var string
     */
    public $triggerGetParam;

    /**
     * If this component is used without yii2-debug extension, set true to show overlay with links to report and
     * callgraph. Otherwise, set false and add panel to yii2-debug (see readme for more details).
     * @var bool
     */
    public $showOverlay = true;

    /**
     * Path alias to the 'xhprof_lib' directory. If not set, value of $libPath will be used instead
     * @var string
     */
    public $libPathAlias;

    /**
     * Direct filesystem path to the 'xhprof_lib' directory
     * @var string
     */
    public $libPath;

    /**
     * URL path to XHProf html reporting files without leading slash
     * @var string
     */
    public $htmlReportBaseUrl = '/xhprof_html';

    /**
     * Enable/disable flag XHPROF_FLAGS_NO_BUILTINS (see http://php.net/manual/ru/xhprof.constants.php)
     * @var bool
     */
    public $flagNoBuiltins = true;

    /**
     * Enable/disable flag XHPROF_FLAGS_CPU (see http://php.net/manual/ru/xhprof.constants.php)
     * Default: false. Reason - some overhead in calculation on linux OS
     * @var bool
     */
    public $flagCpu = false;

    /**
     * Enable/disable flag XHPROF_FLAGS_MEMORY (see http://php.net/manual/ru/xhprof.constants.php)
     * @var bool
     */
    public $flagMemory = true;

    /**
     * List of routes to not run xhprof on.
     * @var array
     */
    public $blacklistedRoutes = ['debug*'];

    /**
     * Current report details
     * @var array
     */
    private $reportInfo;

    /**
     * Path to the temporary directory with reports
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

        if (empty($this->libPath) && empty($this->libPathAlias)) {
            throw new ErrorException('Both libPath and libPathAlias cannot be empty. Provide at least one of the value');
        }

        if (!$this->manualStart) {
            $app->on(Application::EVENT_BEFORE_REQUEST, [$this, 'beginProfiling']);
        }

        if ($this->showOverlay && !$app->request->isAjax) {
            OverlayAsset::register($app->view);
            $app->view->on(View::EVENT_END_BODY, [$this, 'appendResultsOverlay']);
        }

        $app->on(Application::EVENT_AFTER_REQUEST, [$this, 'stopProfiling']);
    }

    /**
     * Check if current route is blacklisted (should not be processed)
     * @return bool
     */
    private function isRouteBlacklisted()
    {
        $result = false;
        $routes = $this->blacklistedRoutes;
        $requestRoute = Yii::$app->getUrlManager()->parseRequest(Yii::$app->getRequest());

        foreach ($routes as $route) {
            $route = str_replace('*', '([a-zA-Z0-9\/\-\._]{0,})', str_replace('/', '\/', '^' . $route));
            if (preg_match("/{$route}/", $requestRoute[0]) !== 0) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Configure XHProf instance and start profiling
     */
    public function beginProfiling()
    {
        $libPath = $this->libPath;
        if (!empty($this->libPathAlias)) {
            $libPath = Yii::getAlias($this->libPathAlias);
        }

        XHProf::getInstance()->configure([
            'flagNoBuiltins' => $this->flagNoBuiltins,
            'flagCpu' => $this->flagCpu,
            'flagMemory' => $this->flagMemory,
            'runNamespace' => Yii::$app->id,
            'libPath' => $libPath,
            'htmlUrlPath' => $this->getReportBaseUrl()
        ]);

        XHProf::getInstance()->run();
    }

    /**
     * Get base URL part to the XHProf UI
     * @return string
     */
    public function getReportBaseUrl()
    {
        if (strpos($this->htmlReportBaseUrl, '://') === false) {
            return Yii::$app->getRequest()->getAbsoluteUrl() . $this->htmlReportBaseUrl;
        }

        return $this->htmlReportBaseUrl;
    }

    /**
     * Stop profiling and save report
     */
    public function stopProfiling()
    {
        $XHProf = XHProf::getInstance();

        if ($XHProf->isStarted() && $XHProf->getStatus() === XHProf::STATUS_RUNNING
            && (!$this->manualStop || ($this->manualStop && $this->forceStop))
        ) {
            $XHProf->stop();
        }

        if ($this->isActive()) {
            $this->saveReport();
        }
    }

    /**
     * Get if component enabled and xhprof profiler is currently started
     * @return bool
     */
    public function isActive()
    {
        return $this->enabled && XHProf::getInstance()->isStarted();
    }

    /**
     * Save current report to history file and check size of the history.
     */
    private function saveReport()
    {
        $reports = $this->loadReports();
        $reports[] = $this->getReportInfo();

        if (count($reports) > $this->maxReportsCount) {
            array_shift($reports);
        }

        $reportsFile = "{$this->getReportSavePath()}/reports.json";
        file_put_contents($reportsFile, Json::encode($reports));
    }

    /**
     * Load list of previous reports from JSON file
     * @return array
     */
    public function loadReports()
    {
        $reportsFile = "{$this->getReportSavePath()}/reports.json";
        $reports = [];

        if (is_file($reportsFile)) {
            $reports = Json::decode(file_get_contents($reportsFile));
        }

        return $reports;
    }

    /**
     * Get reports save path
     * @return string
     */
    public function getReportSavePath()
    {
        if ($this->reportSavePath === null) {
            if ($this->reportPathAlias === null) {
                $path = Yii::$app->getRuntimePath() . '/xhprof';
            } else {
                $path = Yii::getAlias($this->reportPathAlias);
            }

            if (!is_dir($path)) {
                mkdir($path);
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
                'time' => null
            ];
        }

        if ($this->reportInfo === null) {
            $request = Yii::$app->getRequest();
            $this->reportInfo = [
                'enabled' => true,
                'runId' => XHProf::getInstance()->getRunId(),
                'ns' => XHProf::getInstance()->getRunNamespace(),
                'url' => $request->getHostInfo() . $request->getUrl(),
                'time' => microtime(true)
            ];
        }

        return $this->reportInfo;
    }

    /**
     * Add code to display own overlay with links to report and callgraph for current profile run
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