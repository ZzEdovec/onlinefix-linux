<?php
namespace app\forms;

use std, gui, framework, app;


class initConfig extends AbstractForm
{

    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {
        if (fs::isFile('/usr/bin/protontricks') == false or fs::isFile('/usr/bin/protontricks-launch') == false)
        {
            UXDialog::showAndWait(Localization::getByCode('INITCONFIG.NOPROTONTRICKS'),'ERROR',$this);
            app()->shutdown();
        }
        
        #if (fs::isFile('/usr/bin/gamemoderun'))
        #    $this->appModule()->settings->set('useGamemode',true);
        
        
        execute('xdg-open steam://install/480');
        
        new Thread(function ()
        {
            while (execute('killall SteamworksExample.exe',true)->getExitValue() == 1)
                sleep(3);
            
            
            uiLater(function ()
            {
                $this->label->text = Localization::getByCode('INITCONFIG.HEADER.WORKING');
                $this->textArea->text = null;
            });
            
            $tricks = new Process(['protontricks','480','-q','--force','vcrun2022'])->start();
            $tricks->getInput()->eachLine(function ($l)
            {
                uiLater(function () use ($l){$this->textArea->text .= $l."\n";});
            });
            
            $this->appModule()->settings->set('inited',true);
            uiLater(function ()
            {
                app()->showForm('MainForm');
                $this->free();    
            });
        })->start();
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('INITCONFIG.HEADER.DEFAULT');
    }

    /**
     * @event textArea.construct 
     */
    function doTextAreaConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('INITCONFIG.TEXTAREA');
    }

}
