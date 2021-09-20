<?php

namespace WeRtOG\VagachatBot;

use WeRtOG\BottoGram\Telegram\Model\Message;
use WeRtOG\BottoGram\Telegram\Model\MessageEntities;
use WeRtOG\BottoGram\Telegram\Model\MessageEntity;

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
        return preg_match("/^((http:\/\/)|(https:\/\/)|)(((.*)(\.))|)($WhitelistedDomainsString)((\/)|$)/", $URL);
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
                            $Links[] = mb_substr($Text, $Entity->Offset, $Entity->Length);
                            break;
                    }
                }
            }
        }

        return $Links;
    }

    public function IsMessageNotSafe(Message $Message): bool
    {
        $MessageLinks = $this->GetLinksFromMessage($Message);

        foreach($MessageLinks as $MessageLink)
        {
            return !$this->IsURLAllowed($MessageLink) && $Message->SenderChat == null;
        }

        return false;
    }
}