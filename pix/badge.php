<?php
$val = (isset($_GET['v'])) ? $_GET['v'] : '';
$banner = (isset($_GET['b'])) ? $_GET['b'] : '';

$img = imagecreatefrompng('eLbadge.png');

// Number
$x = 150;
$len = strlen($val);
$x -= (35 * $len) - 35;
$f = 90;
imagettftext($img, $f, 0, $x, 175, imagecolorallocate($img, 0, 0, 0), '../fonts/OpenSans.ttf', $val);

// Banner
$len = strlen($banner);
$w = 10 * $len;
$x = (360 - $w) / 2;
imagettftext($img, 15, 0, $x, 245, imagecolorallocate($img, 0, 0, 0), '../fonts/OpenSans.ttf', $banner);



header('Content-type: image/png');
imagepng($img);
imagedestroy($img);
exit;