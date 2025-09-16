<?php
namespace app\forms;

use php\gui\controlsfx\UXPopOver;
use std, gui, framework, app;


class envViewer extends AbstractForm
{
    
    /**
     * @env UXPopOver
     */
    $editorPopOver;
    
    /**
     * @event envTable.construct 
     */
    function doEnvTableConstruct(UXEvent $e = null)
    {
        foreach ($e->sender->columns->toArray() as $column) #Localization load
            $column->text = Localization::getByCode($column->text);
        
        $placeholder = new UXLabel(Localization::getByCode('ENVVIEWER.NOENVS'));
        $placeholder->font = UXFont::of('System',13,'BOLD');
        $placeholder->textColor = 'White';
        $e->sender->placeholder = $placeholder;
           
        $menu = new UXContextMenu;
        $remove = new UXMenuItem(Localization::getByCode('REMOVE'));
        
        $remove->on('action',function ()
        {
            $this->envTable->items->removeByIndex($this->envTable->selectedIndex);
            $this->saveButton->show();
        });
        
        $menu->items->add($remove);
        $e->sender->data('menu',$menu);
    }

    /**
     * @event envTable.click-2x 
     */
    function doEnvTableClick2x(UXMouseEvent $e = null)
    {    
        if ($this->envTable->selectedItem == null)
            return;
        
        $this->showEditor($this->envTable->selectedItem,$e->x,$e->y);
    }

    /**
     * @event addButton.construct 
     */
    function doAddButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/add.png'));
        $e->sender->graphic->size = [20,20];
    }

    /**
     * @event addButton.action 
     */
    function doAddButtonAction(UXEvent $e = null)
    {
        $this->showEditor();
    }

    /**
     * @event saveButton.construct 
     */
    function doSaveButtonConstruct(UXEvent $e = null)
    {
        $e->sender->graphic = new UXImageArea(new UXImage('res://.data/img/save.png'));
        $e->sender->graphic->size = [20,20];
    }

    /**
     * @event saveButton.action 
     */
    function doSaveButtonAction(UXEvent $e = null)
    {
        foreach ($this->envTable->items->toArray() as $env)
        {
            if ($strEnv != null)
                $strEnv .= '\\\\\\\\';
            
            $strEnv .= $env['variable'].'===='.$env['value'];
        }
        
        $this->appModule()->games->set('environment',$strEnv,str::replace($this->title,' environment',null));
        $this->hide();
    }

    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {    
        $popover = new UXPopOver;
        $fragment = new UXFragmentPane;
        $fragment->size = $popover->size = [272,192];
        
        app()->form('envEditor')->showInFragment($fragment);
        
        $popover->contentNode = $fragment;
        $popover->cornerRadius = 15;
        $popover->detachable = false;
        
        $this->editorPopOver = app()->form('envEditor')->popOver = $popover;
    }

    /**
     * @event hide 
     */
    function doHide(UXWindowEvent $e = null)
    {    
        $this->saveButton->hide();
        $this->envTable->items->clear();
    }

    /**
     * @event envTable.click-Right 
     */
    function doEnvTableClickRight(UXMouseEvent $e = null)
    {    
        if ($e->sender->selectedItem != null)
            $e->sender->data('menu')->showByNode($e->sender,$e->x,$e->y);
    }
    
    static function parseEnvironmentArray($game)
    {
        $environment = app()->appModule()->games->get('environment',$game);
        if (str::contains($environment,'\\\\\\\\') == false)
        {
            $parsedEnv = str::split($environment,'====');
            return [$parsedEnv[0] => $parsedEnv[1]];
        }
        else
        {
            foreach (str::split($environment,'\\\\\\\\') as $env)
            {
                $env = str::split($env,'====');
                $parsedEnv[$env[0]] = $env[1];
            }
            
            return $parsedEnv;
        }
    }
    
    function loadByGame($game)
    {
        $environment = self::parseEnvironmentArray($game);
        foreach ($environment as $env => $val)
            $this->envTable->items->add(['variable'=>$env,'value'=>$val]);
            
        $this->envTable->data('originalValues',$this->envTable->items->toArray());
    }
    
    function showEditor($item = null,$x = null,$y = null)
    {
        $envEditor = app()->form('envEditor');
        
        if ($item != null)
        {
            $envEditor->env->text = $item['variable'];
            $envEditor->value->text = $item['value'];
            
            $this->editorPopOver->arrowLocation = 'TOP_CENTER';
            $this->editorPopOver->showByNode($this->envTable,$x - $this->editorPopOver->width / 2,$y);
        }
        else 
        {
            $envEditor->env->text = $envEditor->value->text = null;
            
            $this->editorPopOver->arrowLocation = 'BOTTOM_CENTER';
            $this->editorPopOver->showByNode($this->addButton,
                                             ($this->addButton->width / 2) - ($this->editorPopOver->width / 2),
                                             -($this->editorPopOver->height - 8));
        }
    }
}
