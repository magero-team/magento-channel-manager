<?php
/**
 *  This file is part of the Magento Channel Manager.
 *
 *  (c) Magero team <support@magero.pw>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Magero\Channel\Manager\Command;

use PharData;
use SimpleXMLElement;
use Symfony\Component\Console\Exception;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Magero\Channel\Manager\XmlProcessor;

class UploadCommand extends BaseCommand
{
    const ARGUMENT_PACKAGE_NAME = 'package_name';
    const ARGUMENT_PACKAGE_VERSION = 'package_version';
    const OPTION_PACKAGE_STABILITY = 'stability';
    const OPTION_PACKAGE_FILE_NAME = 'file';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setDescription('Upload new package to channel');

        $this->addArgument(
            self::ARGUMENT_PACKAGE_NAME,
            Input\InputArgument::REQUIRED,
            'Package name'
        );

        $this->addArgument(
            self::ARGUMENT_PACKAGE_VERSION,
            Input\InputArgument::REQUIRED,
            'Package version'
        );

        $this->addOption(
            self::OPTION_PACKAGE_STABILITY,
            's',
            Input\InputOption::VALUE_REQUIRED,
            'Package stability',
            'stable'
        );

        $this->addOption(
            self::OPTION_PACKAGE_FILE_NAME,
            'f',
            Input\InputOption::VALUE_REQUIRED,
            'Package file name'
        );
    }

    /**
     * @inheritdoc
     */
    protected function execute(Input\InputInterface $input, Output\OutputInterface $output)
    {
        $app = $this->getApplication();
        $app->configureDirectories($input)->getChannelInfo();

        $packageName = $input->getArgument(self::ARGUMENT_PACKAGE_NAME);
        $packageVersion = $input->getArgument(self::ARGUMENT_PACKAGE_VERSION);

        if (!$fileName = $input->getOption(self::OPTION_PACKAGE_FILE_NAME)) {
            $fileName = $packageName . '-' . $packageVersion . '.tgz';
        }
        if (!strpos($fileName, '.tgz')) {
            $fileName = $fileName . '.tgz';
        }
        $fileName = $app->getTempDirectory($fileName);
        if (!$this->fileSystem->exists($fileName)) {
            throw new Exception\RuntimeException(
                sprintf('%s package file is not exist', $fileName)
            );
        }

        $stability = $input->getOption(self::OPTION_PACKAGE_STABILITY);
        if (!in_array($stability, array('alpha', 'beta', 'stable'))) {
            $stability = 'stable';
        }

        $xmlProcessor = new XmlProcessor();

        $packagesFile = $app->getChannelDirectory('packages.xml');
        if ($this->fileSystem->exists($packagesFile)) {
            $packagesXml = simplexml_load_file($packagesFile);
        } else {
            $packagesXml = simplexml_load_string("<?xml version=\"1.0\"?><data/>");
        }
        if (!$packageXmlElement = $xmlProcessor->getPackageByName($packagesXml, $packageName)) {
            $packageXmlElement = $packagesXml->addChild('p');
            $packageXmlElement->addChild('n')[0] = $packageName;
        }
        if (!$packageReleasesXmlElement = $xmlProcessor->getChild($packageXmlElement, 'r')) {
            $packageReleasesXmlElement = $packageXmlElement->addChild('r');
        }

        $stabilityTag = 's';
        switch ($stability) {
            case 'alpha':
                $stabilityTag = 'a';
                break;
            case 'beta':
                $stabilityTag = 'b';
                break;
        }
        if (!$packageReleaseStabilityXmlElement = $xmlProcessor->getChild($packageReleasesXmlElement, $stabilityTag)) {
            $packageReleaseStabilityXmlElement = $packageReleasesXmlElement->addChild($stabilityTag);
        }
        $packageReleaseStabilityXmlElement[0] = $packageVersion;

        $packageDirectory = $app->getChannelDirectory($packageName);
        $releasesFile = $packageDirectory . DIRECTORY_SEPARATOR . 'releases.xml';
        if ($this->fileSystem->exists($releasesFile)) {
            $releasesXml = simplexml_load_file($releasesFile);
        } else {
            $releasesXml = simplexml_load_string("<?xml version=\"1.0\"?><releases/>");
        }
        if ($xmlProcessor->getPackageByVersion($releasesXml, $packageVersion)) {
            throw new Exception\RuntimeException(
                sprintf('Package version "%s" already exist', $packageVersion)
            );
        }

        $release = $releasesXml->addChild('r');
        $release->addChild('v')[0] = $packageVersion;
        $release->addChild('s')[0] = $stability;
        $release->addChild('d')[0] = date('Y-m-d');

        $packageVersionDirectory = $app->getChannelDirectory($packageName . DIRECTORY_SEPARATOR . $packageVersion);

        $pharPackageXmlFile = 'phar://' . $fileName . DIRECTORY_SEPARATOR . 'package.xml';
        if (!$this->fileSystem->exists($pharPackageXmlFile)) {
            throw new Exception\RuntimeException(
                sprintf('File package.xml is not present in "%s"', $fileName)
            );
        }

        $packageXmlElement = simplexml_load_file($pharPackageXmlFile);
        if (!$packageXmlElement instanceof SimpleXMLElement) {
            throw new Exception\RuntimeException(
                sprintf('Invalid content of file package.xml: "%s"', $fileName)
            );
        }

        $xmlProcessor->setChildValue($packageXmlElement, 'version', $packageVersion);
        if (!$xmlProcessor->getChildValue($packageXmlElement, 'notes')) {
            $xmlProcessor->setChildValue($packageXmlElement, 'notes', 'Release ' . $packageVersion);
        }

        $packageFileName = $packageVersionDirectory . DIRECTORY_SEPARATOR . $packageName . '-' . $packageVersion . '.tgz';

        $phar = new PharData($fileName);
        $phar->addFromString('package.xml', $packageXmlElement->asXML());

        $this->fileSystem->mkdir($packageDirectory);
        $this->fileSystem->mkdir($packageVersionDirectory);
        $this->fileSystem->rename($fileName, $packageFileName);
        $this->fileSystem->dumpFile(
            $packageVersionDirectory . DIRECTORY_SEPARATOR . 'package.xml',
            $packageXmlElement->asXML()
        );
        $this->fileSystem->dumpFile($releasesFile, $releasesXml->asXML());
        $this->fileSystem->dumpFile($packagesFile, $packagesXml->asXML());

        $output->writeln('Package was uploaded successfully');
    }
}
