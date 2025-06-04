<?php
namespace quUI;

use php\gui\animatefx\AnimationFX;
use php\gui\controlsfx\UXPopOver;
use gui;

class quUI 
{
    static function generateSetButton(UXButton $button,string $text,UXNode $element)
    {
        $hbox = new UXHBox;
        $label = new UXLabel($text);
        $label->font = UXFont::of('System',12);
        $label->textColor = 'White';
        
        $hbox->size = $label->size = $button->size;
        $hbox->paddingLeft = $hbox->paddingRight = 8;
        $hbox->alignment = 'CENTER_LEFT';
        
        $hbox->children->addAll([$label,$element]);
        
        $button->data('quUIElement',$element);
        $button->graphic = $hbox;
        
        $button->on('click',function () use ($element){$element->selected = !$element->selected;});
    }
    
    static function generateContextMenu(UXButton $button, string $buttonText, array $elements, string $defaultText = null, callable $callback = null)
    {
        $listView = new UXListView;
        $listView->size = [$button->width,100];
        $listView->fixedCellSize = 30;
        $listView->items->addAll($elements);
        
        $buttonText = new UXLabel($buttonText);
        $buttonText->font = UXFont::of('Inter',13,'BOLD');
        $buttonText->textColor = 'White';
        
        $selectedText = new UXLabel($defaultText);
        $selectedText->font = UXFont::of('Inter',12);
        $selectedText->textColor = '#cccccc';
        $selectedText->width = $button->width - 15;
        
        $expandImg = new UXImageArea(new UXImage('res://.data/img/down.png'));
        $expandImg->size = [15,15];
        $expandImg->centered = $expandImg->proportional = $expandImg->stretch = true;
        
        $label = new UXVbox([$buttonText,$selectedText]);
        $label->spacing = 0;
        $label->width = $button->width;
        $label->alignment = 'CENTER_LEFT';
        
        $line = new UXHBox([$label,$expandImg]);
        $line->spacing = 7;
        $line->paddingLeft = $line->paddingRight = 5;
        $line->alignment = 'CENTER_LEFT';
        
        $popup = new UXPopOver;
        $popup->arrowLocation = 'TOP_CENTER';
        $popup->contentNode = $listView;
        
        $listView->on('click',function () use ($listView, $popup, $selectedText, $callback, $button)
        {
            if ($listView->selectedIndex != -1)
            {
                $button->data('selected',$listView->selectedItem);
                
                $popup->hideWithFade(100);
                $selectedText->text = $listView->selectedItem;
                
                if ($callback)
                    $callback();
            }
        });
        
        
        $button->graphic = $line;
        $button->on('click',function () use ($popup,$button)
        {
            $popup->showByNode($button,0,$button->height);
        });
    }
    
    static function animateWithoutConflict($animation,$node,$speed,$callback = null)
    {
        if ($node->data('quUIAnimation') != null)
        {
            $node->data('quUIAnimation')->stop();
        }
            
        $animation = new AnimationFX($animation,$node);
        $animation->setOnFinished(function () use ($node,$callback)
        {
            $node->data('quUIAnimation',null);
            
            if (is_callable($callback) and $node->opacity == 0 or $node->opacity == 1)
                $callback();
        });
        
        $node->data('quUIAnimation',$animation);
        $animation->cycleCount = 1;
        $animation->speed = $speed;
        $animation->start();
    }
}