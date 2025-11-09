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
        
        $prefixPath = $this->appModule()->games->get('prefixPath',$gameName) ?? fs::parent($this->appModule()->games->get('executable',$gameName)).'/OFME Prefix';
        if ($this->removePrefix->selected)
            new Process(['rm','-rf',$prefixPath])->startAndWait();
        
        if ($this->removeFiles->selected)
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
            $this->noGamesHeader->show();
        
        app()->form('MainForm')->hideGameMenu();
        $this->hide();
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
        $this->hide();
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
     * @event removeFiles.construct 
     */
    function doRemoveFilesConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMEREMOVER.DISKREMOVE');
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {   
        $gameName = app()->form('MainForm')->gamePanel->data('gameName');
        $this->label->text = sprintf(Localization::getByCode('GAMEREMOVER.HEADER'),$gameName);
        
        $mainPath = $this->appModule()->games->get('mainPath',$gameName);
        $legacyPrefixPath = fs::parent($this->appModule()->games->get('executable',$gameName)).'/OFME Prefix';
        $prefixPath = $this->appModule()->games->get('prefixPath',$gameName);
        if (fs::isDir($prefixPath) == false and fs::isDir($legacyPrefixPath) == false)
            $this->removePrefix->enabled = $this->removePrefix->selected = false;
        elseif (fs::isDir($legacyPrefixPath) or str::contains($prefixPath,$mainPath))
        {
            $this->removePrefix->enabled = false;
            $this->removePrefix->selected = true;
        }
        else 
            $this->removePrefix->enabled = $this->removePrefix->selected = true;
            
        $this->removeFiles->enabled = fs::isDir($mainPath);
    }

    /**
     * @event removePrefix.construct 
     */
    function doRemovePrefixConstruct(UXEvent $e = null)
    {
        $e->sender->text = Localization::getByCode('GAMEREMOVER.PREFIXREMOVE');
    }

    /**
     * @event keyUp-Esc 
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {    
        $this->hide();
    }


}
