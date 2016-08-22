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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem;
use Magero\Channel\Manager\Console\Application;

/**
 * Class BaseCommand
 * @package Magero\Channel\Manager\Command
 *
 * @method Application getApplication()
 */
abstract class BaseCommand extends Command
{
    /** @var Filesystem\Filesystem */
    protected $fileSystem;

    /**
     * BaseCommand constructor
     */
    public function __construct()
    {
        $name = get_class($this);
        $name = strtolower(str_replace('Command', '', substr($name, strrpos($name, '\\') + 1)));

        parent::__construct($name);

        $this->fileSystem = new Filesystem\Filesystem();
    }
}
