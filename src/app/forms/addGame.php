<?php
namespace app\forms;

use std, gui, framework, app;


class addGame extends AbstractForm
{
    $filteredGames = [];
    $allGames = [];

    /**
     * @event construct
     */
    function doConstruct(UXEvent $e = null)
    {
        $this->headerLabel->text = $this->_('ADDGAME.HEADER');
        $this->subheaderLabel->text = $this->_('ADDGAME.SUBHEADER');
        $this->sourceLabel->text = $this->_('ADDGAME.SOURCE.SELECT');
        $this->loadSourceButton->text = $this->_('ADDGAME.SOURCE.SELECT');
        $this->localButton->text = $this->_('ADDGAME.FILECHOOSER');
        $this->downloadButton->text = $this->_('ARIADOWNLOADER.STARTDOWNLOAD');
        $this->cancelButton->text = $this->_('ARIADOWNLOADER.CANCEL');
        $this->listLabel->text = '';
        $this->statusLabel->text = '';

        $this->loadSourceButton->enabled = AriaDownloader::isAvailable();
        if (!$this->loadSourceButton->enabled)
        {
            $this->loadSourceButton->text = $this->_('ADDGAME.SOURCE.NO');
            $this->sourceUrl->enabled = false;
        }

        $savedSource = $this->appModule()->launcher->get('hydraSource', 'User Settings');
        if ($savedSource != null)
            $this->sourceUrl->text = $savedSource;
    }

    /**
     * @event loadSourceButton.action
     */
    function doLoadSourceButtonAction(UXEvent $e = null)
    {
        $url = str::trim($this->sourceUrl->text);
        if (str::length($url) == 0)
        {
            $url = UXDialog::input($this->_('ADDGAME.SOURCE.NEW'), 'https://');
            if ($url == null)
                return;

            $url = str::trim($url);
            if (str::length($url) == 0)
                return;

            $this->sourceUrl->text = $url;
        }

        $this->loadSourceButton->enabled = false;
        $this->loadSourceButton->text = $this->_('ADDGAME.LOADING');
        $this->statusLabel->text = $this->_('ADDGAME.LOADING');
        $this->statusLabel->visible = true;
        $this->listBox->visible = false;

        AriaDownloader::fetchSource($url, function ($data, $error) use ($url)
        {
            if ($this->isFree())
                return;

            if ($data == null)
            {
                $this->loadSourceButton->enabled = true;
                $this->loadSourceButton->text = $this->_('ADDGAME.SOURCE.SELECT');
                $this->statusLabel->text = $this->_('ADDGAME.SOURCE.HINT');
                UXDialog::show($this->_('ADDGAME.SOURCE.FAIL'), 'ERROR');
                return;
            }

            $this->allGames = $data['downloads'];
            $this->filteredGames = $this->allGames;

            $this->appModule()->launcher->set('hydraSource', $url, 'User Settings');

            $this->loadSourceButton->text = sprintf($this->_('ADDGAME.SOURCE'), $data['name'] ?? '');
            $this->loadSourceButton->enabled = true;

            $this->sourceLabel->text = $this->_('ADDGAME.SOURCE.SELECT');
            $this->statusLabel->visible = false;
            $this->searchBox->visible = true;
            $this->listBox->visible = true;

            $this->populateGameList();
        });
    }

    /**
     * @event searchField.keyUp
     */
    function doSearchFieldKeyUp(UXKeyEvent $e = null)
    {
        $query = str::lower(str::trim($e->sender->text));
        if (str::length($query) == 0)
        {
            $this->filteredGames = $this->allGames;
        }
        else
        {
            $this->filteredGames = [];
            foreach ($this->allGames as $game)
            {
                if (str::contains(str::lower($game['title'] ?? ''), $query))
                    $this->filteredGames[] = $game;
            }
        }

        $this->populateGameList();
    }

