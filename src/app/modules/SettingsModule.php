<?php
namespace app\modules;

use std, gui, framework, app;


class SettingsModule extends AbstractModule
{
    $activePage;
    
    function switchPage($newPage)
    {   
        $oldPage = $this->activePage;
        $oldPageButtonID = $oldPage->id.'Button';
        
        if ($oldPage == $newPage)
        {
            $this->$oldPageButtonID->selected = true;
            return;
        }
        
        $this->$oldPageButtonID->selected = false;
        $this->activePage = $newPage;
        
        Animation::fadeOut($oldPage,350,function () use ($newPage,$oldPage)
        {
            $oldPage->hide();
            
            $newPage->show();
            Animation::fadeIn($newPage,350);
        });
    }

    static function setWithDirChooser($param,$sender = null,$for = ['launcher'])
    {
        $dc = new UXDirectoryChooser;
        
        $gameDir = $dc->showDialog($this);
        if ($gameDir == null)
            return;
          
        if ($sender != null)  
            $sender->text = $gameDir;
        
        if ($for[0] == 'launcher')
            app()->appModule()->launcher->set($param,$gameDir,'User Settings');
        else 
            app()->appModule()->games->set($param,$gameDir,$for[1]);
            
        return true;
    }
}