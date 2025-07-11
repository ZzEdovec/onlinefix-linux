<?php
namespace app\forms;

use php\gui\animatefx\AnimationFX;
use std, gui, framework, app;


class about extends AbstractForm
{

    /**
     * @event github.construct 
     */
    function doGithubConstruct(UXEvent $e = null)
    {
        $view = new UXImageArea(new UXImage('res://.data/img/github.png'));
        $view->size = [20,20];
        
        quUI::generateSetButton($e->sender,'GitHub',$view);
    }

    /**
     * @event github.action 
     */
    function doGithubAction(UXEvent $e = null)
    {
        execute('xdg-open https://github.com/ZzEdovec/onlinefix-linux');
    }

    /**
     * @event telegram.construct 
     */
    function doTelegramConstruct(UXEvent $e = null)
    {
        $view = new UXImageArea(new UXImage('res://.data/img/telegram.png'));
        $view->size = [20,20];
        
        quUI::generateSetButton($e->sender,'Telegram',$view);
    }

    /**
     * @event telegram.action 
     */
    function doTelegramAction(UXEvent $e = null)
    {
        execute('xdg-open https://t.me/queinucentral');
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        AnimationFX::play('FadeInUp',$this->vbox);
    }

}
