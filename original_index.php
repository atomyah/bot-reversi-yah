<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';
// テーブル名を定義
define('TABLE_NAME_STONES', 'stones');

// アクセストークンを使いCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
// CurlHTTPClientとシークレットを使いLINEBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
// LINE Messaging APIがリクエストに付与した署名を取得
$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

// 署名が正当かチェック。正当であればリクエストをパースし配列へ
// 不正であれば例外の内容を出力
try {
  $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch(\LINE\LINEBot\Exception\InvalidSignatureException $e) {
  error_log('parseEventRequest failed. InvalidSignatureException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
  error_log('parseEventRequest failed. UnknownEventTypeException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
  error_log('parseEventRequest failed. UnknownMessageTypeException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
  error_log('parseEventRequest failed. InvalidEventRequestException => '.var_export($e, true));
}

// 配列に格納された各イベントをループで処理
foreach ($events as $event) {
  // MessageEvent型でなければ処理をスキップ
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
    error_log('Non message event has come');
    continue;
  }
  // TextMessage型でなければ処理をスキップ
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    error_log('Non text message has come');
    continue;
  }

  // リッチコンテンツがタップされた時
  if(substr($event->getText(), 0, 4) == 'cmd_') {
    // 盤面の確認
    if(substr($event->getText(), 4) == 'check_board') {
      if(getStonesByUserId($event->getUserId()) != PDO::PARAM_NULL) {
        $stones = getStonesByUserId($event->getUserId());
        replyImagemap($bot, $event->getReplyToken(), '盤面',  $stones);
      }
    }
    // 情勢の確認
    else if(substr($event->getText(), 4) == 'check_count') {
      if(getStonesByUserId($event->getUserId()) != PDO::PARAM_NULL) {
        $stones = getStonesByUserId($event->getUserId());
        $white = 0;
        $black = 0;
        for($i = 0; $i < count($stones); $i++) {
          for($j = 0; $j < count($stones[$i]); $j++) {
            if($stones[$i][$j] == 1) {
              $white++;
            } else if($stones[$i][$j] == 2) {
              $black++;
            }
          }
        }
        replyTextMessage($bot, $event->getReplyToken(), sprintf('白 : %d、黒 : %d', $white, $black));
      }
    }
    // ゲームを中断し新ゲームを開始
    else if(substr($event->getText(), 4) == 'newgame') {
      deleteUser($event->getUserId());
      $stones =
      [
      [0, 0, 0, 0, 0, 0, 0, 0],
      [0, 0, 0, 0, 0, 0, 0, 0],
      [0, 0, 0, 0, 0, 0, 0, 0],
      [0, 0, 0, 1, 2, 0, 0, 0],
      [0, 0, 0, 2, 1, 0, 0, 0],
      [0, 0, 0, 0, 0, 0, 0, 0],
      [0, 0, 0, 0, 0, 0, 0, 0],
      [0, 0, 0, 0, 0, 0, 0, 0],
      ];
      registerUser($event->getUserId(), json_encode($stones));

      replyImagemap($bot, $event->getReplyToken(), '盤面', $stones, null);
    }
    // 遊び方
    else if(substr($event->getText(), 4) == 'help') {
      replyTextMessage($bot, $event->getReplyToken(), 'あなたは常に白番です。送られた盤面上の置きたい場所をタップしてね！バグった時はオプションの盤面再送から！');
    }
    continue;
  }

  // ユーザーの情報がデータベースに存在しない時
  if(getStonesByUserId($event->getUserId()) === PDO::PARAM_NULL) {
    // ゲーム開始時の石の配置
    $stones =
    [
    [0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 1, 2, 0, 0, 0],
    [0, 0, 0, 2, 1, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0],
    ];
    // ユーザーをデータベースに登録
    registerUser($event->getUserId(), json_encode($stones));
    // Imagemapを返信
    replyImagemap($bot, $event->getReplyToken(), '盤面', $stones, null);
    // 以降の処理をスキップ
    continue;
  // 存在する時
  } else {
    // データベースから現在の石の配置を取得
    $stones = getStonesByUserId($event->getUserId());
    $lastStones = $stones;
  }

  // 入力されたテキストを[行,列]の配列に変換
  $tappedArea = json_decode($event->getText());

  // ユーザーの石を置く
  placeStone($stones, $tappedArea[0] - 1, $tappedArea[1] - 1, true);
  // 相手の石を置く
  placeAIStone($stones);
  // ユーザーの情報を更新
  updateUser($event->getUserId(), json_encode($stones));

  // ユーザーも相手も石を置くことができない時
  if(!getCanPlaceByColor($stones, true) && !getCanPlaceByColor($stones, false)) {
    // ゲームオーバー
    endGame($bot, $event->getReplyToken(), $event->getUserId(), $stones);
    continue;
  // 相手のみが置ける時
  } else if(!getCanPlaceByColor($stones, true) && getCanPlaceByColor($stones, false)) {
    // ユーザーが置けるようになるまで相手が石を置く
    while(!getCanPlaceByColor($stones, true)) {
      placeAIStone();
      updateUser($bot, json_encode($stones));
      // どちらの石も置けなくなったらゲームオーバー
      if(!getCanPlaceByColor($stones, true) && !getCanPlaceByColor($stones, false)) {
        endGame($bot, $event->getReplyToken(), $event->getUserId(), $stones);
        continue 2;
      }
    }
  }

  replyImagemap($bot, $event->getReplyToken(), '盤面', $stones, $lastStones);
}

// ユーザーをデータベースに登録する
function registerUser($userId, $stones) {
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STONES .' (userid, stone) values (pgp_sym_encrypt(?, \'' . getenv('DB_ENCRYPT_PASS') . '\'), ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId, $stones));
}

// ユーザーの情報を更新
function updateUser($userId, $stones) {
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STONES . ' set stone = ? where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($stones, $userId));
}

// ユーザーの情報をデータベースから削除
function deleteUser($userId) {
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STONES . ' where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($userId));
}

// ユーザーIDを元にデータベースから情報を取得
function getStonesByUserId($userId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select stone from ' . TABLE_NAME_STONES . ' where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  // レコードが存在しなければNULL
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    // 石の配置を連想配列に変換し返す
    return json_decode($row['stone']);
  }
}

// ゲームオーバー
function endGame($bot, $replyToken, $userId, $stones) {
  // それぞれの石の数をカウント
  $white = 0;
  $black = 0;
  for($i = 0; $i < count($stones); $i++) {
    for($j = 0; $j < count($stones[$i]); $j++) {
      if($stones[$i][$j] == 1) {
        $white++;
      } else if($stones[$i][$j] == 2) {
        $black++;
      }
    }
  }

  // 送るテキスト
  if($white == $black) {
    $message = '引き分け！' . sprintf('白 : %d、 黒 %d', $white, $black);
  } else {
    $message = ($white > $black ? 'あなた' : 'CPU') . 'の勝ち！' . sprintf('白 : %d、 黒 : %d', $white, $black);
  }

  // 盤面とダミーエリアのみのImagemapを生成
  $actionArray = array();
  array_push($actionArray, new LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder(
    '-',
    new LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(0, 0, 1, 1)));

  $imagemapMessageBuilder = new \LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder (
    'https://' . $_SERVER['HTTP_HOST'] .  '/images/' . urlencode(json_encode($stones) . '/' . uniqid()),
    $message,
    new LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder(1040, 1040),
    $actionArray
  );

  // テキストのメッセージ
  $textMessage = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
  // スタンプのメッセージ
  $stickerMessage = ($white >= $black)
    ? new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(1, 114)
    : new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(1, 111);
  // データベースからユーザーを削除
  deleteUser($userId);
  // Imagemap、テキスト、スタンプを返信
  replyMultiMessage($bot, $replyToken, $imagemapMessageBuilder, $textMessage, $stickerMessage);
}

// 石が置ける場所があるかを調べる
// 引数は現在の石の配置、石の色
function getCanPlaceByColor($stones, $isWhite) {
  for ($i = 0; $i < count($stones); $i++) {
    for ($j = 0; $j < count($stones[$i]); $j++) {
      if ($stones[$i][$j] == 0) {
        // 1つでもひっくり返るなら真
        if (getFlipCountByPosAndColor($stones, $i, $j, $isWhite) > 0) {
          return true;
        }
      }
    }
  }
  return false;
}

// そこに置くと相手の石が何個ひっくり返るかを返す
// 引数は現在の配置、行、列、石の色
function getFlipCountByPosAndColor($stones, $row, $col, $isWhite)
{
  $total = 0;
  // 石から見た各方向への行、列の数の差
  $directions = [[-1, 0],[-1, 1],[0, 1],[1, 0],[1, 1],[1, 0],[1, -1],[0, -1],[-1, -1]];

  // 全ての方向をチェック
  for ($i = 0; $i < count($directions); ++$i) {
    // 置く場所からの距離。1つずつ進めながらチェックしていく
    $cnt = 1;
    // 行の距離
    $rowDiff = $directions[$i][0];
    // 列の距離
    $colDiff = $directions[$i][1];
    // はさめる可能性がある数
    $flipCount = 0;

    while (true) {
      // 盤面の外に出たらループを抜ける
      if (!isset($stones[$row + $rowDiff * $cnt]) || !isset($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt])) {
        $flipCount = 0;
        break;
      }
      // 相手の石なら$flipCountを加算
      if ($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt] == ($isWhite ? 2 : 1)) {
        $flipCount++;
      }
      // 自分の石ならループを抜ける
      elseif ($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt] == ($isWhite ? 1 : 2)) {
        break;
      }
      // どちらの石も置かれてなければループを抜ける
      elseif ($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt] == 0) {
        $flipCount = 0;
        break;
      }
      // 1個進める
      $cnt++;
    }
    // 加算
    $total += $flipCount;
  }
  // ひっくり返る総数を返す
  return $total;
}

