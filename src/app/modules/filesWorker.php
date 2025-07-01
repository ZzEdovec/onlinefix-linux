<?php
namespace app\modules;

use framework;
use facade\Json;
use Throwable;
use vdf\VDF;
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
               "Exec=env GDK_BACKEND=x11 \"$pwd/jre/bin/java\" -Dprism.forceGPU=true -jar \"".$GLOBALS['argv'][0]."\" \"$name\"\n".
               "Icon=$icon\n".
               "Path=$pwd\n".
               "Type=Application\n".
               "Categories=Game";
    }
    
    static function generateProcess($name,$debug = false)
    {
        if (execute('pidof steam',true)->getExitValue() == 1)
        {
            UXDialog::showAndWait(Localization::getByCode('FILESWORKER.STEAMNOTSTARTED'),'ERROR');
            return;
        }
        
        $executable = app()->appModule()->games->get('executable',$name);
        $proton = self::getProtonExecutable($name);
        if ($proton == false)
        {
            UXDialog::showAndWait(Localization::getByCode('FILESWORKER.PROTON.NOTFOUND'),'ERROR');
            return;
        }
        
        $argsBeforeExec = app()->appModule()->games->get('argsBefore',$name);
        $argsAfterExec = app()->appModule()->games->get('argsAfter',$name);
        
        $exec = "\"$proton\" run \"$executable\"";
        $userHome = System::getProperty('user.home');
        $dxOverrides = 'd3d11=n;d3d10=n;d3d10core=n;dxgi=n;openvr_api_dxvk=n;d3d12=n;d3d12core=n;d3d9=n;d3d8=n;';
        $mainEnvironment = ['WINEDLLOVERRIDES'=>$dxOverrides.app()->appModule()->games->get('overrides',$name),'WINEDEBUG'=>$debug ? '+warn,+err,+trace' : '-all',
                            'STEAM_COMPAT_DATA_PATH'=>fs::parent($executable).'/OFME Prefix','STEAM_COMPAT_CLIENT_INSTALL_PATH'=>"$userHome/.steam/steam"];
        foreach (str::split(app()->appModule()->games->get('environment',$name),' ') as $env)
        {
            $env = str::split($env,'=');
            if (isset($env[1]) == false)
                continue;
            
            $customEnvironment[$env[0]] = $env[1];
        }
        
        if (isset($customEnvironment))
            $mainEnvironment = array_merge($mainEnvironment,$customEnvironment);
        if (app()->appModule()->games->get('steamOverlay',$name))
        {
            $mainEnvironment = array_merge($mainEnvironment,['LD_PRELOAD'=>":$userHome/.local/share/Steam/ubuntu12_32/gameoverlayrenderer.so:".
                                                                            "$userHome/.local/share/Steam/ubuntu12_64/gameoverlayrenderer.so"]);
        }
        
        if (app()->appModule()->games->get('steamRuntime',$name))
        {
            $steamRuntime = self::findSteamRuntime();
            if ($steamRuntime == false)
                app()->appModule()->games->set('steamRuntime',false,$name);
            else 
                $exec = "\"$steamRuntime\" $exec";
        }
        if ($argsBeforeExec != null)
            $exec = $argsBeforeExec." $exec";
        if ($argsAfterExec != null)
            $exec .= " $argsAfterExec";
        if ($debug == false)
            $exec .= ' > /dev/null 2>&1';
        
        return new Process(['bash','-c',$exec],fs::parent($executable),$mainEnvironment);
    }
    
    static function run($process,$gameName,$debug = false)
    {
        $exeParent = fs::parent(app()->appModule()->games->get('executable',$gameName));
        $timeStart = Time::seconds();
        
        UXApplication::setImplicitExit(false);
        fs::makeDir($exeParent.'/OFME Prefix');
        
        if (app()->appModule()->games->get('mainPath',$gameName) == null and app()->appModule()->games->get('migratedFromLegacy',$gameName) != true) #Migrate from legacy. Will be removed in v2.2
        {
            self::migrateFromOldLauncher($gameName,$exeParent.'/OFME Prefix');
            Logger::info("Migrated - $gameName");
        }
        
        $process = $process->startAndWait();
        
        if ($debug)
            self::debug($process,$gameName);
        
        $timeStop = Time::seconds();
        app()->appModule()->games->set('timeSpent',($timeStop - $timeStart) + app()->appModule()->games->get('timeSpent',$gameName),$gameName);
        
        UXApplication::setImplicitExit(true);
    }
    
    static function debug($process,$gameName)
    {
        uiLaterAndWait(function () use ($process,$gameName){
            $info = 'Game name - '.$gameName."\n".
                    'Exit code - '.$process->getExitValue()."\n".
                    "Game settings:\n";
                    
            foreach (app()->appModule()->games->section($gameName) as $param => $value)
                $info .= "\t$param - $value\n";
            
            $info .= "OS Release:\n";
            foreach (str::split(file_get_contents('/etc/os-release'),"\n") as $line)
                $info .= "\t$line\n";
            
            app()->form('log')->textArea->text = $info."\nWine output:\n".$process->getError()->readFully();
            app()->form('log')->textArea->text .= "\n\n\nGame output:\n".$process->getInput()->readFully();
            app()->form('log')->data('gameName',$gameName);
            
            app()->showFormAndWait('log');
        });
        
        uiLaterAndWait(function ()
        {
            if (app()->form('MainForm')->data('manualKill') == true)
                app()->form('MainForm')->data('manualKill',false);
        });
    }
    
    static function findSteamRuntime()
    {
        try 
        {
            $libraryFolders = VDF::fromFile(System::getProperty('user.home').'/.steam/steam/steamapps/libraryfolders.vdf');
            foreach ($libraryFolders['libraryfolders'] as $folder)
            {
                if (isset($folder['apps'][1628350]) and fs::isFile($folder['path'].'/steamapps/common/SteamLinuxRuntime_sniper/run')) # 1628350 - SteamLinuxRuntime_sniper ID
                    return $folder['path'].'/steamapps/common/SteamLinuxRuntime_sniper/run';
            }
        } catch (Throwable $ex) {}
        
        return false;
    }
    
    static function fetchProtonReleases()
    {
        try 
        { 
            $releases = Json::decode(fs::get('https://api.github.com/repos/gloriouseggroll/proton-ge-custom/releases'));
            if (isset($releases['message']))
                throw new IOException;
            
            return $releases;
        } catch (Throwable $ex)
        {
            try
            {
                $url = fs::get('https://zzedovec.github.io/resources/ofmelauncher/latestproton');
                if (str::contains($url,'tar.gz') == false)
                    throw new IOException;
                
                return $url;
            } catch (Throwable $ex){}
            
            return false;
        }
    }
    
    static function findFirstAvailableProton()
    {
        $protons = File::of('./protons')->find(function ($d,$f){return fs::isFile($d.'/'.$f.'/proton');});
        if ($protons == [])
            return false;
        else 
            return $protons[0];
    }

    static function getProtonExecutable($gameName)
    {
        $proton = app()->appModule()->games->get('proton',$gameName);
        if ($proton == 'GE-Proton Latest' or $proton == null)
        {
            if (isset($GLOBALS['LatestProton']) == false)
                $availableName = self::findFirstAvailableProton();
            else 
            {
                $availableName = str::sub($GLOBALS['LatestProton'],str::lastPos($GLOBALS['LatestProton'],'/') + 1,str::pos($GLOBALS['LatestProton'],'.tar'));
                if (fs::isFile('./protons/'.$availableName.'/proton') == false)
                {
                    app()->form('MainForm')->gameDebugButton->enabled = app()->form('MainForm')->playButton->enabled = false;
                    
                    app()->form('protonDownloader')->startDownload($availableName,$GLOBALS['LatestProton']);
                    app()->showFormAndWait('protonDownloader');
                    
                    app()->form('MainForm')->gameDebugButton->enabled = app()->form('MainForm')->playButton->enabled = true;
                    
                    if (fs::isFile('./protons/'.$availableName.'/proton') == false) #Check again after download
                        $availableName = self::findFirstAvailableProton();
                }
            }
            
            if (fs::isFile("./protons/$availableName/proton"))
                return fs::abs("./protons/$availableName/proton");
            else 
                return false;
        }
        elseif (fs::isFile('./protons/'.$proton.'/proton'))
            return fs::abs('./protons/'.$proton.'/proton');
        else
        {
            $proton = self::findFirstAvailableProton();
            if ($proton == false)
                return false;
            else 
                return $proton;
        }
    }
    
    static function migrateFromOldLauncher($gameName,$prefixPath)
    {
        $prefixPath .= '/pfx/drive_c/users/steamuser';
        try 
        {
            $libraryFolders = VDF::fromFile(System::getProperty('user.home').'/.steam/steam/steamapps/libraryfolders.vdf');
            foreach ($libraryFolders['libraryfolders'] as $folder)
            {
                if (isset($folder['apps'][480]) and fs::isDir($folder['path'].'/steamapps/compatdata/480/pfx/drive_c/users/steamuser'))
                    $path = $folder['path'].'/steamapps/compatdata/480/pfx/drive_c/users/steamuser';
            }
        } catch (Throwable $ex) {return;}
        
        $dirs = ['AppData','Saved Games','Documents'];
        foreach ($dirs as $dir)
        {
            $files = fs::scan("$path/$dir",['excludeDirs'=>true]);
            foreach ($files as $file)
            {
                $clearPath = str::replace($file,$path,null);
                fs::ensureParent("$prefixPath/$clearPath");
                fs::copy($file,"$prefixPath/$clearPath");
            }
        }
        
        app()->appModule()->games->put(['proton'=>'GE-Proton Latest',
                                        'steamRuntime'=>filesWorker::findSteamRuntime() != false ? true : false,
                                        'steamOverlay'=>true,
                                        'migratedFromLegacy'=>true],$gameName);
    }

}