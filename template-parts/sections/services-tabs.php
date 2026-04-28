<?php
/**
 * Section: Services Tabs
 *
 * Tabbed services section: sticky pill tabs, sticky image (desktop), scrollable content.
 *
 * Props (via anchor_get_template_data):
 *   eyebrow        (string)  Optional eyebrow text.
 *   heading        (string)  Section heading.
 *   heading_accent (string)  Accent/italic portion of the heading.
 *   text           (string)  Optional subtext below heading.
 *   tabs           (array)   Array of tab data.
 *   cta            (array)   Optional bottom CTA.
 *
 * Tab data shape:
 *   id          (string)  Unique ID for the tab.
 *   label       (string)  Tab pill label.
 *   subtitle    (string)  Optional subtitle badge text.
 *   title       (string)  Content heading.
 *   description (string)  Body paragraph text.
 *   items       (array)   Bullet list items (strings).
 *   image       (string)  Image URL.
 *   image_alt   (string)  Image alt text.
 *   url         (string)  Optional "Learn More" link.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

$eyebrow        = ! empty( $props['eyebrow'] )        ? $props['eyebrow']        : '';
$heading        = ! empty( $props['heading'] )        ? $props['heading']        : '';
$heading_accent = ! empty( $props['heading_accent'] ) ? $props['heading_accent'] : '';
$text           = ! empty( $props['text'] )           ? $props['text']           : '';
$tabs           = ! empty( $props['tabs'] )           ? $props['tabs']           : [];
$cta            = ! empty( $props['cta'] )            ? $props['cta']            : null;

if ( empty( $tabs ) ) return;

// Unique ID scopes the pill/grid JS selectors so multiple instances don't collide.
$uid = 'ast-' . substr( md5( $heading . count( $tabs ) ), 0, 8 );
?>
<div class="anchor-section-pad anchor-services-tabs" id="services">
<div class="anchor-container">

    <?php // Section header. ?>
    <?php if ( $heading || $eyebrow ) : ?>
        <div class="anchor-services-tabs__header">
            <?php
            anchor_render_component( 'heading-group', [
                'eyebrow'        => $eyebrow,
                'heading'        => $heading,
                'heading_accent' => $heading_accent,
                'tag'            => 'h2',
                'align'          => 'left',
            ] );
            ?>
            <?php if ( $text ) : ?>
                <p class="anchor-text-muted anchor-services-tabs__header-text"><?php echo anchor_esc_content( $text ); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php // Sticky tab pills. ?>
    <div class="anchor-tabs__pills" role="tablist" id="<?php echo esc_attr( $uid ); ?>-pills">
        <?php foreach ( $tabs as $i => $tab ) :
            $tab_id = ! empty( $tab['id'] ) ? $tab['id'] : ( 'tab-' . $i );
        ?>
            <button
                class="anchor-tab-pill<?php echo ( 0 === $i ) ? ' anchor-tab-pill--active' : ''; ?>"
                role="tab"
                aria-selected="<?php echo ( 0 === $i ) ? 'true' : 'false'; ?>"
                aria-controls="<?php echo esc_attr( $uid ); ?>-panel-<?php echo esc_attr( $i ); ?>"
                data-tab-index="<?php echo esc_attr( $i ); ?>"
                data-tabs-uid="<?php echo esc_attr( $uid ); ?>"
                type="button"
            >
                <?php echo esc_html( $tab['label'] ); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php // 2-column grid: sticky image + scrollable content. ?>
    <div class="anchor-tabs__grid" id="<?php echo esc_attr( $uid ); ?>-grid">

        <?php // Image column (2fr, sticky on desktop). ?>
        <div class="anchor-tabs__image-col">
            <?php foreach ( $tabs as $i => $tab ) :
                $img     = ! empty( $tab['image'] )     ? $tab['image']     : '';
                $img_alt = ! empty( $tab['image_alt'] ) ? $tab['image_alt'] : ( ! empty( $tab['title'] ) ? $tab['title'] : '' );
            ?>
                <div
                    class="anchor-tabs__image<?php echo ( 0 === $i ) ? ' anchor-tabs__image--active' : ''; ?>"
                    data-tab-image="<?php echo esc_attr( $i ); ?>"
                    data-tabs-uid="<?php echo esc_attr( $uid ); ?>"
                    role="none"
                >
                    <?php if ( $img ) : ?>
                        <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="anchor-tabs__image-overlay" aria-hidden="true"></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php // Content column (3fr). ?>
        <div class="anchor-tabs__content-col">
            <?php foreach ( $tabs as $i => $tab ) :
                $subtitle    = ! empty( $tab['subtitle'] )    ? $tab['subtitle']    : ( ! empty( $tab['label'] ) ? $tab['label'] : '' );
                $title       = ! empty( $tab['title'] )       ? $tab['title']       : '';
                $description = ! empty( $tab['description'] ) ? $tab['description'] : '';
                $items       = ! empty( $tab['items'] )       ? $tab['items']       : [];
                $url         = ! empty( $tab['url'] )         ? $tab['url']         : '';
            ?>
                <div
                    class="anchor-tabs__panel<?php echo ( 0 === $i ) ? ' anchor-tabs__panel--active' : ''; ?>"
                    id="<?php echo esc_attr( $uid ); ?>-panel-<?php echo esc_attr( $i ); ?>"
                    role="tabpanel"
                    data-tab-panel="<?php echo esc_attr( $i ); ?>"
                    data-tabs-uid="<?php echo esc_attr( $uid ); ?>"
                    <?php echo ( 0 !== $i ) ? 'aria-hidden="true"' : ''; ?>
                >
                    <?php if ( $subtitle ) : ?>
                        <div class="anchor-badge anchor-badge--brand anchor-tabs__panel-badge"><?php echo esc_html( $subtitle ); ?></div>
                    <?php endif; ?>

                    <?php if ( $title ) : ?>
                        <h3 class="anchor-tabs__panel-title"><?php echo esc_html( $title ); ?></h3>
                    <?php endif; ?>

                    <?php if ( $description ) : ?>
                        <p class="anchor-text-muted anchor-tabs__panel-desc"><?php echo anchor_esc_content( $description ); ?></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $items ) ) : ?>
                        <ul class="anchor-tabs__panel-list">
                            <?php foreach ( $items as $item ) : ?>
                                <li>
                                    <span class="anchor-tabs__panel-check" aria-hidden="true"><?php echo anchor_icon( 'check-circle', 16 ); ?></span>
                                    <span><?php echo esc_html( $item ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ( $url ) : ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="anchor-btn anchor-btn--ghost anchor-services-tabs__cta-btn">
                            Learn More
                            <?php echo anchor_icon( 'arrow-right', 14 ); ?>
                        </a>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <?php // Optional bottom CTA. ?>
    <?php if ( $cta && ! empty( $cta['label'] ) ) : ?>
        <div class="anchor-section-cta">
            <?php
            anchor_render_component( 'button', array_merge( [
                'variant' => 'ghost',
                'icon'    => 'arrow-right',
            ], $cta ) );
            ?>
        </div>
    <?php endif; ?>

</div>
</div>
