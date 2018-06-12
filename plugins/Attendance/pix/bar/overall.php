<?php

/**
 * Overall bar chart
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 */
//die('Temporarily disabled');

require_once '../../../../../../config.php';
require_once '../../../../lib.php';

if (!isset($_SESSION['pp_user'])){
    require_login();
}

define('PCHART_PATH', '../../../../lib/pChart2.1.3/');
require_once PCHART_PATH.'class/pDraw.class.php';
require_once PCHART_PATH.'class/pImage.class.php';
require_once PCHART_PATH.'class/pData.class.php';

if (!isset($_GET['studentID'])) exit;
$studentID = $_GET['studentID'];

$ELBP = \ELBP\ELBP::instantiate();

$access = $ELBP->getUserPermissions($studentID);
if (!$ELBP->anyPermissionsTrue($access));

$ELBP->loadStudent($studentID);

$attendanceObject = $ELBP->getPlugin("Attendance");

if (!$attendanceObject){
    \elbp_output_missing_image();
}


$attendanceObject->loadStudent($studentID);

$__periods = $attendanceObject->getPeriods();

$__types = $attendanceObject->getTypes();
$__width = count($__periods) * 100;
$__graph_width = $__width;
$__graph_x2 = 50 + $__graph_width;


$myData = new pData();
$myData->loadPalette( PCHART_PATH."palettes/elbp.color", true);


// Types
$num = 2;
foreach ($__types as $type)
{
    
    $__values = array();
    foreach($__periods as $period)
    {
        $val = $attendanceObject->getRecord( array("period"=>$period, "type"=>$type) );
        $__values[] = (is_numeric($val)) ? $val : 0;
    }
    
    $myData->addPoints($__values,"Serie{$num}");
    $myData->setSerieDescription("Serie{$num}",$type);
    $myData->setSerieOnAxis("Serie{$num}",0);
    $num++;
}


$myData->addPoints($__periods,"Absissa");
$myData->setAbscissa("Absissa");


$myData->setAxisPosition(0,AXIS_POSITION_LEFT);
$myData->setAxisName(0,"%");
$myData->setAxisUnit(0,"");

$myPicture = new pImage(700,230,$myData);
$Settings = array("R"=>255, "G"=>255, "B"=>255, "Dash"=>1, "DashR"=>275, "DashG"=>275, "DashB"=>275);
$myPicture->drawFilledRectangle(0,0,300,230,$Settings);

$Settings = array("StartR"=>255, "StartG"=>255, "StartB"=>255, "EndR"=>196, "EndG"=>196, "EndB"=>196, "Alpha"=>50);
$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL,$Settings);

$myPicture->drawRectangle(0,0, (699 - 1) ,229,array("R"=>0,"G"=>0,"B"=>0));

$myPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>50,"G"=>50,"B"=>50,"Alpha"=>20));

$myPicture->setFontProperties(array("FontName"=>PCHART_PATH."fonts/Forgotte.ttf","FontSize"=>14));
$TextSettings = array("Align"=>TEXT_ALIGN_MIDDLEMIDDLE
, "R"=>0, "G"=>0, "B"=>0);
$myPicture->drawText(350,25,get_string('overall', 'block_elbp'),$TextSettings);

$myPicture->setShadow(FALSE);
$myPicture->setGraphArea(50,50,675,190);
$myPicture->setFontProperties(array("R"=>0,"G"=>0,"B"=>0,"FontName"=>PCHART_PATH."fonts/verdana.ttf","FontSize"=>8));

$AxisBoundaries = array( 0=>array("Min"=>0, "Max"=>100) );

$Settings = array("Pos"=>SCALE_POS_LEFTRIGHT
, "Mode"=>SCALE_MODE_MANUAL
, "ManualScale"=>$AxisBoundaries
, "LabelingMethod"=>LABELING_ALL
, "GridR"=>255, "GridG"=>255, "GridB"=>255, "GridAlpha"=>50, "TickR"=>0, "TickG"=>0, "TickB"=>0, "TickAlpha"=>50, "LabelRotation"=>0, "CycleBackground"=>1, "DrawXLines"=>1, "DrawSubTicks"=>1, "SubTickR"=>255, "SubTickG"=>0, "SubTickB"=>0, "SubTickAlpha"=>50, "DrawYLines"=>ALL);
$myPicture->drawScale($Settings);

$myPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>50,"G"=>50,"B"=>50,"Alpha"=>10));

$Config = array("DisplayValues"=>1, "AroundZero"=>1);
$myPicture->drawBarChart($Config);

$Config = array("FontR"=>0, "FontG"=>0, "FontB"=>0, "FontName"=>PCHART_PATH."fonts/verdana.ttf", "FontSize"=>6, "Margin"=>6, "Alpha"=>30, "BoxSize"=>5, "Style"=>LEGEND_NOBORDER
, "Mode"=>LEGEND_HORIZONTAL
);

$__default = 560;
$extras = count($__types) - 2;
if ($extras > 0){
    $minus = 60 * $extras;
    $__default -= $minus;
}

$myPicture->drawLegend($__default,16,$Config);

$myPicture->stroke();