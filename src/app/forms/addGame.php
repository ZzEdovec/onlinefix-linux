<?php
namespace app\forms;

use httpclient;
use facade\Json;
use Exception;
use Throwable;
use std, gui, framework, app;

class addGame extends AbstractForm
{
    /**
     * @var AriaDownloader
    */
    $ariaDownloader;
    
    /**
     * @event construct 
     */
    function doConstruct(UXEvent $e = null)
    {    
        $this->ariaDownloader = new AriaDownloader($this->appModule()->launcher->get('downloadsPath','User Settings') ?? 
                                                   str::trim(execute('xdg-user-dir DOWNLOAD',true)->getInput()->readFully()),
                                                   $this->appModule()->launcher->get('ariaPort','Downloads'));
    }
    
    /**
     * @event addGame.construct 
     */
    function doAddGameConstruct(UXEvent $e = null)
    {
        app()->form('MainForm')->doAddGameConstruct($e);
    }
    
    function switchGameButton($status)
    {
        if ($status == 'add')
        {
            $this->addGame->graphic = $this->addGame->data('add');
            $this->addGame->text = Localization::getByCode('MAINFORM.ADDGAME');
        }
        else 
        {
            $this->addGame->graphic = $this->addGame->data('loading');
            $this->addGame->text = Localization::getByCode('MAINFORM.LOADINGGAME');
        }
    }

    /**
     * @event addGame.action 
     */
    function doAddGameAction(UXEvent $e = null)
    {
        if ($this->addGame->graphic == $this->addGame->data('loading') and $this->addGame->graphic != null)
            return;
            
        $dc = new UXDirectoryChooser;
        $dc->title = Localization::getByCode('ADDGAME.FILECHOOSER');

        $path = $dc->showDialog($this->visible ? $this : app()->form('MainForm'));
        if ($path == null)
            return;
        
        try{$this->switchGameButton('loading');} catch (Throwable $ex){}
        new Thread(function () use ($path)
        {
            $files = addGame::scanDir($path);
                                     
            uiLater(function () use ($files,$path)
            {
                try{$this->switchGameButton('add','addGame');} catch (Throwable $ex){}
                
                if ($files == null or $files == [])
                {
                    UXDialog::show(Localization::getByCode('ADDGAME.EMPTY'),'ERROR');
                    return;
                }
                
                $form = quUI::showFormAndFocus('newGameConfigurator',true);
                uiLater(function () use ($files,$path,$form)
                {
                    $form->prepareForGame($files,$path);
                    $this->hide();
                });
            });
        })->start();
    }

    /**
     * @event container.construct 
     */
    function doContainerConstruct(UXEvent $e = null)
    {
        $vbox = new UXVbox;
        $vbox->spacing = 5;
        
        $e->sender->content = $vbox;
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {
        $dnSource = $this->appModule()->launcher->get('downloadsSource','User Settings');
        if ($dnSource == null)
        {
            $this->gamesBox->hide();
            $this->errorBox->show();
            
            $this->errorLabel->text = Localization::getByCode('ADDGAME.SOURCE.NO');
            $this->errorSubLabel->text = Localization::getByCode('ADDGAME.SOURCE.ADD');
            $this->sourceLabel->text = Localization::getByCode('ADDGAME.SOURCE.SELECT');
            
            return;
        }
        elseif ($this->errorBox->visible)
        {
            $this->errorBox->hide();
            $this->gamesBox->show();
            
            $this->loadGames();
        }
        elseif ($this->container->content->children->isEmpty()){$this->loadGames();}
        
        $this->sourceLabel->text = sprintf(Localization::getByCode('ADDGAME.SOURCE'),$dnSource);
    }
    
    function loadGames()
    {
        $source = $this->appModule()->launcher->get('downloadsSource','User Settings');
        $client = new HttpClient;
        $client->connectTimeout = 3000;
        $client->responseType = 'JSON';
        $this->loadingOverlay->show();
        
        $client->getAsync($source,null,function (HttpResponse $response) use ($source)
        {
            try 
            {
                if ($response->isError())
                    throw new Exception;
                
                $this->container->data('games',$response->body()['downloads']);
                $this->addGamesWithJSONOffset(0);
            } 
            catch (Throwable $ex)
            {
                $this->gamesBox->hide();
                $this->errorBox->show();
                
                $this->errorLabel->text = Localization::getByCode('ADDGAME.SOURCE.GETFAIL');
                $this->errorSubLabel->text = Localization::getByCode('ADDGAME.SOURCE.HINT');
                
                Logger::error('Failed to fetch downloads - '.$ex->getMessage());
            }
            
            $this->loadingOverlay->hide();
            $this->gamesBox->show();
        });
    }

    /**
     * @event container.scroll-Down 
     */
    function doContainerScrollDown(UXScrollEvent $e = null)
    {
        if ($this->search->text != null) return;
        
        $seconds = Time::seconds();
        if ($e->sender->scrollY == $e->sender->scrollMaxY and $e->sender->data('prevScrollEvent') < $seconds)
        {
            $e->sender->data('prevScrollEvent',$seconds + 2);
            $this->addGamesWithJSONOffset($e->sender->content->children->count());
        }
    }


    /**
     * @event search.keyUp 
     */
    function doSearchKeyUp(UXKeyEvent $e = null)
    {
        if ($e->sender->data('timer') != null)
            $e->sender->data('timer')->cancel();
        if ($e->sender->text == null)
        {
            if ($e->sender->data('timer') != null)
                $e->sender->data('timer',null);
            
            $this->noFoundOverlay->hide();
            $this->addGamesWithJSONOffset(0);
            return;
        }
        
        $e->sender->data('timer',Timer::after('1s',function () use ($e)
        {
            $e->sender->data('timer',null);
            uiLaterAndWait(function (){$this->clearGames();});
            
            foreach ($this->container->data('games') as $game)
            {
                if (str::contains(str::lower($game['title']),str::lower($e->sender->text)))
                    uiLaterAndWait(function () use ($game){$this->addGame($game);});
            }
            
            if ($this->container->content->children->isEmpty()) {uiLater(function (){$this->noFoundOverlay->show();});}
            else {uiLater(function (){$this->noFoundOverlay->hide();});}
        }));
    }

    /**
     * @event sourceLabel.click 
     */
    function doSourceLabelClick(UXMouseEvent $e = null)
    {
        $newSource = UXDialog::input(Localization::getByCode('ADDGAME.SOURCE.NEW'),$this->appModule()->launcher->get('downloadsSource','User Settings'));
        if ($newSource == null) return;
        
        try 
        {
            if (Json::decode(fs::get($newSource))['name'] == 'onlinefix')
            {
                $this->appModule()->launcher->set('downloadsSource',$newSource,'User Settings');
                $this->sourceLabel->text = sprintf(Localization::getByCode('ADDGAME.SOURCE'),$newSource);
                
                $this->doShow();
            }
            else 
                UXDialog::show(Localization::getByCode('ADDGAME.FAIL'));
        } catch (Throwable $ex){UXDialog::show($ex->getMessage(),'ERROR');}
    }

    /**
     * @event errorLabel.construct 
     */
    function doErrorLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.SOURCE.GETFAIL');
    }

