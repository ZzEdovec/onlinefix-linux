<?php
namespace app\forms;

use php\gui\controlsfx\UXPopOver;
use std, gui, framework, app;


class envEditor extends AbstractForm
{
    /**
     * @var UXPopOver
     */
    $popOver;

    /**
     * @event saveButton.action 
     */
    function doSaveButtonAction(UXEvent $e = null)
    {    
        if ($this->env->text == null)
        {
            UXDialog::show($this->_('ENVEDITOR.NOVARIABLE'),'ERROR');
            return;
        }
        
        if ($this->env->text == 'LD_PRELOAD' and app()->form('gameSettings')->steamOverlay->data('quUIElement')->selected == true)
        {
            UXDialog::show($this->_('ENVEDITOR.STEAMOVERLAY'),'ERROR');
            return;
        }
        elseif (envEditor::isBlacklistedEnv($this->env->text))
        {
            UXDialog::show($this->_('ENVEDITOR.BLACKLISTED'),'ERROR');
            return;
        }
        
        $envViewer = app()->form('envViewer');
        foreach ($envViewer->envTable->items->toArray() as $pos => $env)
        {
            if ($env['variable'] == $this->env->text)
            {
                $index = $pos;
                break;
            }
        }
        
        $content = ['variable'=>$this->env->text,'value'=>$this->value->text];
        if (isset($index))
        {
            $envViewer->envTable->items->removeByIndex($index);
            $envViewer->envTable->items->insert($index,$content);
        }
        else
            $envViewer->envTable->items->add($content);
        
        $envViewer->envTable->data('originalValues') != $envViewer->envTable->items->toArray() ? $envViewer->saveButton->show() : $envViewer->saveButton->hide();
        $this->popOver->hide();
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('ENVEDITOR.VARIABLE');
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('ENVEDITOR.VALUE');
    }

    /**
     * @event saveButton.construct 
     */
    function doSaveButtonConstruct(UXEvent $e = null)
    {    
        $e->sender->text = $this->_('SAVE');
    }

    /**
     * @event env.keyUp-Enter 
     */
    function doEnvKeyUpEnter(UXKeyEvent $e = null)
    {    
        $this->doSaveButtonAction();
    }

    /**
     * @event value.keyUp-Enter 
     */
    function doValueKeyUpEnter(UXKeyEvent $e = null)
    {    
        $this->doSaveButtonAction();
    }

    /**
     * @event env.keyUp-Ctrl+V 
     */
    function doEnvKeyUpCtrlV(UXKeyEvent $e = null)
    {
        if (str::contains($e->sender->text,'='))
        {
            $splitted = str::split($e->sender->text,'=');
            
            $e->sender->text = $splitted[0];
            $this->value->text = $splitted[1];
            
            $this->value->requestFocus();
            $this->value->positionCaret(str::length($this->value->text));
        }
    }

    /**
     * @event env.keyUp 
     */
    function doEnvKeyUp(UXKeyEvent $e = null)
    {
        if ($e->codeName == 'Equals')
        {
            $e->sender->text = str::replace($e->sender->text,'=',null);
            $this->value->requestFocus();
        }
    }
    
    static function isBlacklistedEnv($env)
    {
        $blacklist = ['WINEDLLOVERRIDES','PROTON_ENABLE_WAYLAND','PROTON_USE_WINED3D'];
        
        if (in_array($env,$blacklist))
            return true;
    }
}
