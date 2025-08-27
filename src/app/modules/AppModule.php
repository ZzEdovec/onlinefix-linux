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
        if (System::getProperty('prism.forceGPU') != true) #MIGRATION FROM LEGACY, REMOVE IN V2.4
        {
            Logger::info('Setting forceGPU to true (migration from legacy launcher installation)');
            
            $desktops = [str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully()).'/OnlineFix Linux Launcher.desktop',
                         System::getProperty('user.home').'/.local/share/applications/OnlineFix Linux Launcher.desktop'];
            foreach ($desktops as $desktop)
            {
                $content = file_get_contents($desktop);
                if (fs::isFile($desktop) and str::contains($content,'-Dprism.forceGPU') == false)
                    file_put_contents($desktop,str::replace($content,'-jar','-Dprism.forceGPU=true -jar'));
            }
            
            if (fs::isFile('./jre/bin/java'))
            {
                new Process(array_merge([fs::abs('./jre/bin/java'),'-Dprism.forceGPU=true','-jar'],$GLOBALS['argv']))->start();
                System::halt(0);
            }
        }
        
        $userhome = System::getProperty('user.home');
        $this->games->path = $userhome.'/.config/OFME-Linux/Games.ini';
        fs::ensureParent($this->games->path);
        
        Logger::info('Loading UI');
        if ($GLOBALS['argv'][1] != null and fs::isFile($this->games->get('executable',$GLOBALS['argv'][1])))
        {
            app()->showForm('gameStarting');
            return;
        }
        
        $GLOBALS['LatestProton'] = 'fetching';
        new Thread(function (){
            $releases = filesWorker::fetchProtonReleases(); #Fetch latest proton 
            if ($releases != false and str::contains($releases,'tar.gz') == false)
            {
                foreach ($releases[0]['assets'] as $asset)
                {
                    if (Regex::match($asset['content_type'],'application/gzip|application/x-gtar') == false or 
                        $asset['state'] != 'uploaded' or 
                        $asset['browser_download_url'] == null)
                        continue;
                    
                    $GLOBALS['LatestProton'] = $asset['browser_download_url'];
                    break;
                }
            }
            elseif (str::contains($releases,'tar.gz'))
                $GLOBALS['LatestProton'] = $releases;
            else
            {
                unset($GLOBALS['LatestProton']);
                
                Logger::error('Failed to fetch latest proton version');
            }
                
            try #Check updates
            {
                if (fs::get('https://zzedovec.github.io/resources/ofmelauncher/currentversion') != '2.2.3')
                {
                    new Process(['./jre/bin/java','-jar','ofmeupd.jar'])->start();
                    
                    app()->shutdown();
                    return;
                }
            } catch (IOException $ex)
            {
                Logger::error('Failed to fetch latest launcher version - '.$ex->getMessage());
            }
            
            Logger::info('Latest versions fetch thread completed. Latest Proton - '.$GLOBALS['LatestProton']);
        })->start();
        
        app()->showForm('MainForm');
        Logger::info('Initialization complete. OFME Linux Launcher '.app()->form('about')->version->text);
    }
}
