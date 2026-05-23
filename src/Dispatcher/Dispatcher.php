<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_errorsmetadata
 * @copyright   Copyright (C) 2026 web-eau.net. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE
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
