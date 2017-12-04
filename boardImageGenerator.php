<?php

require_once __DIR__ . '/vendor/autoload.php';

define('GD_BASE_SIZE', 700);

$destinationImage = imagecreatefrompng('imgs/reversi_board.png');

$stones = json_decode($_REQUEST['stones']);

for($i=0; $i<count($stones); $i++) {
  $row = $stones[$i];
  
  for($j=0; $j<count($row); $j++) {
    if($row[$j] == 1) {
      $stoneImage = imagecreatefrompng('imgs/reversi_stone_white.png');
    } elseif ($row[$j] == 2) {
      $stoneImage = imagecreatefrompng('imgs/reversi_stone_black.png');
    }
    
    if($row[$j] > 0) {
      imagecopy($destinationImage, $stoneImage, 9 + (int)($j * 87.5), 9 + (int)($i * 87.5), 0, 0, 70, 70);
      
      imagedestroy($stoneImage);
    }
  }
}

$size = $_REQUEST['size'];
if ($size = GD_BASE_SIZE) {
  $out = $destinationImage;
} else {
  $out = imagecreatetruecolor($size, $size);
  imagecopyresampled($out, $destinationImage, 0,0,0,0, $size, $size, GD_BASE_SIZE, GD_BASE_SIZE);
}

ob_start();

imagepng($out, null, 9);

$content = ob_get_contents();

ob_end_clean();

header('Content-type: image/png');
echo $content;

?>
