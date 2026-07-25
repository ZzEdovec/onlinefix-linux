<?php
namespace app\modules;

use httpclient;
use facade\Async;
use Throwable;
use php\io\IOException;
use std, gui, framework, app;


class AppModule extends AbstractModule
{

    function fetchLatestProton()
    {
        $GLOBALS['LatestProton'] = 'fetching';
        $releases = FilesWorker::fetchProtonReleases();
        if (arr::count($releases) > 0)
        {
            $latest = reset($releases);
            $GLOBALS['LatestProton'] = $latest["url"];
        }
        else
        {
            unset($GLOBALS['LatestProton']);
            
            Logger::error('Failed to fetch latest proton version');
            return;
        }
        
        Logger::info('Fetched latest GE-Proton download URL! '.$GLOBALS['LatestProton']);
    }
    
    function checkUpdates()
    {
        if (fs::isFile('ofmeupd.jar') == false)
            return;
            
        $client = new HttpClient;
        $client->connectTimeout = $client->readTimeout = 5000;
        
        $latestVersion = $client->get('https://zzedovec.github.io/resources/ofmelauncher/currentversion');
        if ($latestVersion->isSuccess() and $latestVersion->body() != $GLOBALS['version'])
        {
            new Process(['./jre/bin/java','-jar','ofmeupd.jar'])->start();
            
            app()->shutdown();
            return;
        }
        elseif ($latestVersion->isFail())
            Logger::error('Failed to fetch latest launcher version - '.$latestVersion->statusCode().' '.$latestVersion->statusMessage());
    }
    
    /**
     * @event action 
     */
    function doAction(ScriptEvent $e = null)
    {
        $GLOBALS['version'] = '2.7.1';
        
        $userhome = System::getProperty('user.home');
        $this->games->path = "$userhome/.config/OFME-Linux/Games.ini";
        $this->launcher->path = "$userhome/.config/OFME-Linux/Launcher.ini";
        fs::ensureParent($this->games->path);
        
        Logger::info('Loading UI');
        if ($GLOBALS['argv'][1] != null and fs::isFile($this->games->get('executable',$GLOBALS['argv'][1])))
        {
            Logger::info('Game for load detected. Running in minimal mode');
            
            if ($this->games->get('proton',$GLOBALS['argv'][1]) == 'GE-Proton Latest')
                $this->fetchLatestProton();
            
            app()->showForm('gameStarting');
            return;
        }
        
        Async::parallel([[$this,'fetchLatestProton'],[$this,'checkUpdates']]);
        
        $pid = file_get_contents('/tmp/ofllpid');
        if ($pid != null and fs::isDir("/proc/$pid"))
        {
            UXDialog::showAndWait(sprintf(app()->module('Localization')->_('APPMODULE.PIDEXISTS'),$pid),'ERROR');
            
            app()->shutdown();
            return;
        }
        else
        {
            try {file_put_contents('/tmp/ofllpid',App::pid());}
            catch (Throwable $ex) {Logger::warn('Failed to write PID to /tmp/ofllpid - '.$ex->getMessage());}
        }
        
        if (System::getProperty('prism.forceGPU') == false)
            Logger::warn('UI GPU acceleration disabled, so some effects will be disabled');

        app()->showForm('MainForm');
        
        Logger::info('Initialization complete. OnlineFix Linux Launcher '.$GLOBALS['version']);
    }
}
