<?php

namespace stevad\xhprof;

/**
 * Class OverlayAsset
 * Asset for overlay
 *
 * @author Vadym Stepanov <vadim.stepanov.ua@gmail.com>
 * @date 16.11.2017
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
