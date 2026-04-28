<?php
/**
 * Section: FAQ List
 *
 * Accordion FAQ section. Each item has a question and answer. Supports
 * single-column or two-column layout.
 *
 * Data (via anchor_get_template_data):
 *   eyebrow        (string|null) Eyebrow badge text.
 *   heading        (string|null) Section heading.
 *   heading_accent (string|null) Word(s) to italicize in brand color.
 *   items          (array)       Each: question (string), answer (string).
 *   columns        (int)         1 or 2 column layout. Default 1.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Defaults.
$eyebrow        = isset( $props['eyebrow'] )        ? $props['eyebrow']        : null;
$heading        = isset( $props['heading'] )         ? $props['heading']        : null;
$heading_accent = isset( $props['heading_accent'] )  ? $props['heading_accent'] : null;
$items          = ! empty( $props['items'] ) && is_array( $props['items'] ) ? $props['items'] : [];
$columns        = ! empty( $props['columns'] )       ? (int) $props['columns'] : 1;

if ( empty( $items ) ) {
    return;
}

// For two-column layout, split items into two groups.
$use_grid   = ( 2 === $columns && count( $items ) > 1 );
$col1_items = $items;
$col2_items = [];

if ( $use_grid ) {
    $mid        = (int) ceil( count( $items ) / 2 );
    $col1_items = array_slice( $items, 0, $mid );
    $col2_items = array_slice( $items, $mid );
}
?>
<div class="anchor-section-pad">
    <div class="anchor-container">

        <?php if ( $heading ) : ?>
            <div class="anchor-text-center anchor-max-w-2xl anchor-mx-auto anchor-section-intro--md">
                <?php
                anchor_render_component( 'heading-group', [
                    'eyebrow'        => $eyebrow,
                    'heading'        => $heading,
                    'heading_accent' => $heading_accent,
                    'align'          => 'center',
                ] );
                ?>
            </div>
        <?php endif; ?>

        <div class="anchor-max-w-3xl anchor-mx-auto anchor-faq__list">
            <?php if ( $use_grid ) : ?>
                <div class="anchor-grid anchor-grid--2 anchor-faq__grid">
                    <div>
                        <?php foreach ( $col1_items as $item ) : ?>
                            <?php if ( empty( $item['question'] ) ) continue; ?>
                            <div class="anchor-faq-item">
                                <button class="anchor-faq-item__question" type="button" aria-expanded="false">
                                    <span><?php echo esc_html( $item['question'] ); ?></span>
                                    <span class="anchor-faq-item__icon"><?php echo anchor_icon( 'chevron-down', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                </button>
                                <div class="anchor-faq-item__answer" aria-hidden="true">
                                    <div class="anchor-faq-item__answer-inner">
                                        <?php echo anchor_esc_content( $item['answer'] ?? '' ); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <?php foreach ( $col2_items as $item ) : ?>
                            <?php if ( empty( $item['question'] ) ) continue; ?>
                            <div class="anchor-faq-item">
                                <button class="anchor-faq-item__question" type="button" aria-expanded="false">
                                    <span><?php echo esc_html( $item['question'] ); ?></span>
                                    <span class="anchor-faq-item__icon"><?php echo anchor_icon( 'chevron-down', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                </button>
                                <div class="anchor-faq-item__answer" aria-hidden="true">
                                    <div class="anchor-faq-item__answer-inner">
                                        <?php echo anchor_esc_content( $item['answer'] ?? '' ); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else : ?>
                <?php foreach ( $items as $item ) : ?>
                    <?php if ( empty( $item['question'] ) ) continue; ?>
                    <div class="anchor-faq-item">
                        <button class="anchor-faq-item__question" type="button" aria-expanded="false">
                            <span><?php echo esc_html( $item['question'] ); ?></span>
                            <span class="anchor-faq-item__icon"><?php echo anchor_icon( 'chevron-down', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        </button>
                        <div class="anchor-faq-item__answer" aria-hidden="true">
                            <div class="anchor-faq-item__answer-inner">
                                <?php echo anchor_esc_content( $item['answer'] ?? '' ); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>
