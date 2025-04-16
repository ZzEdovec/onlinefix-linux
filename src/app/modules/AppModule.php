<?php
namespace app\modules;

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
        $this->settings->path = $userhome.'/.config/OFME-Linux/Launcher.ini';
        
        if ($GLOBALS['argv'][1] != null and fs::isFile($this->games->get('executable',$GLOBALS['argv'][1])))
        {
            app()->showForm('gameStarting');
            return;
        }
        
        try
        {
<<<<<<< Updated upstream
            if (fs::get('https://zzedovec.github.io/resources/ofmelauncher/currentversion') != '1.3')
=======
            if (fs::get('https://zzedovec.github.io/resources/ofmelauncher/currentversion') != '1.4')
>>>>>>> Stashed changes
            {
                new Process(['./jre/bin/java','-jar','ofmeupd.jar'])->start();
                app()->shutdown();
            }
        } catch (IOException $ex)
        {
            UXDialog::show('Failed to fetch updates - '.$ex->getMessage());
        }
        
        fs::ensureParent($this->games->path);
        new Process(['chmod','+x',fs::abs('./7zip/7z')])->start();

        if ($this->settings->get('inited') == null)
        {
            app()->showForm('initConfig');
            return;
        }
        
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
