<?php
/* @var \yii\web\View $this */
/* @var \stevad\xhprof\XHProfPanel $panel */
/* @var array $urls */
/* @var bool $enabled */
?>
<div class="yii-debug-toolbar__block">
    <a href="<?php echo $panel->getUrl() ?>">XHProf</a>
    <?php if ($enabled): ?>
        <a href="<?php echo $urls['report'] ?>" target="_blank"><span class="yii-debug-toolbar__label yii-debug-toolbar__label_info">Report</span></a>
        <a href="<?php echo $urls['callgraph'] ?>" target="_blank"><span class="yii-debug-toolbar__label yii-debug-toolbar__label_info">Callgraph</span></a>
    <?php else: ?>
        <span class="yii-debug-toolbar__label">Not executed</span>
    <?php endif; ?>
</div>
