<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_errorsmetadata
 * @author      web-eau.net | daniel@web-eau.net
 * @copyright   (C) 2026 web-eau.net <https://web-eau.net>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/src/Helper/ErrorsmetadataHelper.php';

$helper = new \WebEau\Module\Errorsmetadata\Administrator\Helper\ErrorsmetadataHelper();
$helper->setDatabase(Factory::getDbo());

$raw = $helper->getList($params);

$list = [
    'articles'   => $raw['articles']['items']   ?? [],
    'categories' => $raw['categories']['items'] ?? [],
    'menus'      => $raw['menus']['items']       ?? [],
];

$totals = [
    'articles'   => $raw['articles']['total']   ?? 0,
    'categories' => $raw['categories']['total'] ?? 0,
    'menus'      => $raw['menus']['total']       ?? 0,
];

$list['_totals'] = $totals;

require ModuleHelper::getLayoutPath('mod_errorsmetadata', $params->get('layout', 'default'));
