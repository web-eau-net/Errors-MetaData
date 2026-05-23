<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  mod_errorsmetadata
 * @copyright   Copyright (C) 2026 web-eau.net. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE
 */

namespace WebEau\Module\Errorsmetadata\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;

/**
 * Helper for mod_errorsmetadata.
 *
 * v2.2.0 : cross-check article ↔ menu
 *   - Un article avec metadesc/page_title manquant n'est pas signalé si un
 *     lien de menu associé a déjà cette valeur (et vice-versa).
 *   - Les statuts short/long/duplicate sont toujours signalés même si l'autre
 *     côté est OK.
 *
 * @since  2.2.0
 */
class ErrorsmetadataHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    const METADESC_MIN   = 120;
    const METADESC_MAX   = 160;
    const PAGE_TITLE_MAX = 60;

    // ─────────────────────────────────────────────────────────────────────────
    // POINT D'ENTRÉE
    // ─────────────────────────────────────────────────────────────────────────

    public function getList(Registry $params): array
    {
        return [
            'articles'   => $this->getListArticles($params),
            'categories' => $this->getListCategories($params),
            'menus'      => $this->getListMenus($params),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MAPS CROISÉES ARTICLE ↔ MENU
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Map [ article_id => ['metadesc' => string, 'page_title' => string] ]
     * construite à partir des liens de menu pointant vers com_content&view=article.
     * Si plusieurs menus pointent vers le même article, on garde la première
     * valeur non vide trouvée pour chaque champ.
     */
    private function buildArticleMenuMap(int $stateFilter): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([$db->quoteName('m.link'), $db->quoteName('m.params')]);
        $query->from($db->quoteName('#__menu', 'm'));
        $query->where($db->quoteName('m.client_id') . ' = 0');
        $query->where($db->quoteName('m.type') . ' = ' . $db->quote('component'));
        $query->where($db->quoteName('m.id') . ' > 1');
        $query->where($db->quoteName('m.link') . ' LIKE ' . $db->quote('%option=com_content%view=article%'));

        if ($stateFilter === 1) {
            $query->where($db->quoteName('m.published') . ' = 1');
        }

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList();
        } catch (\Exception $e) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            parse_str(parse_url($row->link, PHP_URL_QUERY), $qs);
            $articleId = isset($qs['id']) ? (int) $qs['id'] : 0;

            if ($articleId <= 0) {
                continue;
            }

            $mp        = (new Registry($row->params))->toArray();
            $menuDesc  = trim((string) ($mp['menu-meta_description'] ?? ''));
            $menuTitle = trim((string) ($mp['page_title'] ?? ''));

            if (!isset($map[$articleId])) {
                $map[$articleId] = ['metadesc' => '', 'page_title' => ''];
            }

            if ($map[$articleId]['metadesc'] === '' && $menuDesc !== '') {
                $map[$articleId]['metadesc'] = $menuDesc;
            }
            if ($map[$articleId]['page_title'] === '' && $menuTitle !== '') {
                $map[$articleId]['page_title'] = $menuTitle;
            }
        }

        return $map;
    }

    /**
     * Map [ article_id => ['metadesc' => string, 'page_title' => string] ]
     * construite à partir des articles (pour le cross-check côté menus).
     */
    private function buildArticleMetaMap(int $stateFilter): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('a.id'),
            'TRIM(' . $db->quoteName('a.metadesc') . ') AS ' . $db->quoteName('metadesc'),
            $db->quoteName('a.attribs'),
        ]);
        $query->from($db->quoteName('#__content', 'a'));

        if ($stateFilter === 1) {
            $query->where($db->quoteName('a.state') . ' = 1');
        }

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList();
        } catch (\Exception $e) {
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $attribs   = new Registry($row->attribs ?? '{}');
            $pageTitle = trim($attribs->get('article_page_title', ''));
            $map[(int) $row->id] = [
                'metadesc'   => trim($row->metadesc ?? ''),
                'page_title' => $pageTitle,
            ];
        }

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CALCUL DU STATUT SEO
    // ─────────────────────────────────────────────────────────────────────────

    private function metadescStatus(string $desc, array $descCount): string
    {
        if ($desc === '') return 'missing';
        if (($descCount[$desc] ?? 1) > 1) return 'duplicate';
        if (mb_strlen($desc) < self::METADESC_MIN) return 'short';
        if (mb_strlen($desc) > self::METADESC_MAX) return 'long';
        return 'ok';
    }

    private function pageTitleStatus(string $title, array $titleCount): string
    {
        if ($title === '') return 'missing';
        if (($titleCount[$title] ?? 1) > 1) return 'duplicate';
        if (mb_strlen($title) > self::PAGE_TITLE_MAX) return 'long';
        return 'ok';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ARTICLES
    // ─────────────────────────────────────────────────────────────────────────

    public function getListArticles(Registry $params): array
    {
        $user          = Factory::getApplication()->getIdentity();
        $db            = $this->getDatabase();
        $mCount        = (int) $params->get('arti_count', 5);
        $checkKeywords = (int) $params->get('check_keywords', 0);
        $stateFilter   = (int) $params->get('state_filter', 1);

        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('a.id'),
            $db->quoteName('a.title'),
            $db->quoteName('a.checked_out'),
            $db->quoteName('a.checked_out_time'),
            $db->quoteName('a.access'),
            $db->quoteName('a.created'),
            $db->quoteName('a.created_by'),
            $db->quoteName('a.featured'),
            $db->quoteName('a.state'),
            $db->quoteName('a.attribs'),
            'TRIM(' . $db->quoteName('a.metakey') . ') AS ' . $db->quoteName('metakey'),
            'TRIM(' . $db->quoteName('a.metadesc') . ') AS ' . $db->quoteName('metadesc'),
        ]);

        $query->from($db->quoteName('#__content', 'a'));

        $query->select($db->quoteName('uc.name', 'editor'));
        $query->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'));

        $query->select($db->quoteName('ua.name', 'author_name'));
        $query->join('LEFT', $db->quoteName('#__users', 'ua') . ' ON ' . $db->quoteName('ua.id') . ' = ' . $db->quoteName('a.created_by'));

        if ($stateFilter === 1) {
            $query->where($db->quoteName('a.state') . ' = 1');
        }

        switch ($params->get('arti_ordering', 'c_dsc')) {
            case 'm_dsc':
                $query->order($db->quoteName('a.modified') . ' DESC, ' . $db->quoteName('a.created') . ' DESC');
                break;
            default:
                $query->order($db->quoteName('a.created') . ' DESC');
        }

        $db->setQuery($query);

        try {
            $all = $db->loadObjectList();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 500);
        }

        // ── Map article → menus associés (cross-check) ────────────────────────
        $menuMap = $this->buildArticleMenuMap($stateFilter);

        // ── Maps doublons ─────────────────────────────────────────────────────
        $descCount      = [];
        $pageTitleCount = [];

        foreach ($all as $item) {
            $attribs   = new Registry($item->attribs ?? '{}');
            $pageTitle = trim($attribs->get('article_page_title', ''));
            $desc      = trim($item->metadesc ?? '');

            if ($desc !== '') {
                $descCount[$desc] = ($descCount[$desc] ?? 0) + 1;
            }
            if ($pageTitle !== '') {
                $pageTitleCount[$pageTitle] = ($pageTitleCount[$pageTitle] ?? 0) + 1;
            }
        }

        // ── Analyse ───────────────────────────────────────────────────────────
        $items = [];

        foreach ($all as $item) {
            $attribs   = new Registry($item->attribs ?? '{}');
            $pageTitle = trim($attribs->get('article_page_title', ''));
            $desc      = trim($item->metadesc ?? '');
            $key       = trim($item->metakey ?? '');

            $item->page_title     = $pageTitle;
            $item->page_title_len = mb_strlen($pageTitle);
            $item->metadesc_len   = mb_strlen($desc);

            $item->metadesc_status   = $this->metadescStatus($desc, $descCount);
            $item->page_title_status = $this->pageTitleStatus($pageTitle, $pageTitleCount);
            $item->metakey_status    = ($checkKeywords && $key === '') ? 'missing' : 'ok';

            // ── Cross-check avec les menus ─────────────────────────────────────
            // Règle : si le statut est 'missing' ET qu'un menu associé a la valeur
            // → on lève l'alerte (ok). Short/long/duplicate restent signalés.
            $menuMeta = $menuMap[(int) $item->id] ?? null;

            if ($menuMeta !== null) {
                if ($item->metadesc_status === 'missing' && $menuMeta['metadesc'] !== '') {
                    $item->metadesc_status = 'ok';
                }
                if ($item->page_title_status === 'missing' && $menuMeta['page_title'] !== '') {
                    $item->page_title_status = 'ok';
                }
            }

            $item->has_issue = (
                $item->metadesc_status   !== 'ok'
                || $item->page_title_status !== 'ok'
                || $item->metakey_status    !== 'ok'
            );

            if (!$item->has_issue) {
                continue;
            }

            $item->link = $user->authorise('core.edit', 'com_content.article.' . $item->id)
                ? Route::_('index.php?option=com_content&task=article.edit&id=' . $item->id)
                : '';

            $items[] = $item;
        }

        $total = \count($items);
        if ($mCount > 0) {
            $items = \array_slice($items, 0, $mCount);
        }

        return ['items' => $items, 'total' => $total];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CATEGORIES
    // ─────────────────────────────────────────────────────────────────────────

    public function getListCategories(Registry $params): array
    {
        $user          = Factory::getApplication()->getIdentity();
        $db            = $this->getDatabase();
        $mCount        = (int) $params->get('cate_count', 5);
        $checkKeywords = (int) $params->get('check_keywords', 0);
        $stateFilter   = (int) $params->get('state_filter', 1);

        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('c.id'),
            $db->quoteName('c.title'),
            $db->quoteName('c.checked_out'),
            $db->quoteName('c.checked_out_time'),
            $db->quoteName('c.access'),
            $db->quoteName('c.created_time', 'created'),
            $db->quoteName('c.created_user_id', 'created_by'),
            $db->quoteName('c.published', 'state'),
            $db->quoteName('c.extension'),
            'TRIM(' . $db->quoteName('c.metakey') . ') AS ' . $db->quoteName('metakey'),
            'TRIM(' . $db->quoteName('c.metadesc') . ') AS ' . $db->quoteName('metadesc'),
        ]);

        $query->from($db->quoteName('#__categories', 'c'));

        $query->select($db->quoteName('uc.name', 'editor'));
        $query->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('c.checked_out'));

        $query->select($db->quoteName('ua.name', 'author_name'));
        $query->join('LEFT', $db->quoteName('#__users', 'ua') . ' ON ' . $db->quoteName('ua.id') . ' = ' . $db->quoteName('c.created_user_id'));

        $query->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_content'));

        if ($stateFilter === 1) {
            $query->where($db->quoteName('c.published') . ' = 1');
        }

        switch ($params->get('cate_ordering', 'c_dsc')) {
            case 'm_dsc':
                $query->order($db->quoteName('c.modified_time') . ' DESC, ' . $db->quoteName('c.created_time') . ' DESC');
                break;
            default:
                $query->order($db->quoteName('c.created_time') . ' DESC');
        }

        $db->setQuery($query);

        try {
            $all = $db->loadObjectList();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 500);
        }

        $descCount = [];
        foreach ($all as $item) {
            $desc = trim($item->metadesc ?? '');
            if ($desc !== '') {
                $descCount[$desc] = ($descCount[$desc] ?? 0) + 1;
            }
        }

        $items = [];

        foreach ($all as $item) {
            $desc = trim($item->metadesc ?? '');
            $key  = trim($item->metakey ?? '');

            $item->metadesc_len      = mb_strlen($desc);
            $item->page_title        = '';
            $item->page_title_status = 'ok';
            $item->page_title_len    = 0;

            $item->metadesc_status = $this->metadescStatus($desc, $descCount);
            $item->metakey_status  = ($checkKeywords && $key === '') ? 'missing' : 'ok';

            $item->has_issue = (
                $item->metadesc_status !== 'ok'
                || $item->metakey_status  !== 'ok'
            );

            if (!$item->has_issue) {
                continue;
            }

            $item->link = $user->authorise('core.edit', $item->extension . '.category.' . $item->id)
                ? Route::_('index.php?option=com_categories&task=category.edit&id=' . $item->id . '&extension=' . $item->extension)
                : '';

            $items[] = $item;
        }

        $total = \count($items);
        if ($mCount > 0) {
            $items = \array_slice($items, 0, $mCount);
        }

        return ['items' => $items, 'total' => $total];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MENUS
    // ─────────────────────────────────────────────────────────────────────────

    public function getListMenus(Registry $params): array
    {
        $user        = Factory::getApplication()->getIdentity();
        $db          = $this->getDatabase();
        $mCount      = (int) $params->get('menu_count', 5);
        $stateFilter = (int) $params->get('state_filter', 1);

        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('m.id'),
            $db->quoteName('m.title'),
            $db->quoteName('m.menutype'),
            $db->quoteName('m.type'),
            $db->quoteName('m.link'),
            $db->quoteName('m.checked_out'),
            $db->quoteName('m.checked_out_time'),
            $db->quoteName('m.access'),
            $db->quoteName('m.published'),
            $db->quoteName('m.params'),
        ]);

        $query->from($db->quoteName('#__menu', 'm'));

        $query->select($db->quoteName('uc.name', 'editor'));
        $query->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('m.checked_out'));

        $query->where($db->quoteName('m.client_id') . ' = 0');
        $query->where($db->quoteName('m.type') . ' = ' . $db->quote('component'));
        $query->where($db->quoteName('m.id') . ' > 1');

        if ($stateFilter === 1) {
            $query->where($db->quoteName('m.published') . ' = 1');
        }

        $db->setQuery($query);

        try {
            $all = $db->loadObjectList();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 500);
        }

        // ── Map article → métadonnées (cross-check) ───────────────────────────
        $articleMap = $this->buildArticleMetaMap($stateFilter);

        // ── Maps doublons ─────────────────────────────────────────────────────
        $descCount      = [];
        $pageTitleCount = [];

        foreach ($all as $item) {
            $mp   = (new Registry($item->params))->toArray();
            $desc = trim((string) ($mp['menu-meta_description'] ?? ''));
            $pt   = trim((string) ($mp['page_title'] ?? ''));
            if ($desc !== '') {
                $descCount[$desc] = ($descCount[$desc] ?? 0) + 1;
            }
            if ($pt !== '') {
                $pageTitleCount[$pt] = ($pageTitleCount[$pt] ?? 0) + 1;
            }
        }

        $itemsReturn = [];

        foreach ($all as $item) {
            $mp        = (new Registry($item->params))->toArray();
            $desc      = trim((string) ($mp['menu-meta_description'] ?? ''));
            $pageTitle = trim((string) ($mp['page_title'] ?? ''));

            $item->metadesc       = $desc;
            $item->metakey        = trim((string) ($mp['menu-meta_keywords'] ?? ''));
            $item->page_title     = $pageTitle;
            $item->metadesc_len   = mb_strlen($desc);
            $item->page_title_len = mb_strlen($pageTitle);

            $item->metadesc_status   = $this->metadescStatus($desc, $descCount);
            $item->page_title_status = $this->pageTitleStatus($pageTitle, $pageTitleCount);
            $item->metakey_status    = 'ok';

            // ── Cross-check avec l'article associé ────────────────────────────
            // Uniquement pour les menus pointant vers un article spécifique
            if (strpos($item->link, 'view=article') !== false) {
                parse_str(parse_url($item->link, PHP_URL_QUERY), $qs);
                $articleId  = isset($qs['id']) ? (int) $qs['id'] : 0;
                $articleMeta = $articleId > 0 ? ($articleMap[$articleId] ?? null) : null;

                if ($articleMeta !== null) {
                    if ($item->metadesc_status === 'missing' && $articleMeta['metadesc'] !== '') {
                        $item->metadesc_status = 'ok';
                    }
                    if ($item->page_title_status === 'missing' && $articleMeta['page_title'] !== '') {
                        $item->page_title_status = 'ok';
                    }
                }
            }

            $item->has_issue = (
                $item->metadesc_status   !== 'ok'
                || $item->page_title_status !== 'ok'
            );

            if (!$item->has_issue) {
                continue;
            }

            $item->link_edit = $user->authorise('core.edit', 'com_menus')
                ? Route::_('index.php?option=com_menus&task=item.edit&id=' . (int) $item->id)
                : '';

            // Compatibilité template (ancien nom)
            $item->link = $item->link_edit;

            $itemsReturn[] = $item;
        }

        $total = \count($itemsReturn);
        if ($mCount > 0) {
            $itemsReturn = \array_slice($itemsReturn, 0, $mCount);
        }

        return ['items' => $itemsReturn, 'total' => $total];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX : EXPORT CSV
    // Appelé via com_ajax : ?option=com_ajax&module=errorsmetadata&format=raw&method=exportCsv&type=articles&module_id=X&TOKEN=1
    // ─────────────────────────────────────────────────────────────────────────

    public static function exportCsvAjax(): void
    {
        $app = \Joomla\CMS\Factory::getApplication();

        // Vérification CSRF
        \Joomla\CMS\Session\Session::checkToken('get') or $app->enqueueMessage('Token invalide', 'error') or $app->close();

        $type     = $app->input->getCmd('type', 'articles');
        $moduleId = (int) $app->input->getInt('module_id', 0);

        // Charger les paramètres du module
        $db    = \Joomla\CMS\Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = ' . $moduleId);
        $db->setQuery($query);
        $moduleParams = new \Joomla\Registry\Registry($db->loadResult());

        // Forcer la limite à 0 pour tout exporter
        $moduleParams->set('arti_count', 0);
        $moduleParams->set('cate_count', 0);
        $moduleParams->set('menu_count', 0);

        $helper = new self();
        $helper->setDatabase($db);
        $raw = $helper->getList($moduleParams);

        $rows    = [];
        $headers = [];

        switch ($type) {
            case 'articles':
                $headers = ['ID', 'Titre article', 'Page title actuel', 'Nb car. title', 'Statut title', 'Meta description actuelle', 'Nb car. desc', 'Statut desc'];
                foreach ($raw['articles']['items'] as $item) {
                    $rows[] = [
                        $item->id,
                        $item->title,
                        $item->page_title ?? '',
                        $item->page_title_len ?? 0,
                        $item->page_title_status ?? '',
                        $item->metadesc ?? '',
                        $item->metadesc_len ?? 0,
                        $item->metadesc_status ?? '',
                    ];
                }
                break;

            case 'categories':
                $headers = ['ID', 'Titre catégorie', 'Meta description actuelle', 'Nb car. desc', 'Statut desc'];
                foreach ($raw['categories']['items'] as $item) {
                    $rows[] = [
                        $item->id,
                        $item->title,
                        $item->metadesc ?? '',
                        $item->metadesc_len ?? 0,
                        $item->metadesc_status ?? '',
                    ];
                }
                break;

            case 'menus':
                $headers = ['ID', 'Titre menu', 'Type de menu', 'Page title actuel', 'Nb car. title', 'Statut title', 'Meta description actuelle', 'Nb car. desc', 'Statut desc'];
                foreach ($raw['menus']['items'] as $item) {
                    $rows[] = [
                        $item->id,
                        $item->title,
                        $item->menutype ?? '',
                        $item->page_title ?? '',
                        $item->page_title_len ?? 0,
                        $item->page_title_status ?? '',
                        $item->metadesc ?? '',
                        $item->metadesc_len ?? 0,
                        $item->metadesc_status ?? '',
                    ];
                }
                break;
        }

        $filename = 'missing_metadata_' . $type . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);

        $app->close();
    }

}