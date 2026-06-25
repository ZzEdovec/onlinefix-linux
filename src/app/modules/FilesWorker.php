<?php
namespace app\modules;

use httpclient;
use facade\Async;
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
    static function generateDesktopEntry($name, $icon = null)
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
        if (app()->appModule()->launcher->get('noSteamRequest','User Settings') == false)
        {
            if (execute("which steam")->getExitValue() != 0)
            {
                uiLaterAndWait(function (){UXDialog::showAndWait(app()->module('Localization')->_('FILESWORKER.NOSTEAM'),'ERROR');});
                return;
            }
            elseif (execute('pidof steam',true)->getExitValue() == 1)
            {
                $steam = self::runSteam();
                
                if ($steam == false)
                {
                    uiLaterAndWait(function ()
                    {
                        UXDialog::showAndWait(app()->module('Localization')->_('FILESWORKER.STEAMNOTSTARTED'),'ERROR');
                        
                        $mainForm = app()->form('MainForm');
                        if ($mainForm->visible)
                            $mainForm->switchPlayButton('play');
                    });
                    return;
                }
            }
        }
        
        $executable = app()->appModule()->games->get('executable',$name);
        $proton = self::getProtonExecutable($name);

        if ($proton == false)
        {
            uiLaterAndWait(function (){UXDialog::showAndWait(app()->module('Localization')->_('FILESWORKER.PROTON.NOTFOUND'),'ERROR');});
            return;
        }
        if (fs::isFile($executable) == false)
        {
            uiLaterAndWait(function (){UXDialog::showAndWait(app()->module('Localization')->_('FILESWORKER.NOGAME'),'ERROR');});
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
            $mainEnvironment = array_merge($mainEnvironment,['LD_PRELOAD'=>":$userHome/.steam/steam/ubuntu12_32/gameoverlayrenderer.so:".
                                                                            "$userHome/.steam/steam/ubuntu12_64/gameoverlayrenderer.so",
                                                             'ENABLE_VK_LAYER_VALVE_steam_overlay_1'=>true,
                                                             'SteamOverlayGameId'=>app()->appModule()->games->get('fakeSteamID',$name) ?? 480]);
        }
        
        if (app()->appModule()->games->get('environment',$name) != null)
            $mainEnvironment = array_merge($mainEnvironment,envViewer::parseEnvironmentArray($name));

        if ($argsAfterExec != null)
            $exec = array_merge($exec,str::split($argsAfterExec,' '));
            
        if (app()->appModule()->games->get('steamRuntime',$name))
        {
            $steamRuntime = self::findSteamRuntime(app()->appModule()->games->get('proton',$name));
            if ($steamRuntime == false)
                app()->appModule()->games->set('steamRuntime',false,$name);
            else 
                $exec = array_merge([$steamRuntime,'--'],$exec);
        }
        
        if ($argsBeforeExec != null)
            $exec = array_merge(str::split($argsBeforeExec,' '),$exec);
        
        fs::ensureParent($mainEnvironment['STEAM_COMPAT_DATA_PATH']);
        fs::makeDir($mainEnvironment['STEAM_COMPAT_DATA_PATH']);
        
        try 
        {
            return new Process($exec,fs::parent($executable),$mainEnvironment);
        } catch (Throwable $ex)
        {
            if (str::contains($ex->getMessage(),'Invalid environment variable'))
                uiLaterAndWait(function (){UXDialog::showAndWait(app()->module('Localization')->_('FILESWORKER.REMOVEENV'),'ERROR');});
            
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
        $exit = self::hookProcessOuts($process,boolval($debug));
        
        if ($debug)
            self::debug($exit,$gameName);
        
        $timeStop = Time::seconds();
        app()->appModule()->games->set('timeSpent',($timeStop - $timeStart) + app()->appModule()->games->get('timeSpent',$gameName),$gameName);
        
        if ($GLOBALS['implicitDisableReason'] == 'game')
            UXApplication::setImplicitExit(true);
    }
    
    static function debug($exitCode,$gameName)
    {
        uiLater(function () use ($exitCode,$gameName){
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
            
            app()->showForm('log');
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
    
    static function findSteamRuntime($forProton)
    {
        $protonPath = self::ensureProtonPath($forProton);
        $protonVersion = Regex::of('GE-Proton(\d+)-', null, file_get_contents("$protonPath/version"))->one()[1];

        if ($protonVersion == null)
            $protonVersion = 11; // presumably the new user will download the latest Proton
            
        if ($protonVersion >= 11) {
            if (System::getProperty("os.arch") == "aarch64") {
                $appid = 4185400;
                $dirname = "SteamLinuxRuntime_4-arm64";
            }
            else {
                $appid = 4183110;
                $dirname = "SteamLinuxRuntime_4";
            }
        }
        elseif ($protonVersion >= 8 && $protonVersion <= 10) {
            $appid = 1628350;
            $dirname = "SteamLinuxRuntime_sniper";
        }
        else {
            $appid = 1391110;
            $dirname = "SteamLinuxRuntime_soldier";
        }
        
        try 
        {
            $libraryFolders = VDF::fromFile(System::getProperty('user.home').'/.steam/steam/steamapps/libraryfolders.vdf');
            foreach ($libraryFolders['libraryfolders'] as $folder)
            {
                if (isset($folder['apps'][$appid]) and fs::isFile($folder['path']."/steamapps/common/$dirname/run"))
                    return $folder['path']."/steamapps/common/$dirname/run";
            }
        } catch (Throwable $ex) {}
        
        return false;
    }
    
    static function fetchProtonReleases()
    {
        $protons = [];
        
        $client = new HttpClient;
        $client->connectTimeout = $client->readTimeout = 5000;
        
        $releases = $client->get('https://api.github.com/repos/gloriouseggroll/proton-ge-custom/releases');
        if ($releases->isFail()) {
            Logger::error("Failed to fetch GE-Proton releases - code ".$releases->statusCode().", message - ".$releases->statusMessage());
            return $protons;
        }
        
        try {
            foreach (Json::decode($releases->body()) as $release) {
                $name = $filename = $url = null;
                
                foreach ($release['assets'] as $asset) {
                    $isGzip = Regex::match('^application/(gzip|x-gtar)$', $asset['content_type']);
                    $isArm = str::contains($asset['name'], "aarch64");
                    $sysIsArm = System::getProperty('os.arch') == "aarch64";
                    
                    if (!$isGzip || $isArm != $sysIsArm)
                        continue;
                        
                    $name = $release["tag_name"];
                    $filename = $asset["name"];
                    $url = $asset["browser_download_url"];
                    break;
                }
                
                if ($name == null || $filename == null || $url == null)
                    continue;
                    
                $protons[$name]["filename"] = $filename;
                $protons[$name]["url"] = $url;
            }
        } catch (Throwable $e) {}
        
        return $protons;
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
    
    static function ensureProtonLatestName() {
        if (!isset($GLOBALS["LatestProton"]) || $GLOBALS["LatestProton"] == "fetching")
            return self::findNewestAvailableProton();
            
        return str::sub($GLOBALS['LatestProton'], str::lastPos($GLOBALS['LatestProton'], '/') + 1, str::pos($GLOBALS['LatestProton'], '.tar'));
    }
    
    static function ensureProtonPath($proton) {
        if ($proton == "GE-Proton Latest") {
            $proton = self::ensureProtonLatestName();
            if ($proton == false)
                return false;
        }
        
        $protonsPath = launcherSettings::getBasePathFor('protons')."/$proton";
        if (!file_exists($protonsPath))
            return false;
        
        return $protonsPath;
    }

    static function getProtonExecutable($gameName = null, $exec = 'proton', $skipIfNotFound = false)
    {
        $proton = $gameName != null 
            ? app()->appModule()->games->get('proton', $gameName) 
            : app()->appModule()->launcher->get('defaultProton', 'User Settings') ?? 'GE-Proton Latest';
        
        $protonPath = self::ensureProtonPath($proton);
        $execPath = $exec == 'proton'
            ? "$protonPath/proton"
            : "$protonPath/files/bin/$exec";
    
        if (!fs::isFile($execPath)) {
            if (!$skipIfNotFound) {
                $mainForm = app()->form('MainForm');
                if ($mainForm->visible)
                    uiLater(function () use ($mainForm) { $mainForm->switchPlayButton('wait'); });
    
                uiLaterAndWait(function () use ($proton) {
                    app()->form('protonDownloader')->startDownload($proton, $GLOBALS['LatestProton']);
                    app()->showFormAndWait('protonDownloader');
                });
                
                return self::getProtonExecutable($gameName, $exec, true);
            }
    
            return false;
        }
    
        return fs::abs($execPath);
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
            $steam = execute('steam -silent');
        
            $logUsers = File::of(System::getProperty('user.home').'/.steam/steam/config/loginusers.vdf');
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
            UXDialog::showAndWait(sprintf(app()->module('Localization')->_('FILESWORKER.NOSUBMODULE'),$progs[$prog]));
            return;
        }
        
        $progF = File::of($progs[$prog]);
        $progF->setExecutable(true);
        
        return $progF->getAbsolutePath();
    }
}