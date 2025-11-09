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

class FilesWorker 
{
    static function generateDesktopEntry($name,$icon = null)
    {
        $pwd = fs::abs('./');
        $forceGPU = System::getProperty('prism.forceGPU');
        $exec = fs::isFile('/usr/bin/onlinefix-linux-launcher') ? '/usr/bin/onlinefix-linux-launcher' : fs::abs('./onlinefix-linux-launcher');
        
        return "[Desktop Entry]\n".
               "Name=$name\n".
               "GenericName=Play this game with OnlineFix Launcher\n".
               "Exec=\"$exec\" \"$name\"\n".
               "Icon=$icon\n".
               "Path=$pwd\n".
               "Type=Application\n".
               "Categories=Game;";
    }
    
    static function generateProcess($name,$debug = false)
    {
        if (app()->appModule()->launcher->get('noSteamRequest','User Settings') == false and execute('pidof steam',true)->getExitValue() == 1)
        {
            $steam = self::runSteam();
            
            if ($steam == false)
            {
                uiLater(function ()
                {
                    UXDialog::showAndWait(Localization::getByCode('FILESWORKER.STEAMNOTSTARTED'),'ERROR');
                    
                    $mainForm = app()->form('MainForm');
                    if ($mainForm->visible)
                        $mainForm->switchPlayButton('play');
                });
                return;
            }
        }
        
        $executable = app()->appModule()->games->get('executable',$name);
        $proton = self::getProtonExecutable($name);

        if ($proton == false)
        {
            uiLater(function (){UXDialog::showAndWait(Localization::getByCode('FILESWORKER.PROTON.NOTFOUND'),'ERROR');});
            return;
        }
        if (fs::isFile($executable) == false)
        {
            uiLater(function (){UXDialog::showAndWait(Localization::getByCode('FILESWORKER.NOGAME'),'ERROR');});
            return;
        }
        
        $argsBeforeExec = app()->appModule()->games->get('argsBefore',$name);
        $argsAfterExec = app()->appModule()->games->get('argsAfter',$name);
        
        $exec = [$proton,'run',$executable];
        $userHome = System::getProperty('user.home');
        $wined3d = app()->appModule()->games->get('wined3d',$name);
        
        if ($wined3d == false)
            $dxOverrides = 'd3d11=n;d3d10=n;d3d10core=n;dxgi=n;openvr_api_dxvk=n;d3d12=n;d3d12core=n;d3d9=n;d3d8=n;';
        
        $mainEnvironment = ['WINEDLLOVERRIDES'=>$dxOverrides.app()->appModule()->games->get('overrides',$name),
                            'WINEDEBUG'=>$debug ?: '-all',
                            'STEAM_COMPAT_DATA_PATH'=>self::getProtonPrefixPath($name),
                            'STEAM_COMPAT_CLIENT_INSTALL_PATH'=>"$userHome/.steam/steam",
                            'PROTON_USE_WINED3D'=>$wined3d,
                            'PROTON_ENABLE_WAYLAND'=>app()->appModule()->games->get('nativeWayland',$name)];
                            
        if (app()->appModule()->games->get('steamOverlay',$name))
        {
            $mainEnvironment = array_merge($mainEnvironment,['LD_PRELOAD'=>":$userHome/.local/share/Steam/ubuntu12_32/gameoverlayrenderer.so:".
                                                                            "$userHome/.local/share/Steam/ubuntu12_64/gameoverlayrenderer.so",
                                                             'ENABLE_VK_LAYER_VALVE_steam_overlay_1'=>true,
                                                             'SteamOverlayGameId'=>app()->appModule()->games->get('fakeSteamID',$name) ?? 480]);
        }
        
        if (app()->appModule()->games->get('environment',$name) != null)
            $mainEnvironment = array_merge($mainEnvironment,envViewer::parseEnvironmentArray($name));

        if ($argsAfterExec != null)
            $exec = array_merge($exec,str::split($argsAfterExec,' '));
        if (app()->appModule()->games->get('steamRuntime',$name))
        {
            $steamRuntime = self::findSteamRuntime();
            if ($steamRuntime == false)
                app()->appModule()->games->set('steamRuntime',false,$name);
            else 
                array_unshift($exec,$steamRuntime);
        }
        if ($argsBeforeExec != null)
            array_unshift($exec,$argsBeforeExec);
        
        fs::ensureParent($mainEnvironment['STEAM_COMPAT_DATA_PATH']);
        fs::makeDir($mainEnvironment['STEAM_COMPAT_DATA_PATH']);
        
        try 
        {
            return new Process($exec,fs::parent($executable),$mainEnvironment);
        } catch (Throwable $ex)
        {
            if (str::contains($ex->getMessage(),'Invalid environment variable'))
                uiLater(function (){UXDialog::showAndWait(Localization::getByCode('FILESWORKER.REMOVEENV'),'ERROR');});
            
            Logger::error($ex->getMessage());
            return;
        }
    }
    
