<?php
namespace app\forms;

use std, gui, framework, app;


class log extends AbstractForm
{
    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {
        $this->textArea->text = null;
        $this->free();
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LOGFORM.HEADER');
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('LOGFORM.SUBHEADER');
    }

    /**
     * @event button3.construct 
     */
    function doButton3Construct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/save.png'));
        $e->sender->graphic->size = [20,20];
        
        $e->sender->text = Localization::getByCode('SAVE');
    }

    /**
     * @event button3.action 
     */
    function doButton3Action(UXEvent $e = null)
    {
        $documents = str::trim(execute('xdg-user-dir DOCUMENTS',true)->getInput()->readFully());
        $fileName = $this->data('gameName').' '.Time::now()->toString('yyyy-MM-dd HH:mm').'.log';
        
        fs::makeDir("$documents/OnlineFix Logs");
        file_put_contents("$documents/OnlineFix Logs/$fileName",$this->textArea->text);
        
        open("$documents/OnlineFix Logs");
    }

    /**
     * @event button.construct 
     */
    function doButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/telegram.png'));
        $e->sender->graphic->size = [20,20];
    }

    /**
     * @event button.action 
     */
    function doButtonAction(UXEvent $e = null)
    {
        execute('xdg-open https://t.me/queinucentral_chat');
    }

    /**
     * @event buttonAlt.construct 
     */
    function doButtonAltConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/save.png'));
        $e->sender->text = Localization::getByCode('MAINFORM.STOP');
    }

    /**
     * @event buttonAlt.action 
     */
    function doButtonAltAction(UXEvent $e = null)
    {
        $this->hide();
    }

    /**
     * @event keyUp-Esc 
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {    
        $this->hide();
    }



}
