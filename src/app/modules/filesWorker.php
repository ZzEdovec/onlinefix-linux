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
    
    static function generateProcess($name,$debug = false)
    {
        if (execute('pidof steam',true)->getExitValue() == 1)
        {
            UXDialog::showAndWait(Localization::getByCode('FILESWORKER.STEAMNOTSTARTED'),'ERROR',$this);
            return;
        }
        
        if (self::findProtontricksPath() == false)
        {
            UXDialog::showAndWait(Localization::getByCode('FILESWORKER.NOPROTONTRICKS'),'ERROR',$this);
            return;
        }
        
        $executable = app()->appModule()->games->get('executable',$name);
        
        $dxOverrides = 'd3d11=n;d3d10=n;d3d10core=n;dxgi=n;openvr_api_dxvk=n;d3d12=n;d3d12core=n;d3d9=n;d3d8=n;'; #For some reason, some distributions use wined3d instead of dxvk, this is workaround
        $overridesArr = ['WINEDLLOVERRIDES'=>$dxOverrides.app()->appModule()->games->get('overrides',$name),'WINEDEBUG'=>$debug ? '+warn,+err,+trace' : '-all'];
        $exec = '"'.filesWorker::findProtontricksPath().'protontricks-launch" --appid 480 "'.$executable.'"';
        $envArr = str::split(app()->appModule()->games->get('environment',$name),' ');
        
        if (isset($envArr[0]))
            $overridesArr = array_merge($overridesArr,$envArr);
            
        if (app()->appModule()->games->get('gamemode',$name) and fs::isFile('/usr/bin/gamemoderun'))
            $exec = 'gamemoderun '.$exec;
        if ($debug == false)
            $exec .= ' > /dev/null 2>&1';
        #if (app()->appModule()->games->get('mangohud',$name) and fs::isFile('/usr/bin/mangohud'))
            #array_unshift($execArr,'mangohud');
        
        return new Process(['bash','-c',$exec],fs::parent($executable),$overridesArr);
    }
    
    static function run($process,$gameName,$debug = false)
    {
        UXApplication::setImplicitExit(false);
        
        if (app()->appModule()->games->get('fakeSteam',$gameName))
            $fakeSteam = new Process([self::findProtontricksPath().'protontricks-launch','--appid','480',fs::abs('./steam.exe')])->start();
        
        $process = $process->startAndWait();
        
        if (isset($fakeSteam))
        {
            try {
                $fakeSteam->getOutput()->write("quit\n");
                $fakeSteam->getOutput()->flush();
            }
            catch (IOException $ex){}
        }
        
        if ($debug)
            self::debug($process,$gamename);

        UXApplication::setImplicitExit(true);
    }
    
    static function debug($process,$gameName)
    {
        uiLaterAndWait(function () use ($process,$gameName){
            /*if (app()->appModule()->games->get('fakeSteam',$gameName) == false and $process->getExitValue() == 1 and uiConfirm(Localization::getByCode('FILESWORKER.ISSTEAMNOTSTARTEDERROR')))
            {
                app()->appModule()->games->set('fakeSteam',true,$gameName);
                UXDialog::showAndWait(Localization::getByCode('FILESWORKER.FAKESTEAMENABLED'));
            }
            else 
            {*/
                $info = 'Game name - '.$gameName."\n".
                        'Exit code - '.$process->getExitValue()."\n".
                        "Game settings: \n";
                        
                foreach (app()->appModule()->games->section($gameName) as $param => $value)
                    $info .= "\t$param - $value\n";
                
                app()->form('log')->textArea->text = $info."\nWine output:\n".$process->getError()->readFully();
                app()->form('log')->textArea->text .= "\n\n\nGame output:\n".$process->getInput()->readFully();
                app()->form('log')->data('gameName',$gameName);
                
                app()->showFormAndWait('log');
            #}
        });
        
        uiLaterAndWait(function ()
        {
            if (app()->form('MainForm')->data('manualKill') == true)
                app()->form('MainForm')->data('manualKill',false);
        });
    }
    
    static function findProtontricksPath()
    {
        if (fs::isFile(System::getProperty('user.home').'/.local/bin/protontricks') and fs::isFile(System::getProperty('user.home').'/.local/bin/protontricks-launch'))
            return System::getProperty('user.home').'/.local/bin/';
        elseif (fs::isFile('/usr/bin/protontricks') and fs::isFile('/usr/bin/protontricks-launch'))
            return '/usr/bin/';
        else 
            return false;
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