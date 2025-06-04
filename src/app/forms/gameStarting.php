<?php
namespace app\forms;

use php\gui\animatefx\AnimationFX;
use std, gui, framework, app;


class gameStarting extends AbstractForm
{

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {
        $GLOBALS['argv'][1] = 'Gang Beasts';
        $process = filesWorker::generateProcess($GLOBALS['argv'][1]) ?? app()->shutdown();
        waitAsync('5s',function (){$this->hide();});
        new Thread(function () use ($process){filesWorker::run($process,$GLOBALS['argv'][1]);})->start();
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {
        $e->sender->text = sprintf(Localization::getByCode('GAMESTARTER.STARTING'),$GLOBALS['argv'][1]);
    }

    /**
     * @event background.construct 
     */
    function doBackgroundConstruct(UXEvent $e = null)
    {    
        $clip = new UXRectangle;
        $clip->size = $this->background->size;
        $clip->arcHeight = $clip->arcWidth = 25;
        
        $this->background->clip = $clip;
        $this->background->image = new UXImage($this->appModule()->games->get('banner',$GLOBALS['argv'][1]) ?? 'res://.data/img/noBanner.png');
    }


}
