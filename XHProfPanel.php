<?php

namespace stevad\yii2xhprof;

use Yii;
use yii\web\View;

/**
 * Debug panel for official yii2-debug extension for fast access to XHProf results and list of previous runs with
 * ability to compare results between each others.
 *
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 */
class XHProfPanel extends \yii\debug\Panel
{
    public function getName()
    {
        return 'XHProf';
    }


    public function getDetail()
    {
        if (Yii::$app->get('xhprof', false) === null) {
            return Yii::$app->view->render('@yii2-xhprof/views/details_disabled_component.php');
        }

        $reports = Yii::$app->get('xhprof')->loadReports();
        rsort($reports);

        $urlTemplates = [
            'report' => Yii::$app->get('xhprof')->getReportBaseUrl() . '/' . XHProf::$urlTemplates['report'],
            'callgraph' => Yii::$app->get('xhprof')->getReportBaseUrl() . '/' . XHProf::$urlTemplates['callgraph'],
            'diff' => Yii::$app->get('xhprof')->getReportBaseUrl() . '/' . XHProf::$urlTemplates['diff']
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
                'ns' => $data['ns']
            ],
            'urls' => $urls,
            'reports' => $reports
        ]);
    }


    public function getSummary()
    {
        if (Yii::$app->get('xhprof', false) === null) {
            return null;
        }

        XHProf::getInstance()->setHtmlUrlPath(Yii::$app->get('xhprof')->getReportBaseUrl());

        $urls = [];
        $data = $this->data;
        if ($data['enabled']) {
            $urls['report'] = XHProf::getInstance()->getReportUrl($data['runId'], $data['ns']);
            $urls['callgraph'] = XHProf::getInstance()->getCallgraphUrl($data['runId'], $data['ns']);
        }

        return Yii::$app->view->render('@yii2-xhprof/views/panel', [
            'panel' => $this,
            'enabled' => $data['enabled'],
            'urls' => $urls
        ]);
    }


    public function save()
    {
        if (Yii::$app->get('xhprof', false) === null) {
            return null;
        }

        return Yii::$app->get('xhprof')->getReportInfo();
    }
}