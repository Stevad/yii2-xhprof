<?php
/* @var \yii\web\View $this */
/* @var \stevad\xhprof\XHProfPanel $panel */
/* @var array $urls */
/* @var bool $enabled */
?>
<div class="yii-debug-toolbar-block">
    <a href="<?php echo $panel->getUrl() ?>">XHProf</a>
    <?php if ($enabled): ?>
        <a href="<?php echo $urls['report'] ?>" target="_blank"><span class="label label-info">Report</span></a>
        <a href="<?php echo $urls['callgraph'] ?>" target="_blank"><span class="label label-info">Callgraph</span></a>
    <?php else: ?>
        <span class="label">Not executed</span>
    <?php endif; ?>
</div>
