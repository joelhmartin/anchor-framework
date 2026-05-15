<?php
/**
 * Live Editor admin template — IDE host element.
 * The actual UI is rendered by dist/ide.min.js mounting on #anchor-ide.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'Anchor_Editor_IDE_Page' ) ) {
	Anchor_Editor_IDE_Page::instance()->render_host();
} else {
	echo '<div class="wrap"><p>Anchor IDE not loaded.</p></div>';
}
