<?php
/**
 * Folders feature loader.
 *
 * Brings together the taxonomy, its bootstrap terms, the sidebar/tree
 * rendering, the folder/template ajax handlers, and the archive query
 * into a single predictable location.
 *
 * Nonce handle names ('folder-actions', 'template-actions', 'bulk-actions',
 * 'id-general') are preserved so the existing JS localized `idAjax_*`
 * objects and server-side `check_ajax_referer()` calls keep working.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$idwiz_folders_dir = __DIR__ . '/';

require_once $idwiz_folders_dir . 'taxonomy.php';
require_once $idwiz_folders_dir . 'bootstrap.php';
require_once $idwiz_folders_dir . 'helpers.php';
require_once $idwiz_folders_dir . 'tree.php';
require_once $idwiz_folders_dir . 'query.php';
require_once $idwiz_folders_dir . 'folder-ajax.php';
require_once $idwiz_folders_dir . 'template-ajax.php';
