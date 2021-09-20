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
        $Scheme = parse_url($URL, PHP_URL_SCHEME);
        $URL = !isset($Scheme) ? "http://{$URL}" : $URL;

        $Domain = parse_url($URL, PHP_URL_HOST) ?? '';
        $Domain = mb_strtolower($Domain);

        if(in_array($Domain, $this->WhitelistedDomains))
            return true;

        $Allowed = false;

        foreach($this->WhitelistedDomains as $WhitelistedDomain )
        {
            $WhitelistedDomain = '.' . $WhitelistedDomain;
            if(strpos($Domain, $WhitelistedDomain) === (strlen($Domain) - strlen($WhitelistedDomain)))
            {
                $Allowed = true;
                break;
            }
        }

        return $Allowed;
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
                    if($Entity->Type == 'text_link')
                    {
                        $Links[] = $Entity->Url;
                    }
                    else if($Entity->Type == 'url')
                    {
                        $Links[] = mb_substr($Text, $Entity->Offset, $Entity->Length);
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