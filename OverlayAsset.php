<?php

namespace stevad\yii2xhprof;

/**
 * Class OverlayAsset
 * Asset for overlay
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 */
class OverlayAsset extends DebugPanelAsset
{
    public $sourcePath = '@vendor/stevad/yii2-xhprof';

    public $css = [
        'assets/xhprof.css'
    ];

    public $js = [
    ];
}