    /**
     * @event errorSubLabel.construct 
     */
    function doErrorSubLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.SOURCE.HINT');
    }

    /**
     * @event label.construct 
     */
    function doLabelConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.HEADER');
    }

    /**
     * @event labelAlt.construct 
     */
    function doLabelAltConstruct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.SUBHEADER');
    }

    /**
     * @event label7.construct 
     */
    function doLabel7Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.LOADING');
    }

    /**
     * @event search.construct 
     */
    function doSearchConstruct(UXEvent $e = null)
    {    
        $e->sender->promptText = Localization::getByCode('SEARCH');
    }

    /**
     * @event label3.construct 
     */
    function doLabel3Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.XBOXWARN');
    }

    /**
     * @event label4.construct 
     */
    function doLabel4Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.SEARCH.NOTFOUND');
    }

    /**
     * @event label5.construct 
     */
    function doLabel5Construct(UXEvent $e = null)
    {    
        $e->sender->text = Localization::getByCode('ADDGAME.SEARCH.NOTFOUND.SUB');
    }





    function addGamesWithJSONOffset($offset)
    {
        if ($offset == 0 and $this->container->content->children->isEmpty() == false)
            uiLater(function (){$this->clearGames();});
        
        foreach ($this->container->data('games') as $num => $game)
        {
            if ($num < $offset)
                continue;
            elseif ($num >= $offset + 20)
                break;
            
            uiLater(function () use ($game){$this->addGame($game);});
        }
    }
    
    function addGame($game)
    {
        $panel = $this->instance('prototypes.gameDLPanel');
        $labels = $panel->children[1]->children->toArray();
        $bloom = new BloomEffectBehaviour;
        
        $labels[0]->text = $game['title'];
        $labels[1]->text = $game['fileSize'];
        $panel->children[3]->image = new UXImage('res://.data/img/download.png');
        $panel->children[3]->cursor = 'HAND';
        $panel->children[3]->on('click',function () use ($game)
        {
            new Thread(function () use ($game)
            {
                try 
                {
                    if ($this->ariaDownloader->isRunning() == false)
                        $this->runAria();
                    $this->ariaDownloader->addDownload($game['uris'][0]);
                } catch (Throwable $ex)
                {
                    $this->doConstruct();
                    $this->runAria();
                    $this->ariaDownloader->addDownload($game['uris'][0]);
                }
            })->start();
            
            $this->hide();
        });
        
        $bloom->when = 'HOVER';
        $bloom->apply($panel->children[3]);
        
        $this->container->content->children->add($panel);
    }
    
    function clearGames()
    {
        foreach ($this->container->content->children->toArray() as $node)
            $node->free();
        $this->container->content->children->clear();
    }
    
    function runAria()
    {
        try 
        {
            $this->ariaDownloader->run();
        } catch (Throwable $ex)
        {
            UXDialog::show($ex->getMessage());
        }
    }
    
    static function scanDir($path)
    {
        $files = fs::scan($path,['callback'=>
                                 function (File $f)
                                 {
                                     if ($f->isDirectory() or 
                                          Regex::match('\.(exe|vbs|bat|rar)$',$f) == false or
                                          Regex::match('(?i)^unitycrashhandler.*\.exe$',fs::name($f)))
                                          return false;
                                     
                                     return $f;
                                 }]);
            
        return $files;
    }

}
