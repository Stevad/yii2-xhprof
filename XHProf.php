<?php

namespace stevad\yii2xhprof;

/**
 * Class XHProf
 * Simple wrapper for XHProf with basic functionality to configure and run/stop profiling.
 * Also provide methods to get proper URL for report, callgraph or diff results.
 *
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 */
class XHProf
{
    const STATUS_RUNNING = 0;
    const STATUS_STOPPED = 1;

    /**
     * List of templates to get URL for report, callgraph or diff results
     * @var array
     */
    public static $urlTemplates = [
        'report' => 'index.php?run=%%ID%%&source=%%NAMESPACE%%',
        'callgraph' => 'callgraph.php?run=%%ID%%&source=%%NAMESPACE%%',
        'diff' => 'index.php?run1=%%ID1%%&run2=%%ID2%%&source=%%NAMESPACE%%'
    ];

    /**
     * Single instance to work with profiler
     * @var self
     */
    private static $instance;

    /**
     * Enable/disable flag XHPROF_FLAGS_NO_BUILTINS (http://php.net/manual/ru/xhprof.constants.php)
     * @var boolean
     */
    private $flagNoBuiltins = true;

    /**
     * Enable/disable flag XHPROF_FLAGS_CPU (http://php.net/manual/ru/xhprof.constants.php)
     * Default: false. Reason - some overhead in calculation on linux OS
     * @var boolean
     */
    private $flagCpu = false;

    /**
     * Enable/disable flag XHPROF_FLAGS_MEMORY (http://php.net/manual/ru/xhprof.constants.php)
     * @var boolean
     */
    private $flagMemory = true;

    /**
     * Path to directory with 'xhprof_lib' contents
     * @var string
     */
    private $libPath;

    /**
     * URL path to 'xhprof_html' directory (to create URL for report and others)
     * @var string
     */
    private $htmlUrlPath;

    /**
     * Flag to get info if XHProf was started
     * @var bool
     */
    private $started = false;

    /**
     * Identifier for profile run. Must be unique. If not set - uniqid function will be used
     * @var string
     */
    private $runId;

    /**
     * Namespace for profile run results. To compare several runs they must be with equal namespace
     * @var string
     */
    private $runNamespace = 'yii2-xhprof';

    /**
     * Status of XHProf
     * @var int
     */
    private $runStatus = self::STATUS_STOPPED;

    /**
     * Final construct to prevent from overloading.
     * Checks if xhprof extension is available
     * @throws \RuntimeException
     */
    final protected function __construct()
    {
        if (!extension_loaded('xhprof')) {
            throw new \RuntimeException('XHProf extension is not available');
        }
    }

    /**
     * Get single instance of XHProf
     * @return XHProf
     * @throws \RuntimeException
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Configure instance of XHProf with key-valued array, where key must be the name of private attribute of the class
     * with available public setter method
     * @param array $config key-valued list of params
     */
    public function configure(array $config)
    {
        foreach ($config as $key => $value) {
            $methodName = 'set' . ucfirst($key);
            if (method_exists($this, $methodName)) {
                $this->$methodName($value);
            }
        }
    }

    /**
     * Set value to use flag XHPROF_FLAGS_NO_BUILTINS (http://php.net/manual/ru/xhprof.constants.php)
     * @param boolean $flagNoBuiltins
     */
    public function setFlagNoBuiltins($flagNoBuiltins)
    {
        $this->flagNoBuiltins = $flagNoBuiltins;
    }

    /**
     * Set value to use flag XHPROF_FLAGS_CPU (http://php.net/manual/ru/xhprof.constants.php)
     * @param boolean $flagCpu
     */
    public function setFlagCpu($flagCpu)
    {
        $this->flagCpu = $flagCpu;
    }

    /**
     * Set value to use flag XHPROF_FLAGS_MEMORY (http://php.net/manual/ru/xhprof.constants.php)
     * @param boolean $flagMemory
     */
    public function setFlagMemory($flagMemory)
    {
        $this->flagMemory = $flagMemory;
    }

    /**
     * Set path to directory with 'xhprof_lib' contents.
     * @param string $libPath path to the directory
     * @throws \InvalidArgumentException
     */
    public function setLibPath($libPath)
    {
        if (empty($libPath) || !is_dir($libPath)) {
            throw new \InvalidArgumentException('Lib path cannot be empty and should point to existing directory with xhprof_lib content');
        }

        if (!file_exists($libPath . '/utils/xhprof_lib.php') || !file_exists($libPath . '/utils/xhprof_runs.php')) {
            throw new \InvalidArgumentException('Lib path does not contain necessary files for XHProf (utils/xhprof_lib.php, utils/xhprof_runs.php)');
        }

        $this->libPath = $libPath;
    }

