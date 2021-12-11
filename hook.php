<?php

/////////////////////////////////////////////////////////////////////////////
//             Этот файл принимает на себя вебхуки от Telegram             //
/////////////////////////////////////////////////////////////////////////////

namespace WeRtOG\VagachatBot;

// Используем нужные зависимости

use WeRtOG\BottoGram\BottoConfig;
use WeRtOG\BottoGram\DatabaseManager\DatabaseManager;
use WeRtOG\BottoGram\Telegram\Model\InlineKeyboardButton;
use WeRtOG\BottoGram\Telegram\Model\InlineKeyboardMarkup;
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

// Подключаем модели
foreach (glob(__DIR__ . "/models/*.php") as $Filename) require_once $Filename;

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

// Подключаем БД
$Database = DatabaseManager::Connect($Config->DatabaseConnection);

// Подключаем менеджер контроля пользователей-каналов
$ChatChannelsManager = new ChatChannelsManager($Database);

/////////////////////////////////////////////////////////////////////////////

$BanFreeChannels = [
    '-1001215456161', // синусоида енота
    '-1001640189359' // синусоида аксолотля (для dev версии бота)
];

/////////////////////////////////////////////////////////////////////////////

$Update = $Telegram->GetUpdateFromInput();
$Message = $Update->Message ?? $Update->EditedMessage ?? null;
$InlineQuery = $Update->InlineQuery;
$CallbackQuery = $Update->CallbackQuery;

// Обрабатываем обычные сообщения
if($Message != null)
{
    // Если сообщение из группового чата
    if($Message->IsFromGroup)
    {

        // Чекаем сообщение на ссылки и если чё-то есть и ведёт Ne2Da - удаляем к чёрту
        $WhitelistedDomains = ChatModerator::WhitelistedDomainsFromJSONFile(__DIR__ . '/domains.json');
        $Moderator = new ChatModerator($WhitelistedDomains);

        if($Moderator->IsMessageNotSafe($Message, $BanFreeChannels))
        {
            $Telegram->DeleteMessage($Message->Chat->ID, $Message->MessageID);
        }

        
        // Если сообщение от канала
        if($Moderator->MessageFromChannel($Message))
        {
            // Чекаем на случай, если это важный канал / чат из которого низзя вапщето удалять
            $IsFromBanFreeChannels = in_array((string)$Message->SenderChat->ID, $BanFreeChannels) || $Message->Chat->ID == $Message->SenderChat->ID;

            // Если же нет
            if(!$IsFromBanFreeChannels)
            {
                // Регистрируем канал, если не зарегистрирован
                $ChatChannelsManager->RegisterChannelIfNotRegistered($Message->Chat->ID, $Message->SenderChat->ID, $Message->SenderChat->Title);

                // Если канал из бан листа, то удаляем
                if($ChatChannelsManager->IsChannelInBanList($Message->Chat->ID, $Message->SenderChat->ID))
                {
                    $Telegram->DeleteMessage($Message->Chat->ID, $Message->MessageID);
                }
                // Если нет, то запоминаем (а то мало-ли хитрые какие додики есть)
                else
                {
                    $ChatChannelsManager->RememberMessage($Message->Chat->ID, $Message->SenderChat->ID, $Message->MessageID);
                }
            }
        }

        // Получаем команду из сообщения (если есть)
        $Command = $Message->Command != null ? explode('@', $Message->Command)[0] ?? $Message->Command : null;

        // Если получена команда бана (охх дальше и кода. пипееец. уж простите, кто читает)
        if($Command == '/ban')
        {
            // Если юзер админ или чат
            if(ChatModerator::IsUserAdmin($Message->Chat->ID, $Message->From, $Telegram) || $Message->Chat->ID == $Message->SenderChat?->ID)
            {
                // Если это ответ на другое сообщение (подозрительное)
                if($Message->ReplyToMessage != null)
                {
                    // Если то сообщение, на которое ответ из канала
                    if($Moderator->MessageFromChannel($Message->ReplyToMessage))
                    {
                        // Если это не чат
                        if($Message->Chat != $Message->ReplyToMessage->SenderChat)
                        {
                            // Если не в BanFree списке
                            if(!in_array((string)$Message->ReplyToMessage->SenderChat->ID, $BanFreeChannels))
                            {
                                // Баним
                                $ChatChannelsManager->SetChannelStatus($Message->Chat->ID, $Message->ReplyToMessage->SenderChat->ID, 1);
                            
                                // Удаляем сообщения
                                ChatModerator::DeleteMessagesFromChannel($Message->Chat->ID, $Message->ReplyToMessage->SenderChat->ID, $ChatChannelsManager, $Telegram);
    
                                // Отправляем сообщение о том, что такого-то додика забанили
                                $Telegram->SendMessage($Message->Chat->ID, '⛔️ Пользователь-канал <b>"' . strip_tags($Message->ReplyToMessage->SenderChat->Title) . '"</b> успешно забанен! Теперь его последующие сообщения будут удаляться.', ParseMode: ParseMode::HTML, ReplyToMessageID: $Message->MessageID, ReplyMarkup: new InlineKeyboardMarkup([
                                    [
                                        new InlineKeyboardButton(
                                            Text: '✝️ Разбанить',
                                            CallbackData: '/unban ' . $Message->ReplyToMessage->SenderChat->ID
                                        )
                                    ]
                                ]));
                            }
                            // Если канал всё же в BanFree списке
                            else
                            {
                                $Telegram->SendMessage($Message->Chat->ID, '🤷‍♂️ Сообщения с этого канала защищены от бана' , ReplyToMessageID: $Message->MessageID);
                            }
                        } 
                        // Если то сообщение от чата
                        else
                        {
                            // Если Ваганыч пытается забанить сам себа (зачем??)
                            if($Message->Chat->ID == $Message->SenderChat?->ID)
                            {
                                $Telegram->SendMessage($Message->Chat->ID, 'Зачем же банить самого себя? 😳' , ReplyToMessageID: $Message->MessageID);
                            }
                            // Если же какой-то смертный (но админ)
                            else
                            {
                                $Telegram->SendMessage($Message->Chat->ID, '😠😠😠 Как смеешь ты, смертный, банить создателя?' , ReplyToMessageID: $Message->MessageID);
                            }
                        }
                    }
                    // Если то сообщение, на которое ответ не из канала
                    else
                    {
                        $Telegram->SendMessage($Message->Chat->ID, '🤷‍♂️ Я пока-что умею банить сообщения только от каналов' , ReplyToMessageID: $Message->MessageID); 
                    }
                }
                // Если это вообще был не ответ
                else
                {
                    $Telegram->SendMessage($Message->Chat->ID, 'ℹ️ Чтобы забанить канал необходимо ответить на его сообщение командой /ban' , ReplyToMessageID: $Message->MessageID); 
                }
            }
            // Если сообщение от недостойного
            else
            {
                $Telegram->SendMessage($Message->Chat->ID, '🙅‍♂️ Доступ к этой команде доступен только у администраторов чата' , ReplyToMessageID: $Message->MessageID); 
            }
        }
    }
    // Если же сообщение не из группового чата
    else
    {
        if($Message->Command == '/start')
            $Telegram->SendMessage($Message->Chat->ID, '👋 _привчёдел_');

        $Telegram->SendSticker($Message->Chat->ID, 'CAACAgIAAxkBAAMiYUfRtM4tpYmmGCtg6H6ztq3NCYYAAjoAA_K1bSjLDmA18sCjPCAE');
        $Telegram->SendMessage($Message->Chat->ID, '*хранитель спит*', ParseMode: ParseMode::HTML);
    }
}