// 石を置く。石の配置は参照渡し
function placeStone(&$stones, $row, $col, $isWhite) {
  // ひっくり返す。処理の流れは
  // getFlipCountByPosAndColorとほぼ同じ
  $directions = [[-1, 0],[-1, 1],[0, 1],[1, 0],[1, 1],[1, 0],[1, -1],[0, -1],[-1, -1]];

  for ($i = 0; $i < count($directions); ++$i) {
    $cnt = 1;
    $rowDiff = $directions[$i][0];
    $colDiff = $directions[$i][1];
    $flipCount = 0;

    while (true) {
      if (!isset($stones[$row + $rowDiff * $cnt]) || !isset($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt])) {
        $flipCount = 0;
        break;
      }
      if ($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt] == ($isWhite ? 2 : 1)) {
        $flipCount++;
      } elseif ($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt] == ($isWhite ? 1 : 2)) {
        if ($flipCount > 0) {
          // ひっくり返す
          for ($i = 0; $i < $flipCount; ++$i) {
            $stones[$row + $rowDiff * ($i + 1)][$col + $colDiff * ($i + 1)] = ($isWhite ? 1 : 2);
          }
        }
        break;
      } elseif ($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt] == 0) {
        $flipCount = 0;
        break;
      }
      $cnt++;
    }
  }
  // 新たに石を置く
  $stones[$row][$col] = ($isWhite ? 1 : 2);
}

