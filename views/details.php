<?php
/* @var \yii\web\View $this */
/* @var \stevad\yii2xhprof\XHProfPanel $panel */
/* @var array $urls */
/* @var array $reports */
/* @var array $run */
/* @var bool $enabled */
?>
<h3>XHProf Reports</h3>

<h4>Current profiler run:</h4>
<?php if ($enabled): ?>
    <ul>
        <li><a href="<?php echo $urls['report'] ?>" target="_blank">Detailed report</a></li>
        <li><a href="<?php echo $urls['callgraph'] ?>" target="_blank">Callgraph</a></li>
    </ul>
<?php else: ?>
    <p>XHProf was not used for this request.</p>
<?php endif; ?>

<h4>Previous runs (<?php echo Yii::$app->get('xhprof')->maxReportsCount ?> items max):</h4>
<p>You can open report, callgraph or diff with current run for any of the previous profiler runs. Also you can compare
    any previous runs between themselves (check radios and click on button "Compare selected").</p>

<table id="xhprof-reports" class="table table-condensed table-bordered table-striped table-hover">
    <thead>
    <tr>
        <th style="width: 35px;">#</th>
        <th colspan="2" style="width: 75px;">Compare</th>
        <th>Request</th>
        <th style="width:180px">Date and Time</th>
        <th style="width:65px">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($reports as $i => $report): ?>
        <tr>
            <td><?php echo $i + 1 ?></td>
            <td>
                <?php if ($run['id'] !== $report['runId']): ?>
                    <div><input type="radio" name="xhprof[id1]" value="<?php echo $report['runId'] ?>" data-ns="<?php echo $report['ns'] ?>" data-type="1"></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($run['id'] !== $report['runId']): ?>
                    <input type="radio" name="xhprof[id2]" value="<?php echo $report['runId'] ?>"
                           data-ns="<?php echo $report['ns'] ?>" data-type="2">
                <?php endif; ?>
            </td>
            <td><?php echo \yii\helpers\Html::encode($report['url']); ?></td>
            <td><?php echo date('Y-m-d H:i:s', $report['time']) ?>.<?php echo substr((string)($report['time'] - floor($report['time'])), 2, 3) ?></td>
            <td style="text-align: center;">
                <a href="#" class="xhprof-report" title="View report" data-id="<?php echo $report['runId'] ?>"
                   data-ns="<?php echo $report['ns'] ?>" target="_blank"><i class="glyphicon glyphicon-file"></i></a>
                <a href="#" class="xhprof-callgraph" title="View callgraph" data-id="<?php echo $report['runId'] ?>"
                   data-ns="<?php echo $report['ns'] ?>" target="_blank"><i class="glyphicon glyphicon-retweet"></i></a>
                <?php if ($run['id'] !== $report['runId'] && $enabled): ?>
                    <a href="#" class="xhprof-diff" title="Compare with this run"
                       data-id="<?php echo $report['runId'] ?>" data-id2="<?php echo $run['id'] ?>"
                       data-ns="<?php echo $run['ns'] ?>" target="_blank"><i class="glyphicon glyphicon-share-alt"></i></a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<button class="btn btn-default disabled xhprof-compare">Compare selected</button>

<p></p>