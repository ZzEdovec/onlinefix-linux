<?php
namespace app\modules;

use php\io\IOException;
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
        
        $overridesArr = ['WINEDLLOVERRIDES'=>app()->appModule()->games->get('overrides',$name),'WINEDEBUG'=>'+warn,+err,+trace'];
        $execArr = ['protontricks-launch','--appid','480',$executable];
        $envArr = str::split(app()->appModule()->games->get('environment',$name),' ');
        
        if (isset($envArr[0]))
            $overridesArr = array_merge($overridesArr,$envArr);
            
        if (app()->appModule()->games->get('gamemode',$name) and fs::isFile('/usr/bin/gamemoderun'))
            array_unshift($execArr,'gamemoderun');
        if (app()->appModule()->games->get('mangohud',$name) and fs::isFile('/usr/bin/mangohud'))
            array_unshift($execArr,'mangohud');
        
        return new Process($execArr,fs::parent($executable),$overridesArr);
    }
    
    static function runWithDebug($process,$gameName)
    {
        UXApplication::setImplicitExit(false);
        
        if (app()->appModule()->games->get('fakeSteam',$gameName))
            $fakeSteam = new Process(['protontricks-launch','--appid','480',fs::abs('./steam.exe')])->start();
        
        $process = $process->startAndWait();
        
        if (isset($fakeSteam))
        {
            try {
                $fakeSteam->getOutput()->write("quit\n");
                $fakeSteam->getOutput()->flush();
            }
            catch (IOException $ex){}
        }
        
        if ($process->getExitValue() != 0 and uiLaterAndWait(function (){return app()->form('MainForm')->data('manualKill');}) == false)
        {
            uiLaterAndWait(function () use ($process,$gameName){
                if (app()->appModule()->games->get('fakeSteam',$gameName) == false and $process->getExitValue() == 1 and uiConfirm('Вы сейчас получали ошибку "Steam не запущен"?'))
                {
                    app()->appModule()->games->set('fakeSteam',true,$gameName);
                    UXDialog::showAndWait(Localization::getByCode('FILESWORKER.FAKESTEAMENABLED'));
                }
                else 
                {
                    $info = 'Game name - '.$gameName."\n".
                            'Exit code - '.$process->getExitValue()."\n".
                            "Game settings: \n";
                            
                    foreach (app()->appModule()->games->section($gameName) as $param => $value)
                        $info .= "\t$param - $value\n";
                    
                    app()->form('log')->textArea->text = $info."\nGame output:\n".$process->getError()->readFully();
                    app()->form('log')->data('gameName',$gameName);
                    
                    app()->showFormAndWait('log');
                }
            });
        }
        
        uiLaterAndWait(function ()
        {
            if (app()->form('MainForm')->data('manualKill') == true)
                app()->form('MainForm')->data('manualKill',false);
        });
        
        UXApplication::setImplicitExit(true);
    }
    
    /*static function checkProtontricksInstallation()
    {
        if (fs::isFile('/usr/bin/protontricks') and fs::isFile('/usr/bin/protontricks-launch'))
            return 'system';
        elseif (fs::isFile('/usr/bin/flatpak') and execute('flatpak info com.github.Matoking.protontricks',true)->getExitValue() == 0)
            return 'flatpak';
        else 
            return false;
    }
    
    static function getProtontricksLaunchString($type)
    {
        $install = self::checkProtontricksInstallation();
        var_dump($install);
        var_dump(execute('flatpak info com.github.Matoking.protontricks',true)->getExitValue());
        
        if ($install == 'system')
        {
            if ($type == 'main')
                return ['protontricks'];
            else 
                return ['protontricks-launch'];
        }
        else 
        {
            if ($type == 'main')
                return ['flatpak','run','com.github.Matoking.protontricks'];
            else 
                return ['flatpak','run','--command=protontricks-launch','com.github.Matoking.protontricks'];
        }
    }*/
}