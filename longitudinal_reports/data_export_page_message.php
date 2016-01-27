<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

if ($Proj->longitudinal && !isset($_GET['addedit']) && !isset($_GET['report_id'])) {
    print RCView::div(array('id' => 'lrMsg', 'class' => 'gray',
        'style' => 'display:none;margin:10px;text-align:center;'),
            RCView::img(array('src' => APP_PATH_IMAGES.'information_frame.png')) .
            RCView::span(array('style' => ''), "Take a look at the ".
                            RCView::a(array('style' => 'font-weight:bold;',
                                'href' => APP_PATH_WEBROOT_FULL.'plugins/longitudinal_reports/index.php?pid='.$_GET['pid']), 
                                    "Longitudinal Reports") . 
                    " plugin for row-per-participant longitudinal data...")
            );
}
?>
<script type="text/javascript">
    $(function(){
        setTimeout(function(){
            $('#lrMsg').slideToggle('slow');
        },500);
    });
</script>
