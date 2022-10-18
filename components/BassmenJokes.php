<?php

namespace WeRtOG\VagachatBot;

class BassmenJokes
{
    public static function GetRandom(): string
    {
        $Jokes = [
            
        ];
        return $Jokes[array_rand($Jokes)] ?? 'Шутки кончились :(';
    }
}
