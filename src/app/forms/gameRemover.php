<?php
namespace app\forms;

use Throwable;
use std, gui, framework, app;


class gameRemover extends AbstractForm
{

    /**
     * @event button.action 
     */
    function doButtonAction(UXEvent $e = null)
    {
        $gameName = app()->form('MainForm')->gamePanel->data('gameName');
        $desktopIcon = str::trim(execute('xdg-user-dir DESKTOP',true)->getInput()->readFully())."/$gameName.desktop";
        $appMenuIcon = System::getProperty('user.home')."/.local/share/applications/$gameName.desktop";
        $icon = $this->appModule()->games->get('icon',$gameName);
        $banner = $this->appModule()->games->get('banner',$gameName);
        
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
        
        $this->removeFile($desktopIcon);
        $this->removeFile($appMenuIcon);
        $this->removeFile($icon);
        $this->removeFile($banner);
        
        try
        {
            $this->appModule()->games->removeSection($gameName);
            app()->form('MainForm')->gamePanel->data('opener')->free();
        } catch (Throwable $ex){}
        
        if (app()->form('MainForm')->container->content->children->isEmpty())
        {
            $this->noGamesHeader->show();
        }
        
        app()->form('MainForm')->hideGameMenu();
        $this->free();
    }
    
    function removeFile($path)
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