<?php
namespace app\forms;

use std, gui, framework, app;


class log extends AbstractForm
{

    /**
     * @event button.action 
     */
    function doButtonAction(UXEvent $e = null)
    {    
        $documents = str::trim(execute('xdg-user-dir DOCUMENTS',true)->getInput()->readFully());
        fs::makeDir($documents.'/OnlineFix-Linux');
        file_put_contents($documents.'/OnlineFix-Linux/'.$this->data('gameName').'.log',$this->textArea->text);
        
        execute('xdg-open https://t.me/queinu');
        open($documents.'/OnlineFix-Linux');
        
        UXDialog::showAndWait('Отправьте лог-файл с названием игры, которую вы пытались запустить');
        
        $this->hide();
    }

    /**
     * @event buttonAlt.action 
     */
    function doButtonAltAction(UXEvent $e = null)
    {    
        $this->hide();
    }

    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {    
        $this->textArea->text = null;
    }

}
