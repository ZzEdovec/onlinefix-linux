<?php
namespace app\forms;

use std, gui, framework, app;


class gameSettings extends AbstractForm
{

    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {
        if (fs::isFile('/usr/bin/gamemoderun') == false)
            $this->gamemode->enabled = false;
        if (fs::isFile('/usr/bin/mangohud') == false)
           $this->mangohud->enabled = false;
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        $this->gamemode->selected = $this->appModule()->games->get('gamemode',$this->gameName->text);
        $this->mangohud->selected = $this->appModule()->games->get('mangohud',$this->gameName->text);
        $this->fakeSteam->selected = $this->appModule()->games->get('fakeSteam',$this->gameName->text);
        $this->overrides->text = $this->appModule()->games->get('overrides',$this->gameName->text);
        $this->env->text = $this->appModule()->games->get('environment',$this->gameName->text);
    }

    /**
     * @event gamemode.click 
     */
    function doGamemodeClick(UXMouseEvent $e = null)
    {
        UXDialog::show('Некоторые фиксы перестают работать из-за Gamemode - выключите его, если игра не работает','WARNING');
        
        $this->appModule()->games->set('gamemode',$e->sender->selected,$this->gameName->text);
    }

    /**
     * @event mangohud.click 
     */
    function doMangohudClick(UXMouseEvent $e = null)
    {    
        $this->appModule()->games->set('mangohud',$e->sender->selected,$this->gameName->text);
    }

    /**
     * @event fakeSteam.click 
     */
    function doFakeSteamClick(UXMouseEvent $e = null)
    {    
        $this->appModule()->games->set('fakeSteam',$e->sender->selected,$this->gameName->text);
    }

    /**
     * @event overrides.keyUp 
     */
    function doOverridesKeyUp(UXKeyEvent $e = null)
    {    
        $this->appModule()->games->set('overrides',$e->sender->text,$this->gameName->text);
    }

    /**
     * @event env.keyUp 
     */
    function doEnvKeyUp(UXKeyEvent $e = null)
    {    
        $this->appModule()->games->set('environment',$e->sender->text,$this->gameName->text);
    }

    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {    
        $this->gameName->graphic->free();
    }

    /**
     * @event panel.construct 
     */
    function doPanelConstruct(UXEvent $e = null)
    {    
        $e->sender->title = Localization::getByCode('GAMESETTINGS.ADDITIONALS');
    }

    /**
     * @event gamemode.construct 
     */
    function doGamemodeConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ADDITIONALS.USEGAMEMODE');
    }

    /**
     * @event mangohud.construct 
     */
    function doMangohudConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ADDITIONALS.USEMANGOHUD');
    }

    /**
     * @event fakeSteam.construct 
     */
    function doFakeSteamConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ADDITIONALS.USEFAKESTEAM');
    }

    /**
     * @event panelAlt.construct 
     */
    function doPanelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->title = Localization::getByCode('GAMESETTINGS.ENVS');
    }

    /**
     * @event envTitle.construct 
     */
    function doEnvTitleConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESETTINGS.ENVS.ENVIRONMENT');
    }

}
