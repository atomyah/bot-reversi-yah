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
              [0,0,0,0,0,0,0,0,],
              [0,0,0,0,0,0,0,0,],
              [0,0,0,0,0,0,0,0,],
              [0,0,0,1,2,0,0,0,],
              [0,0,0,2,1,0,0,0,],
              [0,0,0,0,0,0,0,0,],
              [0,0,0,0,0,0,0,0,],
              [0,0,0,0,0,0,0,0,]             
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
  $sql = 'SELECT stone from ' . TABLE_NAME_STONES . ' where ? = pgp_sys_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') .'\')';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return json_decode($row['stone']);
  }
}



//イメージマップ作成ファンクション
  function replyImagemap($bot, $replyToken, $alternativeText, $stones) {
    $actionArray = array();
    
    //アクションの設定
    array_push($actionArray, new \LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder('-', 
            new \LINE\LINEBot\ImagemapActionBuilder\AreaBuilder(0, 0, 1, 1)));
    
    
    //imagemapMessageBuilder、つまりベースの画像を作る
    $imagemapMessageBuilder = new \LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder (
            'https://' . $_SERVER['HTTP_HOST'] . '/images/' . urlencode(json_encode($stones)) . '/' .uniqid(),
             $alternativeText,
             new \LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder(1040, 1040),
             $actionArray
    );
    
    $response = $bot->replyMessage($replyToken, $imagemapMessageBuilder);
    if(!$response->isSuceeded()) {
      error_log('Failed'. $response->getHTTPStatus . ' ' . $response->getRawBody());
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