    static function run($process,$gameName,$debug = false)
    {
        $timeStart = Time::seconds();
        
        $GLOBALS['implicitDisableReason'] = 'game';
        UXApplication::setImplicitExit(false);
        
        $process = $process->start();
        self::hookProcessOuts($process,boolval($debug));
        
        if ($debug)
            self::debug($exit,$gameName);
        
        $timeStop = Time::seconds();
        app()->appModule()->games->set('timeSpent',($timeStop - $timeStart) + app()->appModule()->games->get('timeSpent',$gameName),$gameName);
        
        if ($GLOBALS['implicitDisableReason'] == 'game')
            UXApplication::setImplicitExit(true);
    }
    
    static function debug($exitCode,$gameName)
    {
        uiLaterAndWait(function () use ($exitCode,$gameName){
            $info = 'Game name - '.$gameName."\n".
                    'Exit code - '.$exitCode."\n".
                    "Game settings:\n";
                    
            foreach (app()->appModule()->games->section($gameName) as $param => $value)
                $info .= "\t$param - $value\n";
            
            $info .= "OS Release:\n";
            foreach (str::split(file_get_contents('/etc/os-release'),"\n") as $line)
                $info .= "\t$line\n";
            
            app()->form('log')->textArea->text = "$info\n".app()->form('log')->textArea->text;
            app()->form('log')->data('gameName',$gameName);
            app()->form('log')->title = "$gameName log";
            
            app()->showFormAndWait('log');
        });
    }
    
