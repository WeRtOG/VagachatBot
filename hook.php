<?php

/////////////////////////////////////////////////////////////////////////////
//             Этот файл принимает на себя вебхуки от Telegram             //
/////////////////////////////////////////////////////////////////////////////

namespace WeRtOG\VagachatBot;

// Используем нужные зависимости

use WeRtOG\BottoGram\BottoConfig;
use WeRtOG\BottoGram\Telegram\Model\InlineQueryResultArray;
use WeRtOG\BottoGram\Telegram\Model\InlineQueryResultArticle;
use WeRtOG\BottoGram\Telegram\Model\InputTextMessageContent;
use WeRtOG\BottoGram\Telegram\Model\ParseMode;
use WeRtOG\BottoGram\Telegram\Model\Response;
use WeRtOG\BottoGram\Telegram\Telegram;

// Подключаем Composer
require_once 'vendor/autoload.php';

// Подключаем классы
require_once 'components/ChatModerator.php';
require_once 'components/BassmenJokes.php';

// Включаем отладку и логи
ini_set("log_errors", 1);
ini_set("error_log", "app-error.log");

// Обработываем ошибки
set_exception_handler(function ($Exception) {
    $ErrorText = '<code>' . $Exception->getMessage() . '</code> in <code>' .  basename($Exception->getFile()) . '</code>, line <code>' . $Exception->getLine() . '</code>' . PHP_EOL;
    error_log($ErrorText);
    echo $ErrorText;
});

// Подключаем конфиг
$Config = BottoConfig::CreateFromJSONFile('config.json');

// Инициализируем клиент Telegram
$Telegram = new Telegram($Config->Token);

// Обрабатываем необработанные ответы от Telegram
$Telegram->OnResponse(function(Response $Response) {
    print_r($Response->GetData());
});


/////////////////////////////////////////////////////////////////////////////

$Update = $Telegram->GetUpdateFromInput();
$Message = $Update->Message ?? $Update->EditedMessage ?? null;
$InlineQuery = $Update->InlineQuery;

// Обрабатываем обычные сообщения
if($Message != null)
{
    if($Message->IsFromGroup)
    {
        $WhitelistedDomains = ChatModerator::WhitelistedDomainsFromJSONFile(__DIR__ . '/domains.json');
        $Moderator = new ChatModerator($WhitelistedDomains);

        if($Moderator->IsMessageNotSafe($Message))
        {
            $Telegram->DeleteMessage($Message->Chat->ID, $Message->MessageID);
        }
    }
    else
    {
        if($Message->Command == '/start')
            $Telegram->SendMessage($Message->Chat->ID, '👋 _привчёдел_');

        $Telegram->SendSticker($Message->Chat->ID, 'CAACAgIAAxkBAAMiYUfRtM4tpYmmGCtg6H6ztq3NCYYAAjoAA_K1bSjLDmA18sCjPCAE');
        $Telegram->SendMessage($Message->Chat->ID, '*хранитель спит*', ParseMode: ParseMode::HTML);
    }
}

// Обрабатываем инлайновые запросы
if($InlineQuery != null)
{
    $RandomBassmenAnekdot = BassmenJokes::GetRandom();
    $Telegram->AnswerInlineQuery($InlineQuery->ID, 
        Results: new InlineQueryResultArray(
            new InlineQueryResultArticle(
                ID: uniqid(), 
                Title: 'Отправить рандомную шутку про басиста',
                Description: 'Без негатива!',
                InputMessageContent: new InputTextMessageContent($RandomBassmenAnekdot . "\n\n😝 _Без негатива!_", ParseMode: ParseMode::Markdown),
                ThumbUrl: 'https://emojipedia-us.s3.dualstack.us-west-1.amazonaws.com/thumbs/160/apple/285/guitar_1f3b8.png'
            )
        ),
        CacheTime: 1
    );
}


