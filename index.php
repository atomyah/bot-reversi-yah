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

  
  if(getStonesByUserId($event->getUserId()) === PDO::PARAM_NULL) { // もし初めてのユーザならば
  // ゲーム開始時の石の配置
    $stones =
          [
              [0,0,0,0,0,0,0,0],
              [0,0,0,0,0,0,0,0],
              [0,0,0,0,0,0,0,0],
              [0,0,0,1,2,0,0,0],
              [0,0,0,2,1,0,0,0],
              [0,0,0,0,0,0,0,0],
              [0,0,0,0,0,0,0,0],
              [0,0,0,0,0,0,0,0]             
          ];
  
    registerUser($event->getUserId(), json_encode($stones));
  
    replyImagemap($bot, $event->getReplyToken(), '盤面', $stones);
  
    continue;
  
  } else {
    $stones = getStonesByUserID($event->getUserId());
  }
  
  replyImagemap($bot, $event->getReplyToken(), '盤面', $stones);

}  


//ユーザをDBにインサートするファンクション
function registerUser($userId, $stones) {
  $dbh = dbConnection::getConnection();
  $sql = 'INSERT INTO ' . TABLE_NAME_STONES .' (userid, stone) values (pgp_sym_encrypt(?, \'' . getenv('DB_ENCRYPT_PASS') . '\'), ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId, $stones));
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



//引数は、配置・行・列・石の色
function getFlipCountByPosAndColor($stones, $row, $col, $isWhite) { // $row, $colは盤上のマス位置. どちらも0~7
  $total = 0;
  
  $directions = [[-1, 0],[-1, 1],[0, 1],[1, 0],[1, 1],[1, -1],[0, -1],[-1, -1]]; // １マスの四方は８つ。将棋の金みたく。
  
  for ($i=0; $i<count($directions);$i++) { //$iは7回まわす
    $cnt = 1;
    $rowDiff = $directions[$i][0]; //$rowDiffは$directions配列の[x,y]のxを$i分代入
    $colDiff = $directions[$i][1]; //$colDiffは$directions配列の[x,y]のyを$i分代入。つまり$directions配列の８通りすべてを試す。
    $flipCount = 0;
  
  
      while (true) {
        //盤面の外に出たら抜ける。エラーはUndefined offset: -1。$stones[$row+$rowDiff*$cnt][$col+$colDiff*$cnt]はstones配列の値（0か1か2)を返す。
        if(isset($stones[$row+$rowDiff*$cnt]) || !isset($stones[$row+$rowDiff*$cnt][$col+$colDiff*$cnt])) {
          $flipCount = 0;
          break;
        }
        
        //相手の石(2)なら$flipCountを加算。
        if($stones[$row+$rowDiff*$cnt][$col+$colDiff*$cnt] == ($isWhite ? 2 :1)) {
          $flipCount++;
        } elseif ($stones[$row+$rowDiff*$cnt][$col+$colDiff*$cnt] == ($isWhite ? 1:2)) {
          break;  // 自分の石ならループを抜ける
        } elseif ($stones[$row+$rowDiff*$cnt][$col+$colDiff*$cnt] == 0) {
          $flipCount = 0;
          break;  // 石がなければループを抜ける
        }
      // すべての$directionsが盤外にでるまで$cntを増やしていく。でも結局for文で7回回すのだけど。
      $cnt++;
    }
   $total += $flipCount;
   
  }
  return $total; 
}



//イメージマップ作成ファンクション
  function replyImagemap($bot, $replyToken, $alternativeText, $stones) {
   // アクションの配列
    $actionArray = array();
    
   // 1つ以上のエリアが必要なためダミーのタップ可能エリアを追加
    array_push($actionArray, new \LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder('-', 
            new \LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(0, 0, 100, 100)));
    
    
        array_push($actionArray, new \LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder('-', 
            new \LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(200, 200, 100, 100)));
        
/*        
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
  */ 
    
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
