<?php
namespace app\forms;

use std, gui, framework, app;


class gameStarting extends AbstractForm
{

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        $process = filesWorker::generateProcess($GLOBALS['argv'][1]) ?? app()->shutdown();
        
        waitAsync('5s',function (){$this->hide();});
        
        new Thread(function () use ($process)
        {
            filesWorker::run($process,$GLOBALS['argv'][1]);
        })->start();
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('GAMESTARTER.STARTING');
    }

}
