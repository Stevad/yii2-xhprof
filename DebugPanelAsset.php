<?php

namespace stevad\xhprof;

use yii\web\AssetBundle;

/**
 * Class DebugPanelAsset
 * Debug panel asset
 *
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 * @date 16.11.2017
 */
class DebugPanelAsset extends AssetBundle
{
    public $sourcePath = '@vendor/stevad/yii2-xhprof';

    public $css = [
        'assets/xhprof.css'
    ];

    public $js = [
        'assets/xhprof.js'
    ];
}
