<?php

namespace WeRtOG\VagachatBot;

use WeRtOG\BottoGram\DatabaseManager\Database;
use WeRtOG\BottoGram\Telegram\Model\Chat;

class ChatChannelsManager
{
    protected Database $Database;

    public function __construct(Database $Database)
    {
        $this->Database = $Database;
    }

    public function RegisterChannelIfNotRegistered(string $ChatID, string $ChannelID, string $ChannelName): void
    {
        $ChannelName = $this->Database->EscapeString(strip_tags($ChannelName));
        
        if($ChatID != $ChannelID)
            $this->Database->CallProcedure('registerChannelIfNotRegistered', [$ChatID, $ChannelID, $ChannelName]);
    }

    public function IsChannelInBanList(string $ChatID, string $ChannelID): bool
    {
        $DBResult = $this->Database->CallProcedure('getChannel', [$ChatID, $ChannelID]);

        if($DBResult != null)
        {
            return ($DBResult['Banned'] ?? null) == 1;
        }

        return false;
    }

    public function SetChannelStatus(string $ChatID, string $ChannelID, bool $IsBanned): void
    {
        if($ChatID != $ChannelID)
            $this->Database->CallProcedure('setChannelStatus', [$ChatID, $ChannelID, $IsBanned ? '1' : '0']);
    }

    public function RememberMessage(string $ChatID, string $ChannelID, string $MessageID): void
    {
        $this->Database->CallProcedure('rememberMessage', [$ChatID, $ChannelID, $MessageID]);
    }

    public function GetChannelMessages(string $ChatID, string $ChannelID): array
    {
        $Result = [];
        
        $DBResult = $this->Database->CallProcedure('getChannelMessages', [$ChatID, $ChannelID], true) ?? [];
        foreach($DBResult as $Item)
            $Result[] = $Item['MessageID'];

        return $Result;
    }

    public function DeleteChannelMessages(string $ChatID, string $ChannelID): void
    {
        $this->Database->CallProcedure('deleteChannelMessages', [$ChatID, $ChannelID]);
    }

}