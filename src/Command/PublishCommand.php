<?php

namespace MarvinCaspar\Composer\Command;

use Composer\Command\BaseCommand;
use MarvinCaspar\Composer\FileHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishCommand extends BaseCommand
{
    protected $tempDir = '../.temp';
    protected FileHelper $fileHelper;

    protected function configure()
    {
        $this->setName('azure:publish');
        $this->setDescription('Publish this composer package to Azure DevOps.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->fileHelper = $this->getFileHelper();
        $extra = $this->getComposer()->getPackage()->getExtra();

        if (!isset($extra['azure-publish-registry']) || !is_array($extra['azure-publish-registry'])) {
            return 0;
        }

        $this->copyPackage();
        $this->cleanIgnoredFiles();
        $this->sendPackage();
        $this->removeTempFiles();

        $output->writeln('Done.');
        return 0;
    }

    protected function getFileHelper(): FileHelper
    {
        return new FileHelper();
    }

    protected function copyPackage()
    {
        $this->fileHelper->copyDirectory('.', $this->tempDir);
    }

    protected function cleanIgnoredFiles()
    {
        if (!file_exists($this->tempDir . '/.gitignore')) {
            return;
        }

        $ignoredFiles = file($this->tempDir . '/.gitignore');

        if ($ignoredFiles === false) {
            return;
        }

        foreach ($ignoredFiles as $ignoredFile) {
            if (empty(trim($ignoredFile))) {
                continue;
            }

            $ignoredDir = $this->tempDir . trim($ignoredFile);
            if (is_dir($ignoredDir)) {
                $this->fileHelper->removeDirectory($ignoredDir);
            }
        }
    }

    protected function removeGitFolder()
    {
        $gitFolder = $this->tempDir . '/.git';
        if (is_dir($gitFolder)) {
            $this->fileHelper->removeDirectory($gitFolder);
        }
    }

    protected function sendPackage()
    {
        $extra = $this->getComposer()->getPackage()->getExtra();

        $command = 'az artifacts universal publish';
        $command .= ' --organization ' . 'https://' . $extra['azure-publish-registry']['organization'];
        $command .= ' --project "' . $extra['azure-publish-registry']['project'] . '"';
        $command .= ' --scope project';
        $command .= ' --feed ' . $extra['azure-publish-registry']['feed'];
        $command .= ' --name ' . str_replace('/', '.', $this->getComposer()->getPackage()->getName());
        $command .= ' --version ' . $this->getComposer()->getPackage()->getPrettyVersion();
        $command .= ' --description "' . $this->getComposer()->getPackage()->getDescription() . '"';
        $command .= ' --path ' . $this->tempDir;

        $this->executeShellCmd($command);
    }

    protected function executeShellCmd(string $cmd)
    {
        $output = array();
        $return_var = -1;
        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            throw new \Exception(implode("\n", $output));
        }
    }

    protected function removeTempFiles()
    {
        $this->fileHelper->removeDirectory($this->tempDir);
    }
}