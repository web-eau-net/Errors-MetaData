<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_errorsmetadata
 * @author      web-eau.net | daniel@web-eau.net
 * @copyright   (C) 2026 web-eau.net <https://web-eau.net>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace WebEau\Module\Errorsmetadata\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use WebEau\Module\Errorsmetadata\Administrator\Helper\ErrorsmetadataHelper;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];

        $helper = new ErrorsmetadataHelper();
        $helper->setDatabase(\Joomla\CMS\Factory::getDbo());

        $data['list'] = $helper->getList($params);

        return $data;
    }
}
