<?php
require_once __DIR__ .'/vendor/autoload.php';
require __DIR__ . '/functions.php';

define('TABLE_NAME_STONES', 'stones');



$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));

$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

try {
  $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch (\LINE\LINEBot\Exception\InvalidSignatureException $e) {
  error_log('ParseEventRequest failed. InvalidSignatureException => '. var_export($e, TRUE));
} catch (\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
  error_log('ParseEventRequest failed. UnknownEventTypeException => '. var_export($e, TRUE));
} catch (\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
  error_log('ParseEventRequest failed. UnknownMessageTypeException => '. var_export($e, TRUE));
} catch (\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
  error_log('ParseEventRequest failed. InvalidEventRequestException => '. var_export($e, TRUE));
}


foreach ($events as $event) {
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
    error_log('not message event has come');
    continue;
  }
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    error_log('not text message has come');
    continue;
  }

  // ユーザーの情報がデータベースに存在しない時
  if(getStonesByUserId($event->getUserId()) === PDO::PARAM_NULL) { // もし初めてのユーザならば
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
    replyImagemap($bot, $event->getReplyToken(), '盤面', $stones);
    // 以降の処理をスキップ
    continue;
  // 存在する時
  } else {
    // データベースから現在の石の配置を取得
    $stones = getStonesByUserID($event->getUserId());
  }
  
  // 入力されたテキストを[行,列]の配列に変換
  $tappedArea = json_decode($event->getText());
  // ユーザーの石を置く
  //placeStone($stones, $tappedArea[0] - 1, $tappedArea[1] - 1, true);
  $row = $tappedArea[0];
  $col = $tappedArea[1];
  $stones[$row][$col] = 1;
  
  //replyTextMessage($bot, $event->getReplyToken(), json_encode($stones));
  
  // ユーザーの情報を更新
  updateUser($event->getUserId(), json_encode($stones));

  replyImagemap($bot, $event->getReplyToken(), '盤面', $stones);

}  


//ユーザをDBにインサートするファンクション
function registerUser($userId, $stones) {
  $dbh = dbConnection::getConnection();
  $sql = 'INSERT INTO ' . TABLE_NAME_STONES .' (userid, stone) values (pgp_sym_encrypt(?, \'' . getenv('DB_ENCRYPT_PASS') . '\'), ?) ';
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


//ユーザーIDから、DBよりデータフェッチ
function getStonesByUserId($userId) {
  $dbh = dbConnection::getConnection();
  $sql = 'SELECT stone from ' . TABLE_NAME_STONES . ' where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') .'\')';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return json_decode($row['stone']);
  }
}



//そこに置くと相手の石がひっくり返るかを返す。
//引数は、配置・行・列・石の色
function getFlipCountByPosAndColor($stones, $row, $col, $isWhite) {
  $total = 0;
  
  // 石から見た各方向への行、列の数の差
  $directions = [[-1, 0],[-1, 1],[0, 1],[1, 0],[1, 1],[1, -1],[0, -1],[-1, -1]];
  
  //すべての方向をチェック
  for ($i = 0; $i < count($directions); $i++) {
    //置く場所からの距離。１つづつ進めながらチェックしていく。
    $cnt = 1;
    //行の距離
    $rowDiff = $directions[$i][0];
    //列の距離
    $colDiff = $directions[$i][1];
    //裏返せる石の数
    $flipCount = 0;
    
    while (true) {
      //盤面の外に出たらループを抜ける
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
      // どちらの石も置かれてなければループを抜ける
      }
      elseif ($stones[$row + $rowDiff * $cnt][$col + $colDiff * $cnt] == 0) {
        $flipCount = 0;
        break;
      }
      //一個進める
      $cnt++;
    }
    //加算
    $total += $flipCount;          
  }
  //ひっくり返る総数を返す
  return $total;
}
  

// 石を置く。石の配置は参照渡し
function placeStone($stones, $row, $col, $isWhite) {
  // ひっくり返す。処理の流れは
  // getFlipCountByPosAndColorとほぼ同じ
  $directions = [[-1, 0],[-1, 1],[0, 1],[1, 0],[1, 1],[1, -1],[0, -1],[-1, -1]];

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



//イメージマップ作成ファンクション
  function replyImagemap($bot, $replyToken, $alternativeText, $stones) {
   // アクションの配列
    $actionArray = array();
    
   // 1つ以上のエリアが必要なためダミーのタップ可能エリアを追加
    array_push($actionArray, new \LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder('-', 
            new \LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(0, 0, 1, 1)));
    
    
       
   // 全てのマスに対して   
  for($i = 0; $i < 8; $i++) {
    for($j = 0; $j < 8; $j++) {
      // 石が置かれていない、かつ
      // そこに置くと相手の石が1つでもひっくり返る場合
      if($stones[$i][$j] == 0 && getFlipCountByPosAndColor($stones, $i, $j, true) > 0) {
        // タップ可能エリアとアクションを作成し配列に追加
        array_push($actionArray, new LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder(
            '[' . ($i + 1) . ',' . ($j + 1) . '] ',
            new LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(130 * $j, 130 * $i, 130, 130)));
      }
    }
  }
    
    //imagemapMessageBuilder、つまりベースの画像を作る
    $imagemapMessageBuilder = new \LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder (
            'https://' . $_SERVER['HTTP_HOST'] . '/images/' . urlencode(json_encode($stones)) . '/' .uniqid(),
             $alternativeText,
             new \LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder(1040, 1040),
             $actionArray //エリアとアクションの配列
    );
    
    $response = $bot->replyMessage($replyToken, $imagemapMessageBuilder);
    if(!$response->isSucceeded()) {
      error_log('Failed! Ahan'. $response->getHTTPStatus . ' ' . $response->getRawBody());
    }
    
  }


  //DB接続用クラス
  class dbConnection {
    protected static $db;
    
    private function __construct() {
      try {
          $url = parse_url(getenv('DATABASE_URL'));
          $dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
          self::$db = new PDO($dsn, $url['user'], $url['pass']);
          self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $e) {
          echo 'Connection Error: ' . $e->getMessage();
      }
    }
    
    public static function getConnection() {
      if (!self::$db) {
        new dbConnection();
      }
      return self::$db;
    }
  }
?>