    function populateGameList()
    {
        $this->gameList->items->clear();
        $this->downloadButton->enabled = false;

        if (count($this->filteredGames) == 0)
        {
            $this->listLabel->text = $this->_('ADDGAME.SEARCH.NOTFOUND');
            return;
        }

        $this->listLabel->text = sprintf($this->_('ADDGAME.GAMESCOUNT'), count($this->filteredGames));
        foreach ($this->filteredGames as $game)
        {
            $title = $game['title'] ?? 'Unknown';
            $size = $game['fileSize'] ?? '';
            $label = $title;
            if (str::length($size) > 0)
                $label .= ' (' . $size . ')';

            $this->gameList->items->add($label);
        }
    }

    /**
     * @event gameList.action
     */
    function doGameListAction(UXEvent $e = null)
    {
        $this->downloadButton->enabled = $this->gameList->selectedIndex != -1;
    }

    /**
     * @event downloadButton.action
     */
    function doDownloadButtonAction(UXEvent $e = null)
    {
        $index = $this->gameList->selectedIndex;
        if ($index == null or $index < 0)
            return;

        $game = $this->filteredGames[$index];
        $uris = $game['uris'] ?? [];
        $magnetUri = (is_array($uris) and count($uris) > 0) ? $uris[0] : null;

        if ($magnetUri == null)
        {
            UXDialog::show($this->_('ADDGAME.SOURCE.FAIL'), 'ERROR');
            return;
        }

        $destDir = launcherSettings::getBasePathFor('downloads');
        if (!fs::isDir($destDir))
        {
            UXDialog::show($this->_('ARIADOWNLOADER.DOWNLOADFOLDER.NOTEXIST'), 'ERROR');
            return;
        }

        if (AriaDownloader::isBusy())
        {
            UXDialog::show($this->_('ADDGAME.DOWNLOAD.ALREADY'), 'ERROR');
            return;
        }

        $gameTitle = str::trim($game['title'] ?? '');
        $gameTitle = str::replace($gameTitle, '/', '_');
        $gameTitle = str::replace($gameTitle, '\\', '_');
        $gameTitle = str::replace($gameTitle, '..', '_');
        $gameTitle = str::replace($gameTitle, ':', '_');
        $gameTitle = str::replace($gameTitle, "\r", '_');
        $gameTitle = str::replace($gameTitle, "\n", '_');
        $gameTitle = str::replace($gameTitle, "\t", '_');
        $gameTitle = str::trim($gameTitle, '. ');
        $gameTitle = str::sub($gameTitle, 0, 100);
        if (str::length($gameTitle) == 0)
            $gameTitle = 'Game';

        $baseTitle = $gameTitle;
        $gameDir = $destDir . '/' . $gameTitle;
        $suffix = 2;
        while (fs::isDir($gameDir))
        {
            $gameTitle = $baseTitle . ' (' . $suffix . ')';
            $gameDir = $destDir . '/' . $gameTitle;
            $suffix++;
        }

        $this->hide();

        $form = app()->getNewForm('gameDownloadConfigurator');
        $form->title = $gameTitle;
        $form->show();
        $form->startDownload($gameTitle, $magnetUri, $gameDir);
    }

    /**
     * @event localButton.action
     */
    function doLocalButtonAction(UXEvent $e = null)
    {
        $dc = new UXDirectoryChooser;
        $dc->title = $this->_('ADDGAME.FILECHOOSER');

        $path = $dc->showDialog($this->visible ? $this : null);
        if ($path == null)
            return;

        $this->hide();

        new Thread(function () use ($path)
        {
            $files = \app\forms\MainForm::scanDir($path);

            uiLater(function () use ($files, $path)
            {
                $form = quUI::showFormAndFocus('newGameConfigurator', true);
                uiLater(function () use ($files, $path, $form) { $form->prepareForGame($files, $path); });
            });
        })->start();
    }

    /**
     * @event cancelButton.action
     */
    function doCancelButtonAction(UXEvent $e = null)
    {
        $this->hide();
    }

    /**
     * @event hide
     */
    function doHide(UXWindowEvent $e = null)
    {
        if ($this->isFree())
            return;

        $this->free();
    }

    /**
     * @event keyUp-Esc
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {
        $this->hide();
    }
}
