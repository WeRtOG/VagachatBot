<?php

namespace WeRtOG\VagachatBot;

use WeRtOG\BottoGram\Telegram\Model\ChatMember;
use WeRtOG\BottoGram\Telegram\Model\Message;
use WeRtOG\BottoGram\Telegram\Model\MessageEntities;
use WeRtOG\BottoGram\Telegram\Model\MessageEntity;
use WeRtOG\BottoGram\Telegram\Model\User;
use WeRtOG\BottoGram\Telegram\Telegram;

class ChatModerator
{
    public array $WhitelistedDomains = [];

    public function __construct(array $WhitelistedDomains)
    {
        $this->WhitelistedDomains = $WhitelistedDomains;
    }

    public static function WhitelistedDomainsFromJSONFile(string $Filename): array
    {
        $Result = [];
        $FileTextData = @file_get_contents($Filename);

        if(!empty($FileTextData))
        {
            $JSON = @json_decode($FileTextData, true);
            if(is_array($JSON))
            {
                $Result = $JSON['allowed'];
            }
        }
        
        return $Result;
    }

    public function IsURLAllowed(string $URL): bool
    {
        $WhitelistedDomainsString = implode('|', $this->WhitelistedDomains);
        
        return preg_match("/^(?i)((http:\/\/)|(https:\/\/)|)(((.*)(\.))|)($WhitelistedDomainsString)((\/)|$)/", $URL);
    }

    public function GetLinksFromMessage(Message $Message): array
    {
        $Links = [];
        $Entities = $Message->Entities ?? $Message->CaptionEntities;

        if($Entities != null && $Entities instanceof MessageEntities)
        {
            $Text = !empty($Message->Text) ? $Message->Text : ($Message->Caption ?? '');

            foreach($Entities as $Entity)
            {
                if($Entity instanceof MessageEntity)
                {
                    switch($Entity->Type)
                    {
                        case 'text_link':
                            $Links[] = $Entity->Url;
                            break;
                        
                        case 'url':
                            $Links[] = mb_substr($Text, $Entity->Offset, $Entity->Length, 'utf-8');
                            break;
                    }
                }
            }
        }

        return $Links;
    }

    public function IsMessageNotSafe(Message $Message, array $BanFreeChannels): bool
    {
        $IsFromBanFreeChannels = in_array((string)$Message->SenderChat?->ID, $BanFreeChannels) || $Message->Chat->ID == $Message->SenderChat?->ID;
        
        $MessageLinks = $this->GetLinksFromMessage($Message);
        $HasNotSafeLinks = false; 

        foreach($MessageLinks as $MessageLink)
        {
            if(!$this->IsURLAllowed($MessageLink))
                $HasNotSafeLinks = true;
        }

        return $HasNotSafeLinks && !$IsFromBanFreeChannels;
    }
    
    public static function IsUserAdmin(int $ChatID, User $User, Telegram $Telegram): bool
    {
        $ChatAdministrators = $Telegram->GetChatAdministrators($ChatID) ?? [];

        foreach($ChatAdministrators as $ChatAdministrator)
        {
            if($ChatAdministrator instanceof ChatMember && $ChatAdministrator->User->ID == $User->ID)
            {
                return true;
            }
        }

        return false;
    }

    public static function MessageFromChannel(Message $Message): bool
    {
        return $Message->SenderChat != null;
    }

    public static function DeleteMessagesFromChannel(string $ChatID, string $ChannelID, ChatChannelsManager $ChatChannelsManager, Telegram $Telegram)
    {
        $Messages = $ChatChannelsManager->GetChannelMessages($ChatID, $ChannelID);

        foreach($Messages as $MessageID)
        {
            $Telegram->DeleteMessage($ChatID, $MessageID);
        }

        $ChatChannelsManager->DeleteChannelMessages($ChatID, $ChannelID);
    }
}