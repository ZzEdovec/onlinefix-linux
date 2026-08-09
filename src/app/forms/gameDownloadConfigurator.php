<?php
namespace app\forms;

use Throwable;
use std, gui, framework, app;


class gameDownloadConfigurator extends AbstractForm
{
    $downloadHandle = null;
    $gameTitle = '';
    $destDir = '';
    $downloadFinished = false;

    /**
     * @event construct
     */
    function doConstruct(UXEvent $e = null)
    {
        $this->titleLabel->text = $this->gameTitle;
        $this->statusLabel->text = $this->_('ARIADOWNLOADER.FETCHINGMETADATA');
        $this->cancelButton->text = $this->_('ARIADOWNLOADER.CANCEL');
        $this->detailLabel->text = '';
        $this->progressBar->progress = -1;
    }

    function startDownload($title, $magnetUri, $destDir)
    {
        $this->gameTitle = $title;
        $this->destDir = $destDir;
        $this->downloadFinished = false;

        $this->titleLabel->text = $title;
        $this->statusLabel->text = $this->_('ARIADOWNLOADER.FETCHINGMETADATA');
        $this->progressBar->progress = -1;

        $this->downloadHandle = AriaDownloader::startDownload(
            $magnetUri,
            $destDir,
            function ($line, $pct = null)
            {
                if ($this->isFree())
                    return;

                if ($pct !== null)
                {
                    $this->progressBar->progress = $pct / 100.0;
                    $this->detailLabel->text = $line;
                }
                else
                {
                    $this->detailLabel->text = $line;
                }
            },
            function ($status)
            {
                if ($this->isFree())
                    return;

                if ($status == 'fetching')
                    $this->statusLabel->text = $this->_('ARIADOWNLOADER.FETCHINGMETADATA');
                elseif ($status == 'connecting')
                    $this->statusLabel->text = $this->_('ARIADOWNLOADER.WAITING');
            },
            function ()
            {
                if ($this->isFree())
                    return;

                $this->downloadFinished = true;
                $this->statusLabel->text = $this->_('ARIADOWNLOADER.STARTDOWNLOAD');
                $this->detailLabel->text = '';
                $this->progressBar->progress = -1;
                $this->onDownloadComplete();
            },
            function ($errorMsg)
            {
                if ($this->isFree())
                    return;

                $this->statusLabel->text = $this->_('ARIADOWNLOADER.DOWNLOADERROR');
                $this->detailLabel->text = $errorMsg;
                $this->progressBar->progress = 0;
                UXDialog::show($this->_('ARIADOWNLOADER.DOWNLOADERROR') . "\n" . $errorMsg, 'ERROR');
                $this->hide();
            }
        );
    }

    function onDownloadComplete()
    {
        new Thread(function ()
        {
            try
            {
                $files = AriaDownloader::findGameFiles($this->destDir);

                uiLater(function () use ($files)
                {
                    if ($this->isFree())
                        return;

                    if (count($files) == 0)
                    {
                        AriaDownloader::removeDownload($this->destDir);
                        UXDialog::show($this->_('ARIADOWNLOADER.NOVALIDFILES'), 'ERROR');
                        $this->hide();
                        return;
                    }

                    $rarFiles = [];
                    $exeFiles = [];
                    foreach ($files as $file)
                    {
                        $ext = fs::ext($file);
                        if (str::lower($ext) == 'rar')
                            $rarFiles[] = $file;
                        elseif (str::lower($ext) == 'exe')
                            $exeFiles[] = $file;
                    }

                    $mainFile = null;
                    if (count($rarFiles) > 0)
                        $mainFile = $rarFiles[0];
                    elseif (count($exeFiles) > 0)
                        $mainFile = $exeFiles[0];

                    if ($mainFile == null)
                    {
                        AriaDownloader::removeDownload($this->destDir);
                        UXDialog::show($this->_('ARIADOWNLOADER.NOVALIDFILES'), 'ERROR');
                        $this->hide();
                        return;
                    }

                    $form = quUI::showFormAndFocus('newGameConfigurator', true);
                    $form->gameParams['openedFromAria'] = true;
                    $form->gameParams['originalFile'] = $mainFile;
                    $form->gameParams['skipConfig'] = false;
                    $form->gameName->text = $this->gameTitle;
                    $form->prefixPath->text = launcherSettings::getBasePathFor('prefixes') . '/' . $this->gameTitle;
                    $form->gamePath->text = launcherSettings::getBasePathFor('installs') . '/' . $this->gameTitle;

                    $this->hide();

                    $form->prepareForGame($files, $this->destDir);
                });
            }
            catch (\Throwable $ex)
            {
                uiLater(function () use ($ex)
                {
                    if ($this->isFree())
                        return;

                    AriaDownloader::removeDownload($this->destDir);
                    UXDialog::show($this->_('ARIADOWNLOADER.DOWNLOADERROR') . "\n" . $ex->getMessage(), 'ERROR');
                    $this->hide();
                });
            }
        })->start();
    }

    /**
     * @event cancelButton.action
     */
    function doCancelButtonAction(UXEvent $e = null)
    {
        AriaDownloader::cancelDownload($this->downloadHandle);
        $this->hide();
    }

    /**
     * @event hide
     */
    function doHide(UXWindowEvent $e = null)
    {
        if ($this->isFree())
            return;

        if ($this->downloadFinished == false)
        {
            AriaDownloader::cancelDownload($this->downloadHandle);
            AriaDownloader::removeDownload($this->destDir);
        }

        $this->downloadHandle = null;
        $this->free();
    }

    /**
     * @event keyUp-Esc
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {
        $this->doCancelButtonAction();
    }
}
