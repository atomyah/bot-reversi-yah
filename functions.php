<?php

function replyTextMessage($bot, $replyToken, $text) {
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($text));

  if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
  
}

function replyImageMessage($bot, $replyToken, $originalImageUrl, $previewImageUrl) {
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\ImageMessageBuilder($originalImageUrl, $previewImageUrl));
  
  if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}
  
  function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {
    $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\LocationMessageBuilder($title, $address, $lat, $lon));
  
  if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }  
}

  function replyStickerMessage($bot, $replyToken, $packageId, $stickerId) {
    $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId));

  if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }      
}

  function replyVideoMessage($bot, $replyToken, $originalContentUrl, $previewImageUrl) {
    $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\VideoMessageBuilder($originalContentUrl, $previewImageUrl));
  
  if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }   
}

  function replyAudioMessage($bot, $replyToken, $originalContentUrl, $audioLength) {
    $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\AudioMessageBuilder($originalContentUrl, $audioLength));
  
  if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
  }  
}

  
  function replyMultiMessage($bot, $replyToken, ...$msgs) { ///...$msgsは可変長引数
    $builder = new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder();
    
    foreach($msgs as $value) {
      $builder->add($value);
    }
    $response = $bot->replyMessage($replyToken, $builder);
    
    if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
    }    
}


  function replyButtonTemplate($bot, $replyToken, $alternativeText, $imageUrl, $title, $text, ...$actions) {
    
    $actionArray = array();
    
    foreach ($actions as $value) {
      array_push($actionArray, $value);
    }
    
    $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($alternativeText, 
            new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder($title, $text, $imageUrl, $actionArray)
    );
    
    $response = $bot->replyMessage($replyToken, $builder);

    if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
    }    
    
  }

  function replyConfirmTemplate($bot, $replyToken, $alternativeText, $text, ...$actions) {
    
    $actionArray = array();
    foreach($actions as $value) {
      array_push($actionArray, $value);
    }
    
    $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($alternativeText,
            new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder($text, $actionArray)
          );
    
    $response = $bot->replyMessage($replyToken, $builder);
    
    if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
    }
    
  }
  
  function replyCarouselTemplate($bot, $replyToken, $alternativeText, $columnArray) {
    $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($alternativeText,
            new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder($columnArray)
    );
    $response = $bot->replyMessage($replyToken, $builder);

    if (!$response->isSucceeded()) {
    error_log('Failed!' . $response->getHTTPStatus . ' ' . $response->getRawBody());
    }      
    
  }
  
  
  function turnStone($stones, $row, $col, $isWhite) {
  // ひっくり返す。処理の流れは
  // getFlipCountByPosAndColorとほぼ同じ
  $directions = [[-1, 0],[-1, 1],[0, 1],[1, 0],[1, 1],[1, -1],[0, -1],[-1, -1]];

  for ($i = 0; $i < count($directions); $i++) {
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
          for ($i = 0; $i < $flipCount; $i++) {
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
  //$stones[$row][$col] = ($isWhite ? 1 : 2);
}


?>