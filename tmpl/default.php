<?php
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;

$articles   = $list['articles']   ?? [];
$categories = $list['categories'] ?? [];
$menus      = $list['menus']      ?? [];
$totals     = $list['_totals']    ?? ['articles' => count($articles), 'categories' => count($categories), 'menus' => count($menus)];

use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$moduleId   = $module->id;
$token      = Session::getFormToken();
$exportBase = Uri::base() . 'index.php?option=com_ajax&module=errorsmetadata&method=exportCsv&format=raw&' . $token . '=1&module_id=' . $moduleId;

function mmBadge(string $status, string $type, int $len = -1): string
{
    $lenStr = $len >= 0 ? ' (' . $len . ' car.)' : '';
    switch ($status) {
        case 'missing':
            $cls = 'bg-danger';
            $key = $type === 'metadesc' ? 'MOD_ERRORSMETADATA_NO_DESC' : 'MOD_ERRORSMETADATA_NO_TITLE';
            break;
        case 'short':
            $cls = 'bg-warning text-dark';
            $key = 'MOD_ERRORSMETADATA_DESC_SHORT';
            break;
        case 'long':
            $cls = 'bg-warning text-dark';
            $key = $type === 'metadesc' ? 'MOD_ERRORSMETADATA_DESC_LONG' : 'MOD_ERRORSMETADATA_TITLE_LONG';
            break;
        case 'duplicate':
            $cls = 'bg-info text-dark';
            $key = $type === 'metadesc' ? 'MOD_ERRORSMETADATA_DESC_DUPLICATE' : 'MOD_ERRORSMETADATA_TITLE_DUPLICATE';
            break;
        default:
            return '';
    }
    return '<span class="badge ' . $cls . ' me-1">' . Text::_($key) . $lenStr . '</span>';
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

<div class="mod-errorsmetadata">

    <ul class="nav nav-tabs mb-4" id="errorsMetadataTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="articles-tab" data-bs-toggle="tab" data-bs-target="#articles" type="button" role="tab" aria-controls="articles" aria-selected="true">
                <?php echo Text::_('MOD_ERRORSMETADATA_ARTICLES'); ?>
                <span class="badge bg-<?php echo $totals['articles'] ? 'warning' : 'success'; ?> ms-2"><?php echo $totals['articles']; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab" aria-controls="categories" aria-selected="false">
                <?php echo Text::_('MOD_ERRORSMETADATA_CATEGORIES'); ?>
                <span class="badge bg-<?php echo $totals['categories'] ? 'warning' : 'success'; ?> ms-2"><?php echo $totals['categories']; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="menus-tab" data-bs-toggle="tab" data-bs-target="#menus" type="button" role="tab" aria-controls="menus" aria-selected="false">
                <?php echo Text::_('MOD_ERRORSMETADATA_MENUS'); ?>
                <span class="badge bg-<?php echo $totals['menus'] ? 'warning' : 'success'; ?> ms-2"><?php echo $totals['menus']; ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="errorsMetadataTabsContent">

        <!-- ARTICLES -->
        <div class="tab-pane fade show active" id="articles" role="tabpanel" aria-labelledby="articles-tab">
            <?php if (!empty($articles)) : ?>
                <div class="d-flex justify-content-end px-3 pt-2 pb-1">
                    <a href="<?php echo $exportBase . '&type=articles'; ?>" class="btn btn-sm btn-outline-secondary">
                        <span class="icon-download" aria-hidden="true"></span> <?php echo Text::_('MOD_ERRORSMETADATA_EXPORT_CSV'); ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (empty($articles)) : ?>
                <p class="text-success ps-3 mb-0"><span class="icon-check-circle"></span> <?php echo Text::_('MOD_ERRORSMETADATA_ALL_OK'); ?></p>
            <?php else : ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($articles as $item) : ?>
                        <li class="d-flex align-items-start gap-2 py-2 px-3 border-bottom">
                            <div class="flex-grow-1">
                                <?php if ($item->link) : ?>
                                    <a href="<?php echo $item->link; ?>" class="fw-semibold"><?php echo htmlspecialchars($item->title); ?></a>
                                <?php else : ?>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($item->title); ?></span>
                                <?php endif; ?>
                                <div class="small mt-1">
                                    <?php if (isset($item->metadesc_status) && $item->metadesc_status !== 'ok') echo mmBadge($item->metadesc_status, 'metadesc', $item->metadesc_len ?? -1); ?>
                                    <?php if (isset($item->page_title_status) && $item->page_title_status !== 'ok') echo mmBadge($item->page_title_status, 'page_title', $item->page_title_len ?? -1); ?>
                                    <?php if (isset($item->metakey_status) && $item->metakey_status === 'missing') : ?>
                                        <span class="badge bg-secondary me-1"><?php echo Text::_('MOD_ERRORSMETADATA_NO_KEYS'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- CATEGORIES -->
        <div class="tab-pane fade" id="categories" role="tabpanel" aria-labelledby="categories-tab">
            <?php if (!empty($categories)) : ?>
                <div class="d-flex justify-content-end px-3 pt-2 pb-1">
                    <a href="<?php echo $exportBase . '&type=categories'; ?>" class="btn btn-sm btn-outline-secondary">
                        <span class="icon-download" aria-hidden="true"></span> <?php echo Text::_('MOD_ERRORSMETADATA_EXPORT_CSV'); ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (empty($categories)) : ?>
                <p class="text-success ps-3 mb-0"><span class="icon-check-circle"></span> <?php echo Text::_('MOD_ERRORSMETADATA_ALL_OK'); ?></p>
            <?php else : ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($categories as $item) : ?>
                        <li class="d-flex align-items-start gap-2 py-2 px-3 border-bottom">
                            <div class="flex-grow-1">
                                <?php if ($item->link) : ?>
                                    <a href="<?php echo $item->link; ?>" class="fw-semibold"><?php echo htmlspecialchars($item->title); ?></a>
                                <?php else : ?>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($item->title); ?></span>
                                <?php endif; ?>
                                <div class="small mt-1">
                                    <?php if (isset($item->metadesc_status) && $item->metadesc_status !== 'ok') echo mmBadge($item->metadesc_status, 'metadesc', $item->metadesc_len ?? -1); ?>
                                    <?php if (isset($item->metakey_status) && $item->metakey_status === 'missing') : ?>
                                        <span class="badge bg-secondary me-1"><?php echo Text::_('MOD_ERRORSMETADATA_NO_KEYS'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- MENUS -->
        <div class="tab-pane fade" id="menus" role="tabpanel" aria-labelledby="menus-tab">
            <?php if (!empty($menus)) : ?>
                <div class="d-flex justify-content-end px-3 pt-2 pb-1">
                    <a href="<?php echo $exportBase . '&type=menus'; ?>" class="btn btn-sm btn-outline-secondary">
                        <span class="icon-download" aria-hidden="true"></span> <?php echo Text::_('MOD_ERRORSMETADATA_EXPORT_CSV'); ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if (empty($menus)) : ?>
                <p class="text-success ps-3 mb-0"><span class="icon-check-circle"></span> <?php echo Text::_('MOD_ERRORSMETADATA_ALL_OK'); ?></p>
            <?php else : ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($menus as $item) : ?>
                        <li class="d-flex align-items-start gap-2 py-2 px-3 border-bottom">
                            <div class="flex-grow-1">
                                <?php if ($item->link) : ?>
                                    <a href="<?php echo $item->link; ?>" class="fw-semibold"><?php echo htmlspecialchars($item->title); ?></a>
                                <?php else : ?>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($item->title); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item->menutype)) : ?>
                                    <span class="small text-muted ms-2"><?php echo htmlspecialchars($item->menutype); ?></span>
                                <?php endif; ?>
                                <div class="small mt-1">
                                    <?php if (isset($item->metadesc_status) && $item->metadesc_status !== 'ok') echo mmBadge($item->metadesc_status, 'metadesc', $item->metadesc_len ?? -1); ?>
                                    <?php if (isset($item->page_title_status) && $item->page_title_status !== 'ok') echo mmBadge($item->page_title_status, 'page_title', $item->page_title_len ?? -1); ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</div>
