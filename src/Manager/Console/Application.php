<?php
/**
 *  This file is part of the Magento Channel Manager.
 *
 *  (c) Magero team <support@magero.pw>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Magero\Channel\Manager\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem;
use Magero\Channel\Manager\Command;
use Magero\Channel\Manager\XmlProcessor;

class Application extends BaseApplication
{
    const VERSION = '1.0.0';
    const OPTION_CHANNEL_DIRECTORY = 'dir';
    const OPTION_TEMP_DIRECTORY = 'temp-dir';

    /** @var string */
    private $channelDirectory = '.';

    /** @var string */
    private $tempDirectory;

    /** @var Filesystem\Filesystem */
    private $fileSystem;

    /**
     * Application constructor
     */
    public function __construct()
    {
        parent::__construct('Magero Channel Manager', self::VERSION);

        $this->fileSystem = new Filesystem\Filesystem();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands()
    {
        $commands = array_merge(
            parent::getDefaultCommands(),
            array(
                new Command\UploadCommand(),
            )
        );

        return $commands;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(new InputOption(
            self::OPTION_CHANNEL_DIRECTORY,
            'd',
            InputOption::VALUE_REQUIRED,
            'Magento channel directory'
        ));

        $definition->addOption(new InputOption(
            self::OPTION_TEMP_DIRECTORY,
            't',
            InputOption::VALUE_REQUIRED,
            'Channel temp directory'
        ));

        return $definition;
    }

    /**
     * @param InputInterface $input
     * @return $this
     */
    public function configureDirectories(InputInterface $input)
    {
        if ($directory = $input->getOption(self::OPTION_CHANNEL_DIRECTORY)) {
            $this->channelDirectory = $directory;
        }
        $this->validateDirectory($this->channelDirectory);
        $this->channelDirectory = realpath($this->channelDirectory);
        $this->tempDirectory = $this->channelDirectory . DIRECTORY_SEPARATOR . 'temp';

        if ($directory = $input->getOption(self::OPTION_TEMP_DIRECTORY)) {
            $this->tempDirectory = $directory;
        }
        $fileSystem = new Filesystem\Filesystem();
        if (!$fileSystem->exists($this->tempDirectory)) {
            $fileSystem->mkdir($this->tempDirectory);
        }
        $this->tempDirectory = realpath($this->tempDirectory);

        return $this;
    }

    /**
     * @param null $path
     * @return string
     */
    public function getChannelDirectory($path = null)
    {
        if (!$this->channelDirectory) {
            throw new RuntimeException('Channel directory in not configured yet');
        }
        $this->validateDirectory($this->channelDirectory);

        if ($path) {
            return $this->channelDirectory . DIRECTORY_SEPARATOR . trim(ltrim((string)$path, '\\\/'));
        }

        return $this->channelDirectory;
    }

    /**
     * @param null $path
     * @return string
     */
    public function getTempDirectory($path = null)
    {
        if (!$this->tempDirectory) {
            throw new RuntimeException('Channel temp directory in not configured yet');
        }
        $this->validateDirectory($this->tempDirectory, 'Channel temp');

        if ($path) {
            return $this->tempDirectory . DIRECTORY_SEPARATOR . trim(ltrim((string)$path, '\\\/'));
        }

        return $this->tempDirectory;
    }

    /**
     * @param string $directory
     * @param string $label
     */
    private function validateDirectory($directory, $label = 'Channel')
    {
        if (!$directory) {
            throw new RuntimeException(
                sprintf('%s directory is not specified', $label, $directory)
            );
        }
        if (!is_dir($directory)) {
            throw new RuntimeException(
                sprintf('%s directory "%s" is not exist', $label, $directory)
            );
        }
        if (!is_readable($directory)) {
            throw new Filesystem\Exception\IOException(
                sprintf('%s directory "%s" is not readable', $label, $directory)
            );
        }
        if (!is_writable($directory)) {
            throw new Filesystem\Exception\IOException(
                sprintf('%s directory "%s" is not writable', $label, $directory)
            );
        }
    }

    /**
     * @return array
     */
    public function getChannelInfo()
    {
        $channelFile = $this->getChannelDirectory('channel.xml');
        if (!$this->fileSystem->exists($channelFile)) {
            throw new RuntimeException(
                sprintf('Channel file not found in directory "%s" ', $this->getChannelDirectory())
            );
        }
        if (!is_readable($channelFile)) {
            throw new RuntimeException(
                sprintf('Channel file "%s" is not readable', $channelFile)
            );
        }

        $xmlElement = simplexml_load_file($channelFile);
        $xmlProcessor = new XmlProcessor();
        if (!$name = $xmlProcessor->getChildValue($xmlElement, 'name')) {
            throw new RuntimeException(
                sprintf('Channel name is not defined in file "%s"', $channelFile)
            );
        }
        if (!$uri = $xmlProcessor->getChildValue($xmlElement, 'uri')) {
            throw new RuntimeException(
                sprintf('Channel uri is not defined in file "%s"', $channelFile)
            );
        }
        if (!$summary = $xmlProcessor->getChildValue($xmlElement, 'summary')) {
            throw new RuntimeException(
                sprintf('Channel summary is not defined in file "%s"', $channelFile)
            );
        }

        return array(
            'name' => $name,
            'uri' => $uri,
            'summary' => $summary,
        );
    }
}
