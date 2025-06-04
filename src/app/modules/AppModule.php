<?php
namespace app\modules;

use Throwable;
use php\io\IOException;
use std, gui, framework, app;


class AppModule extends AbstractModule
{

    /**
     * @event action 
     */
    function doAction(ScriptEvent $e = null)
    {
        $userhome = System::getProperty('user.home');
        $this->games->path = $userhome.'/.config/OFME-Linux/Games.ini';
        fs::ensureParent($this->games->path);
        
        $releases = filesWorker::fetchProtonReleases();
        if ($releases != false and str::contains($releases,'tar.gz') == false)
        {
            foreach ($releases[0]['assets'] as $asset)
            {
                if ($asset['content_type'] != 'application/gzip' or $asset['state'] != 'uploaded' or $asset['browser_download_url'] == null)
                    continue;
                
                $GLOBALS['LatestProton'] = $asset['browser_download_url'];
                break;
            }
        }
        elseif (str::contains($releases,'tar.gz'))
            $GLOBALS['LatestProton'] = $releases;
        
        if ($GLOBALS['argv'][1] != null and fs::isFile($this->games->get('executable',$GLOBALS['argv'][1])))
        {
            app()->showForm('gameStarting');
            return;
        }
        
        new Thread(function (){
            try
            {
                if (fs::get('https://zzedovec.github.io/resources/ofmelauncher/currentversion') != '2')
                {
                    new Process(['./jre/bin/java','-jar','ofmeupd.jar'])->start();
                    app()->shutdown();
                }
            } catch (IOException $ex){}
        })->start();
                                 
        app()->showForm('MainForm');
    }

    /**
     * @event overlayEmulator.action 
     */
    function doOverlayEmulatorAction(ScriptEvent $e = null)
    {    
        execute('steam steam://open/friends');
    }







}