    static function hookProcessOuts(Process $process,$debug = false,$wait = true)
    {
        $baseHook = function ($l,$std,$debug)
        {
            echo "$l\n";
                
            if ($debug)
                uiLater(function () use ($l,$std){app()->form('log')->textArea->text .= "STD$std - $l\n";});
        };
        new Thread(function () use ($process,$debug,$baseHook)
        {
            $process->getError()->eachLine(function ($l) use ($debug,$baseHook) {$baseHook($l,'ERR',$debug);});
        })->start();
        new Thread(function () use ($process,$debug,$baseHook)
        {
            $process->getInput()->eachLine(function ($l) use ($debug,$baseHook) {$baseHook($l,'OUT',$debug);});
        })->start();
        
        while ($process->getExitValue() === null and $wait == true)
            sleep(2);
        
        return $process->getExitValue();
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
    
    static function findNewestAvailableProton()
    {
        $protonsPath = launcherSettings::getBasePathFor('protons');
        
        foreach (File::of($protonsPath)->findFiles(function ($d,$f){return fs::isFile("$d/$f/proton");}) as $proton)
        {
            $protonExec = File::of("$proton/proton");
            $protonDate = $protonExec->lastModified();
            
            if ($protonDate > $latestProtonDate)
            {
                $latestProtonDate = $protonDate;
                $latestProton = $proton;
            }
        }
        
        if ($latestProton == null)
            return false;
        else 
            return $latestProton->getName();
    }
    
    static function getInstalledProtons()
    {
        $protonPath = launcherSettings::getBasePathFor('protons');
        if (fs::isDir($protonPath))
        {
            $dir = File::of($protonPath);
            $dirs = $dir->find(function ($d,$f){return fs::isFile("$d/$f/proton");});
            
            return $dirs;
        }
        else 
            return [];
    }

    static function getProtonExecutable($gameName = null,$exec = 'proton',$skipIfNotFound = false)
    {
        $proton = $gameName != null ? app()->appModule()->games->get('proton',$gameName) : app()->appModule()->launcher->get('defaultProton','User Settings') ?? 'GE-Proton Latest';
        $protonPath = launcherSettings::getBasePathFor('protons');
        
        if ($proton == 'GE-Proton Latest' or $proton == null)
        {
            while ($GLOBALS['LatestProton'] == 'fetching') #Block main thread until fetched
                wait(300);
                
            if (isset($GLOBALS['LatestProton']) == false)
                $availableName = self::findNewestAvailableProton();
            else 
            {
                $availableName = str::sub($GLOBALS['LatestProton'],str::lastPos($GLOBALS['LatestProton'],'/') + 1,str::pos($GLOBALS['LatestProton'],'.tar'));
                if (($exec == 'proton' and fs::isFile("$protonPath/$availableName/proton") == false) or fs::isFile("$protonPath/$availableName/files/bin/$exec") == false)
                {
                    if ($skipIfNotFound == false)
                    {
                        $mainForm = app()->form('MainForm');
                        if ($mainForm->visible) {uiLater(function () use ($mainForm){$mainForm->switchPlayButton('wait');});}
                        
                        uiLaterAndWait(function () use ($availableName)
                        {
                            app()->form('protonDownloader')->startDownload($availableName,$GLOBALS['LatestProton']);
                            app()->showFormAndWait('protonDownloader');
                        });
                    }
                    
                    if ($skipIfNotFound or ($exec == 'proton' and fs::isFile("$protonPath/$availableName/proton") == false) or fs::isFile("$protonPath/$availableName/files/bin/$exec") == false) #Check again after download
                        $availableName = self::findNewestAvailableProton();
                }
            }
            
            if (($exec == 'proton' and fs::isFile("$protonPath/$availableName/proton")) or fs::isFile("$protonPath/$availableName/files/bin/$exec"))
                return $exec == 'proton' ? fs::abs("$protonPath/$availableName/proton") : fs::abs("$protonPath/$availableName/files/bin/$exec");
            else 
                return false;
        }
        elseif (($exec == 'proton' and fs::isFile("$protonPath/$proton/proton")) or fs::isFile("$protonPath/$proton/files/bin/$exec"))
            return $exec == 'proton' ? fs::abs("$protonPath/$proton/proton") : fs::abs("$protonPath/$proton/files/bin/$exec");
        else
        {
            $proton = self::findNewestAvailableProton();
            if ($proton == false)
                return false;
            else 
            {
                $gameName != null ? app()->appModule()->games->set('proton',$proton,$gameName) : app()->appModule()->launcher->set('defaultProton',$proton);
                
                return $exec == 'proton' ? fs::abs("$protonPath/$proton/proton") : fs::abs("$protonPath/$proton/files/bin/$exec");
            }
        }
    }
    
    static function getProtonPrefixPath($gameName,$type = 'proton')
    {
        $prefixPath = app()->appModule()->games->get('prefixPath',$gameName) ?? fs::parent(app()->appModule()->games->get('executable',$gameName)).'/OFME Prefix';
        if ($type == 'wine')
            $prefixPath .= '/pfx';
        
        fs::ensureParent($prefixPath);
        fs::makeDir($prefixPath);
        
        return $prefixPath;
    }
    
    static function runSteam()
    {
        $switchButton = function ($enabled)
        {
            uiLater(function () use ($enabled)
            {
                $mainForm = app()->form('MainForm');
                if ($mainForm->visible == false)
                    return;
                
                $mainForm->switchPlayButton('wait');
            });
        };
        
        $switchButton(false);
        
        try
        {
            $steam = execute('/usr/bin/steam -silent');
            self::hookProcessOuts($steam,false,false);
        
            $logUsers = File::of(System::getProperty('user.home').'/.local/share/Steam/config/loginusers.vdf');
            $lastMod = $logUsers->lastModified();
            
            while ($attempts <= 420 and ($logUsers->exists() == false or $logUsers->lastModified() == $lastMod)) # 420 attempts = 7 minutes
            {
                if ($steam->getExitValue() !== null)
                    $attempts = 420;
                    
                $attempts += 1;
                wait('1s');
            }
        } catch (Throwable $ex) {$attempts = 421;}
        
        $switchButton(true);
        
        if ($attempts > 420)
            return false;
        else 
            return true;
    }
    
    static function getThirdParty($prog)
    {
        $progs = ['7zip'=>fs::isFile('/usr/bin/7z') ? '/usr/bin/7z' : './thirdparty/7zip/7z','unrar'=>fs::isFile('/usr/bin/unrar') ? '/usr/bin/unrar' : './thirdparty/unrar/unrar'];
        
        if (fs::isFile($progs[$prog]) == false)
        {
            UXDialog::showAndWait(sprintf(Localization::getByCode('FILESWORKER.NOSUBMODULE'),$progs[$prog]));
            return;
        }
        
        $progF = File::of($progs[$prog]);
        $progF->setExecutable(true);
        
        return $progF->getAbsolutePath();
    }
}