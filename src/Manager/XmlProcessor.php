<?php
/**
 *  This file is part of the Magento Channel Manager.
 *
 *  (c) Magero team <support@magero.pw>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Magero\Channel\Manager;

use SimpleXMLElement;

class XmlProcessor
{
    /**
     * @param SimpleXMLElement $xmlElement
     * @param string $childName
     * @return null|SimpleXMLElement
     */
    public function getChild($xmlElement, $childName)
    {
        /** @var SimpleXMLElement $child */
        foreach ($xmlElement->children() as $child) {
            if ($child->getName() == $childName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param SimpleXMLElement $xmlElement
     * @param string $childName
     * @param mixed $default
     * @return mixed
     */
    public function getChildValue($xmlElement, $childName, $default = null)
    {
        $child = $this->getChild($xmlElement, $childName);
        if (is_null($child)) {
            return $default;
        }

        return (string)$child;
    }

    /**
     * @param SimpleXMLElement $xmlElement
     * @param string $childName
     * @param string $value
     * @return $this
     */
    public function setChildValue($xmlElement, $childName, $value)
    {
        if (!$child = $this->getChild($xmlElement, $childName)) {
            $child = $xmlElement->addChild($childName);
            $child[0] = $value;
        }

        return $this;
    }

    /**
     * @param SimpleXMLElement $xmlElement
     * @param string $version
     * @return array|null
     */
    public function getPackageByVersion($xmlElement, $version)
    {
        /** @var SimpleXMLElement $child */
        foreach ($xmlElement->children() as $child) {
            if ($this->getChildValue($child, 'v') == $version) {
                return array(
                    'version' => $version,
                    'stability' => $this->getChildValue($child, 's'),
                    'date' => $this->getChildValue($child, 'd'),
                );
            }
        }

        return null;
    }

    /**
     * @param SimpleXMLElement $xmlElement
     * @param string $name
     * @return SimpleXMLElement|null
     */
    public function getPackageByName($xmlElement, $name)
    {
        /** @var SimpleXMLElement $child */
        foreach ($xmlElement->children() as $child) {
            if ($this->getChildValue($child, 'n') == $name) {
                return $child;
            }
        }

        return null;
    }
}
