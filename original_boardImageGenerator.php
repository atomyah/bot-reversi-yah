<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';
// 合成のベースとなるサイズを定義
define('GD_BASE_SIZE', 700);

// 合成のベースになる画像を生成
$destinationImage = imagecreatefrompng('imgs/reversi_board.png');

// パラメータから現在の石の配置を取得
$stones = json_decode(explode('|', $_REQUEST['stones'])[0]);
// パラメータから前ターンの石の配置を取得
$lastStones = json_decode(explode('|', $_REQUEST['stones'])[1]);

// 現在置かれている石の総数を取得
$stoneCount = 0;
foreach($stones as $array) {
  foreach($array as $stone) {
    if($stone > 0) {
      $stoneCount++;
    }
  }
}

// 前のターンに置かれていた石の総数を取得
$lastStoneCount = 0;
foreach($lastStones as $array) {
  foreach($array as $stone) {
    if($stone > 0) {
      $lastStoneCount++;
    }
  }
}

// 前ターンの合成済み画像が保存されていれば
if(file_exists('./tmp/' . $lastStoneCount . '/' . json_encode($lastStones) . '.png')) {
  // 保存された画像を合成のベースに変更
  $destinationImage = imagecreatefrompng('./tmp/' . $lastStoneCount . '/' . json_encode($lastStones) . '.png');

  // 各列をループ
  for($i = 0; $i < count($stones); $i++) {
    $row = $stones[$i];
    // 各要素をループ
    for($j = 0; $j < count($row); $j++) {
      // 前ターンと置かれている石が異なる時のみ現在の石を生成
      if($stones[$i][$j] != $lastStones[$i][$j]) {
        if($row[$j] == 1) {
          $stoneImage = imagecreatefrompng('imgs/reversi_stone_white.png');
        } elseif($row[$j] == 2) {
          $stoneImage = imagecreatefrompng('imgs/reversi_stone_black.png');
        }
        // 合成
        if($row[$j] > 0) {
          imagecopy($destinationImage, $stoneImage, 9 + (int)($j * 87.5), 9 + (int)($i * 87.5), 0, 0, 70, 70);
          // 破棄
          imagedestroy($stoneImage);
        }
      }
    }
  }
}
// 前ターンの画像が存在しない時
else {
  for($i = 0; $i < count($stones); $i++) {
    $row = $stones[$i];
    for($j = 0; $j < count($row); $j++) {
      // 石が置かれている場合は全て生成し合成
      if($row[$j] == 1) {
        $stoneImage = imagecreatefrompng('imgs/reversi_stone_white.png');
      } elseif($row[$j] == 2) {
        $stoneImage = imagecreatefrompng('imgs/reversi_stone_black.png');
      }
      if($row[$j] > 0) {
         imagecopy($destinationImage, $stoneImage, 9 + (int)($j * 87.5), 9 + (int)($i * 87.5), 0, 0, 70, 70);
         imagedestroy($stoneImage);
      }
    }
  }
}

// 前ターンの石の配置が渡されている場合
if($lastStones != null) {
  // 画像の保存先フォルダを定義。レスポンス改良の為フォルダに分配
  $directory_path = './tmp/' . $stoneCount;
  // フォルダが存在しない時
  if(!file_exists($directory_path)) {
    // フォルダを作成
    if(mkdir($directory_path, 0777, true)) {
      // 権限を変更
      chmod($directory_path, 0777);
    }
  }
  // 現在の画像をフォルダに保存
  imagepng($destinationImage, $directory_path. '/' . json_encode($stones) . '.png', 9);
}

// リクエストされているサイズを取得
$size = $_REQUEST['size'];
// ベースサイズと同じなら何もしない
if($size == GD_BASE_SIZE) {
  $out = $destinationImage;
}
// 違うサイズの場合
else {
  // リクエストされたサイズの空の画像を生成
  $out = imagecreatetruecolor($size ,$size);
  // リサイズしながら合成
  imagecopyresampled($out, $destinationImage, 0, 0, 0, 0, $size, $size, GD_BASE_SIZE, GD_BASE_SIZE);
}

// 出力のバッファリングを有効に
ob_start();
// バッファに出力
imagepng($out, null, 9);
// バッファから画像を取得
$content = ob_get_contents();
// バッファを消去し出力のバッファリングをオフ
ob_end_clean();

// 出力のタイプを指定
header('Content-type: image/png');
// 画像を出力
echo $content;

?>