// Обрабатываем Callback запросы
if($CallbackQuery != null)
{
    // Получаем чат
    $ChatID = $CallbackQuery->Message->Chat->ID ?? $CallbackQuery->ChatInstance;

    // Если в колбеке была скрыта команда разбана
    if($CallbackQuery->DataCommand == '/unban')
    {
        // Чекаем с начала является ли чел, что кликнул админом
        if(ChatModerator::IsUserAdmin($ChatID, $CallbackQuery->From, $Telegram))
        {
            // Получаем ID канала из команды
            $ChannelID = $CallbackQuery->DataArguments[0] ?? null;

            // Если всё оки-с
            if($ChannelID != null)
            {
                // Разбаниваем
                $ChatChannelsManager->SetChannelStatus($ChatID, $ChannelID, 0);

                // Оповещаем о разбане в виде уведомляшки
                $Telegram->AnswerCallbackQuery($CallbackQuery->ID, 'ℹ️ Пользователь-канал разбанен');
                
                // Если есть исходное сообщение откуда келбек - удаляем его
                if($CallbackQuery->Message != null)
                {
                    $Telegram->DeleteMessage($CallbackQuery->Message->Chat->ID, $CallbackQuery->Message->MessageID);
                }
            }
            // Если же нету ID - сообщаем об этом
            else
            {
                $Telegram->AnswerCallbackQuery($CallbackQuery->ID, '🙅‍♂️ Команда неверна');
            }
        }
        // Если чел, что кликнул - не админ, то сообщаем ему об этом
        else
        {
            $Telegram->AnswerCallbackQuery($CallbackQuery->ID, '🙅‍♂️ Нет доступа');
        }
    }
    // Если же какая-то другая команда - сообщаем, что команда не найдена
    else
    {
        $Telegram->AnswerCallbackQuery($CallbackQuery->ID, '🙅‍♂️ Команда не найдена');
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