// 敵の石を置く
function placeAIStone(&$stones) {
  // 強い場所の配列。強い準
  $strongArray = [0, 7, 56, 63, 2, 5, 16, 18, 21, 23, 40, 42, 45, 47, 58, 61];
  // 弱い場所の配列。強い準
  $weakArray = [1, 6, 8, 15, 48, 57, 62, 9, 14, 49, 54];

  // どちらにも属さない場所の配列
  $otherArray = [];
  for ($i = 0; $i < count($stones) * count($stones[0]); $i++) {
      if (!in_array($i, $strongArray) && !in_array($i, $weakArray)) {
          array_push($otherArray, $i);
      }
  }
  // ランダム性を持たせるためシャッフル
  shuffle($otherArray);

  // 全てのマスの強い+普通+弱い順の配列
  $posArray = array_merge($strongArray, $otherArray, $weakArray);

  // 1つずつそこに置けるかをチェックし、
  // 可能なら置いて処理を終える
  for ($i = 0; $i < count($posArray); ++$i) {
    $pos = [$posArray[$i] / 8, $posArray[$i] % 8];
    if ($stones[$pos[0]][$pos[1]] == 0) {
      if (getFlipCountByPosAndColor($stones, $pos[0], $pos[1], false) > 0) {
        placeStone($stones, $pos[0], $pos[1], false);
        break;
      }
    }
  }
}