    /**
     * Set URL path to the 'xhprof_html' directory (which contains XHProf UI)
     * @param string $htmlUrlPath
     * @throws \InvalidArgumentException
     */
    public function setHtmlUrlPath($htmlUrlPath)
    {
        if (empty($htmlUrlPath) || filter_var($htmlUrlPath, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('HtmlUrlPath cannot be blank and must be a valid URL value');
        }
        $this->htmlUrlPath = rtrim($htmlUrlPath, '\\');
    }

    /**
     * Get if profiler was started
     * @return boolean
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Get status of running
     * @return int
     */
    public function getStatus()
    {
        return $this->runStatus;
    }

    /**
     * Get URL to the profiler report.
     * @param string $id identifier of profiler run. If not specified will be used value from current run
     * @param string $namespace namespace of the profiler run. If not specified will be used value from current run
     * @return string
     */
    public function getReportUrl($id = null, $namespace = null)
    {
        if ($id === null) {
            $id = $this->getRunId();
        }

        if ($namespace === null) {
            $namespace = $this->getRunNamespace();
        }

        return $this->getUrl('report', [$id], $namespace);
    }

    /**
     * Get unique run identifier for active profiler
     * @return string
     */
    public function getRunId()
    {
        if ($this->runId === null) {
            $this->runId = uniqid('', false);
        }

        return $this->runId;
    }

    /**
     * Set own value of identifier for current profile run (should be unique, to separate all profiler runs)
     * @param string $runId
     * @throws \InvalidArgumentException
     */
    public function setRunId($runId)
    {
        if (empty($runNamespace)) {
            throw new \InvalidArgumentException('Identifier cannot be blank');
        }

        $this->runId = $runId;
    }

    /**
     * Get namespace for current run
     * @return string
     */
    public function getRunNamespace()
    {
        return $this->runNamespace;
    }

    /**
     * Set namespace for current profiler run
     * @param string $runNamespace
     * @throws \InvalidArgumentException
     */
    public function setRunNamespace($runNamespace)
    {
        if (empty($runNamespace)) {
            throw new \InvalidArgumentException('Namespace cannot be blank');
        }
        $this->runNamespace = $runNamespace;
    }

    /**
     * Utility method to get value of the URL with specified type and params
     * @param string $type type of the URL. Allowed values are: 'report', 'callgraph', 'diff'
     * @param array $ids one or two identifiers for URL creation
     * @param string $namespace profile namespace
     * @return string
     * @throws \RuntimeException
     */
    private function getUrl($type, array $ids, $namespace)
    {
        if (empty($this->htmlUrlPath)) {
            throw new \RuntimeException('Html URL path not specified');
        }

        if ($type !== 'diff') {
            $url = $this->htmlUrlPath . '/' . str_replace(
                    ['%%ID%%', '%%NAMESPACE%%'],
                    [$ids[0], $namespace],
                    self::$urlTemplates[$type]
                );
        } else {
            $url = $this->htmlUrlPath . '/' . str_replace(
                    ['%%ID1%%', '%%ID2%%', '%%NAMESPACE%%'],
                    [$ids[0], $ids[1], $namespace],
                    self::$urlTemplates[$type]
                );
        }

        return $url;
    }

    /**
     * Get URL to the callgraph report.
     * @param string $id identifier of profiler run. If not specified will be used value from current run
     * @param string $namespace namespace of the profiler run. If not specified will be used value from current run
     * @return string
     */
    public function getCallgraphUrl($id = null, $namespace = null)
    {
        if ($id === null) {
            $id = $this->getRunId();
            $namespace = $this->getRunNamespace();
        }

        return $this->getUrl('callgraph', [$id], $namespace);
    }

    /**
     * Get URL to the diff report of two specified runs.
     * @param string $id1 identifier of first profiler run
     * @param string $id2 identifier of another profiler run to compare with first
     * @param string $namespace namespace of the profiler run. If not specified will be used value from current run
     * @return string
     */
    public function getDiffUrl($id1, $id2, $namespace = null)
    {
        if ($namespace === null) {
            $namespace = $this->getRunNamespace();
        }

        return $this->getUrl('callgraph', [$id1, $id2], $namespace);
    }

    /**
     * Get flags and run XHProf. Only one start per session is allowed
     * @throws \RuntimeException
     */
    public function run()
    {
        if ($this->started) {
            throw new \RuntimeException('Cannot run XHProf again - already started');
        }

        $flags = $this->getFlags();
        if ($flags !== 0) {
            xhprof_enable($flags);
        } else {
            xhprof_enable();
        }

        $this->runStatus = self::STATUS_RUNNING;
        $this->started = true;
    }

    /**
     * Calculate flags for 'xhprof_enable' function
     * @return int
     */
    private function getFlags()
    {
        $flags = 0;
        if ($this->flagNoBuiltins) {
            $flags += XHPROF_FLAGS_NO_BUILTINS;
        }
        if ($this->flagCpu) {
            $flags += XHPROF_FLAGS_CPU;
        }
        if ($this->flagMemory) {
            $flags += XHPROF_FLAGS_MEMORY;
        }

        return $flags;
    }

    /**
     * Stop profiling and save results with configured identifier and namespace
     * @throws \RuntimeException
     */
    public function stop()
    {
        if (!$this->started) {
            throw new \RuntimeException('Cannot stop XHProf - not started');
        }

        $runId = $this->getRunId();
        $runNamespace = $this->runNamespace;

        $data = xhprof_disable();

        include_once($this->libPath . '/utils/xhprof_lib.php');
        include_once($this->libPath . '/utils/xhprof_runs.php');

        $xhprof = new \XHProfRuns_Default();
        $xhprof->save_run($data, $runNamespace, $runId);

        $this->runStatus = self::STATUS_STOPPED;
    }
}