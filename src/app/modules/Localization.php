<?php
namespace app\modules;

use facade\Json;
use std;

class Localization 
{    
    static function getByCode(string $code)
    {
        $locale = Locale::getDefault()->getLanguage();
        if (ResourceStream::exists('res://locale/'.$locale.'.json') == false)
            $locale = 'en';
        
        return Json::decode(ResourceStream::of('res://locale/'.$locale.'.json')->readFully())[str::upper($code)];
    }
}