// テキストを返信。引数はLINEBot、返信先、テキスト
function replyTextMessage($bot, $replyToken, $text) {
  // 返信を行いレスポンスを取得
  // TextMessageBuilderの引数はテキスト
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($text));
  // レスポンスが異常な場合
  if (!$response->isSucceeded()) {
    // エラー内容を出力
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 画像を返信。引数はLINEBot、返信先、画像URL、サムネイルURL
function replyImageMessage($bot, $replyToken, $originalImageUrl, $previewImageUrl) {
  // ImageMessageBuilderの引数は画像URL、サムネイルURL
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\ImageMessageBuilder($originalImageUrl, $previewImageUrl));
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 位置情報を返信。引数はLINEBot、返信先、タイトル、住所、
// 緯度、経度
function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {
  // LocationMessageBuilderの引数はダイアログのタイトル、住所、緯度、経度
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\LocationMessageBuilder($title, $address, $lat, $lon));
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// スタンプを返信。引数はLINEBot、返信先、
// スタンプのパッケージID、スタンプID
function replyStickerMessage($bot, $replyToken, $packageId, $stickerId) {
  // StickerMessageBuilderの引数はスタンプのパッケージID、スタンプID
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId));
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 動画を返信。引数はLINEBot、返信先、動画URL、サムネイルURL
function replyVideoMessage($bot, $replyToken, $originalContentUrl, $previewImageUrl) {
  // VideoMessageBuilderの引数は動画URL、サムネイルURL
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\VideoMessageBuilder($originalContentUrl, $previewImageUrl));
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// オーディオファイルを返信。引数はLINEBot、返信先、
// ファイルのURL、ファイルの再生時間
function replyAudioMessage($bot, $replyToken, $originalContentUrl, $audioLength) {
  // AudioMessageBuilderの引数はファイルのURL、ファイルの再生時間
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\AudioMessageBuilder($originalContentUrl, $audioLength));
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 複数のメッセージをまとめて返信。引数はLINEBot、
// 返信先、メッセージ(可変長引数)
function replyMultiMessage($bot, $replyToken, ...$msgs) {
  // MultiMessageBuilderをインスタンス化
  $builder = new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder();
  // ビルダーにメッセージを全て追加
  foreach($msgs as $value) {
    $builder->add($value);
  }
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// Buttonsテンプレートを返信。引数はLINEBot、返信先、代替テキスト、
// 画像URL、タイトル、本文、アクション(可変長引数)
function replyButtonsTemplate($bot, $replyToken, $alternativeText, $imageUrl, $title, $text, ...$actions) {
  // アクションを格納する配列
  $actionArray = array();
  // アクションを全て追加
  foreach($actions as $value) {
    array_push($actionArray, $value);
  }
  // TemplateMessageBuilderの引数は代替テキスト、ButtonTemplateBuilder
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
    $alternativeText,
    // ButtonTemplateBuilderの引数はタイトル、本文、
    // 画像URL、アクションの配列
    new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder ($title, $text, $imageUrl, $actionArray)
  );
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// Confirmテンプレートを返信。引数はLINEBot、返信先、代替テキスト、
// 本文、アクション(可変長引数)
function replyConfirmTemplate($bot, $replyToken, $alternativeText, $text, ...$actions) {
  $actionArray = array();
  foreach($actions as $value) {
    array_push($actionArray, $value);
  }
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
    $alternativeText,
    // Confirmテンプレートの引数はテキスト、アクションの配列
    new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder ($text, $actionArray)
  );
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// Carouselテンプレートを返信。引数はLINEBot、返信先、代替テキスト、
// ダイアログの配列
function replyCarouselTemplate($bot, $replyToken, $alternativeText, $columnArray) {
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
  $alternativeText,
  // Carouselテンプレートの引数はダイアログの配列
  new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder (
   $columnArray)
  );
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 盤面のImagemapを返信
function replyImagemap($bot, $replyToken, $alternativeText, $stones, $lastStones) {
  // アクションの配列
  $actionArray = array();
  // 1つ以上のエリアが必要なためダミーのタップ可能エリアを追加
  array_push($actionArray, new LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder(
      '-',
      new LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(0, 0, 1, 1)));
  // 全てのマスに対して
  for($i = 0; $i < 8; $i++) {
    for($j = 0; $j < 8; $j++) {
      // 石が置かれていない、かつ
      // そこに置くと相手の石が1つでもひっくり返る場合
      if($stones[$i][$j] == 0 && getFlipCountByPosAndColor($stones, $i, $j, true) > 0) {
        // タップ可能エリアとアクションを作成し配列に追加
        array_push($actionArray, new LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder(
            '[' . ($i + 1) . ',' . ($j + 1) . ']',
            new LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(130 * $j, 130 * $i, 130, 130)));
      }
    }
  }
  // ImagemapMessageBuilderの引数は画像のURL、代替テキスト、
  // 基本比率サイズ(幅は1040固定)、アクションの配列
  $imagemapMessageBuilder = new \LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder (
    'https://' . $_SERVER['HTTP_HOST'] . '/images/' . urlencode(json_encode($stones) . '|' . json_encode($lastStones)) . '/' . uniqid(),
    $alternativeText,
    new LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder(1040, 1040),
    $actionArray
  );

  $response = $bot->replyMessage($replyToken, $imagemapMessageBuilder);
  if(!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// データベースへの接続を管理するクラス
class dbConnection {
  // インスタンス
  protected static $db;
  // コンストラクタ
  private function __construct() {

    try {
      // 環境変数からデータベースへの接続情報を取得し
      $url = parse_url(getenv('DATABASE_URL'));
      // データソース
      $dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
      // 接続を確立
      self::$db = new PDO($dsn, $url['user'], $url['pass']);
      // エラー時例外を投げるように設定
      self::$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }
    catch (PDOException $e) {
      error_log('Connection Error: ' . $e->getMessage());
    }
  }

  // シングルトン。存在しない場合のみインスタンス化
  public static function getConnection() {
    if (!self::$db) {
      new dbConnection();
    }
    return self::$db;
  }
}

?>
