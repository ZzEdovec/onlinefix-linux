<?php
namespace app\forms;

use Throwable;
use std, gui, framework, app;


class bannerEditor extends AbstractForm
{

    /**
     * @event button.action 
     */
    function doButtonAction(UXEvent $e = null)
    {    
        if ($this->edit->text == null)
        {
            $this->toast(Localization::getByCode('BANNEREDITOR.STEAM.NOAPPID'));
            return;
        }
        
        try 
        {
            $banner = FixParser::parseBanner($this->edit->text);
            $this->data('banner',UXImage::ofUrl($banner));
            
            $this->hide();
        } catch (Throwable $ex){$this->toast(Localization::getByCode('MAINFORM.STEAM.FAILED'));}
    }

    /**
     * @event buttonAlt.action 
     */
    function doButtonAltAction(UXEvent $e = null)
    {    
        $fc = new UXFileChooser;
        
        $fc->extensionFilters = [['extensions'=>['*.jpg','*.png'],'description'=>Localization::getByCode('FILECHOOSER.IMG.DESC')]];
        $fc->title = Localization::getByCode('FILECHOOSER.IMG.TITLE');
        
        $img = $fc->showOpenDialog($this);
        if ($img == null)
            return;
            
        $this->data('banner',new UXImage($img));
        
        $this->hide();
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('BANNEREDITOR.STEAM.HEADER');
    }

    /**
     * @event edit.construct 
     */
    function doEditConstruct(UXEvent $e = null)
    {    
        $e->sender->promptText = Localization::getByCode('BANNEREDITOR.STEAM.APPID');
    }

    /**
     * @event button.construct 
     */
    function doButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('BANNEREDITOR.STEAM.GET');
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('BANNEREDITOR.FILE.HEADER');
    }

    /**
     * @event buttonAlt.construct 
     */
    function doButtonAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('BANNEREDITOR.FILE.CHOOSE');
    }

}
