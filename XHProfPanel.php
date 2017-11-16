<?php

namespace stevad\xhprof;

use Yii;
use yii\web\View;

/**
 * Debug panel for official yii2-debug extension for fast access to XHProf results and list of previous runs with
 * ability to compare results between each others.
 *
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 * @date 16.11.2017
 */
class XHProfPanel extends \yii\debug\Panel
{
    public function getName()
    {
        return 'XHProf';
    }


    public function getDetail()
    {
        if ($this->getComponent() === null) {
            return Yii::$app->view->render('@yii2-xhprof/views/details_disabled_component.php');
        }

        $reports = $this->getComponent()->loadReports();
        \rsort($reports);

        $urlTemplates = [
            'report' => $this->getComponent()->getReportBaseUrl() . '/' . XHProf::$urlTemplates['report'],
            'callgraph' => $this->getComponent()->getReportBaseUrl() . '/' . XHProf::$urlTemplates['callgraph'],
            'diff' => $this->getComponent()->getReportBaseUrl() . '/' . XHProf::$urlTemplates['diff'],
        ];

        $js = <<<EOD
XHProf.urlReportTemplate = '{$urlTemplates['report']}';
XHProf.urlCallgraphTemplate = '{$urlTemplates['callgraph']}';
XHProf.urlDiffTemplate = '{$urlTemplates['diff']}';
EOD;

        DebugPanelAsset::register(Yii::$app->view);
        Yii::$app->view->registerJs($js, View::POS_END);

        $urls = [];
        $data = $this->data;

        if ($data['enabled']) {
            $urls['report'] = XHProf::getInstance()->getReportUrl($data['runId'], $data['ns']);
            $urls['callgraph'] = XHProf::getInstance()->getCallgraphUrl($data['runId'], $data['ns']);
        }

        return Yii::$app->view->render('@yii2-xhprof/views/details', [
            'panel' => $this,
            'enabled' => $data['enabled'],
            'run' => [
                'id' => $data['runId'],
                'ns' => $data['ns'],
            ],
            'urls' => $urls,
            'reports' => $reports,
        ]);
    }


    public function getSummary()
    {
        if ($this->getComponent() === null) {
            return null;
        }

        XHProf::getInstance()->setHtmlUrlPath($this->getComponent()->getReportBaseUrl());

        $urls = [];
        $data = $this->data;
        if ($data['enabled']) {
            $urls['report'] = XHProf::getInstance()->getReportUrl($data['runId'], $data['ns']);
            $urls['callgraph'] = XHProf::getInstance()->getCallgraphUrl($data['runId'], $data['ns']);
        }

        return Yii::$app->view->render('@yii2-xhprof/views/panel', [
            'panel' => $this,
            'enabled' => $data['enabled'],
            'urls' => $urls,
        ]);
    }


    public function save()
    {
        if ($this->getComponent() === null) {
            return null;
        }

        return $this->getComponent()->getReportInfo();
    }

    /**
     * Get profiler component
     *
     * @return XHProfComponent
     */
    public function getComponent()
    {
        return Yii::$app->get('xhprof', false);
    }
}
