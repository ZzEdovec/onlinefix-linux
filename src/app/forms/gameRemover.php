<?php
namespace app\forms;

use std, gui, framework, app;


class gameRemover extends AbstractForm
{

    /**
     * @event button.action 
     */
    function doButtonAction(UXEvent $e = null)
    {
        $gameName = app()->form('MainForm')->gamePanel->data('gameName');
        $desktopIcon = $this->appModule()->games->get('desktopIcon',$gameName);
        $appMenuIcon = $this->appModule()->games->get('appMenuIcon',$gameName);
        
        $prefixPath = fs::parent($this->appModule()->games->get('executable',$gameName)).'/OFME Prefix';
        if (fs::isDir($prefixPath))
        {
            new Process(['rm','-rf',$prefixPath])->startAndWait();
        }
        
        if ($this->checkbox->selected)
        {
            $gamePath = $this->appModule()->games->get('mainPath',$gameName) ?? fs::parent($this->appModule()->games->get('executable',$gameName));
            
            if (fs::isDir($gamePath))
                new Process(['rm','-rf',$gamePath])->startAndWait();
        }
        
        $this->removeLink($desktopIcon);
        $this->removeLink($appMenuIcon);
        
        $this->appModule()->games->removeSection($gameName);
        app()->form('MainForm')->gamePanel->data('opener')->free();
        
        if (app()->form('MainForm')->container->content->children->isEmpty())
        {
            $this->noGamesHeader->show();
        }
        
        app()->form('MainForm')->hideGameMenu();
        $this->free();
    }
    
    function removeLink($path)
    {
        if ($path != null and fs::isFile($path))
            fs::delete($path);
    }

    /**
     * @event button.construct 
     */
    function doButtonConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('DIALOG.YES');
    }

    /**
     * @event buttonAlt.action 
     */
    function doButtonAltAction(UXEvent $e = null)
    {
        $this->free();
    }

    /**
     * @event buttonAlt.construct 
     */
    function doButtonAltConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('DIALOG.NO');
    }

    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {    
        $this->free();
    }

    /**
     * @event checkbox.construct 
     */
    function doCheckboxConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMEREMOVER.DISKREMOVE');
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        $this->label->text = sprintf(Localization::getByCode('GAMEREMOVER.HEADER'),app()->form('MainForm')->gamePanel->data('gameName'));
    }


}