<?php
namespace app\modules;

use gui;
use std;
use app;

class filesWorker 
{
    static function generateDesktopEntry($name,$icon = null)
    {
        $pwd = fs::abs('./');
        return "[Desktop Entry]\n".
               "Name=$name\n".
               "GenericName=Play this game with OnlineFix Launcher\n".
               "Exec=env GDK_BACKEND=x11 \"$pwd/jre/bin/java\" -jar \"".$GLOBALS['argv'][0]."\" \"$name\"\n".
               "Icon=$icon\n".
               "Path=$pwd\n".
               "Type=Application\n".
               "Categories=Game";
    }
    
    static function generateProcess($name)
    {
        if (execute('pidof steam',true)->getExitValue() == 1)
        {
            UXDialog::showAndWait(Localization::getByCode('FILESWORKER.STEAMNOTSTARTED'),'ERROR',$this);
            return;
        }
        
        if (fs::isFile('/usr/bin/protontricks-launch') == false)
        {
            UXDialog::showAndWait(Localization::getByCode('FILESWORKER.NOPROTONTRICKS'),'ERROR',$this);
            return;
        }
        
        $executable = app()->appModule()->games->get('executable',$name);
        $overrides = app()->appModule()->games->get('overrides',$name);
        $execString = ['protontricks-launch','--appid','480',$executable];
        
        if (app()->appModule()->settings->get('useGamemode'))
            array_unshift($execString,'gamemoderun');
        
        return new Process($execString,fs::parent($executable),['WINEDLLOVERRIDES'=>$overrides]);
    }
}