<?php
/**
 * Section: Team Grid
 *
 * Displays team members as a photo card grid or as full alternating bios.
 *
 * Layouts:
 *   'grid' — Simple photo card grid (homepage-style).
 *   'bios' — Full alternating bio layout (about-page-style).
 *
 * Props (via anchor_get_template_data):
 *   eyebrow        (string)  Optional eyebrow text.
 *   heading        (string)  Section heading.
 *   heading_accent (string)  Accent/italic portion of the heading.
 *   text           (string)  Optional description text.
 *   columns        (int)     Grid columns for 'grid' layout. Default 4.
 *   members        (array)   Array of member data.
 *   layout         (string)  'grid' or 'bios'. Default 'grid'.
 *   cta            (array)   Optional CTA link data.
 *
 * Member data shape:
 *   name   (string)  Full name.
 *   role   (string)  Job title / role.
 *   photo  (string)  Photo URL.
 *   url    (string)  Optional link URL.
 *   slug   (string)  URL slug for anchor IDs (bios layout).
 *   bio    (mixed)   String or array of paragraphs (bios layout).
 *   quote  (string)  Optional blockquote text (bios layout).
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$variant = ! empty( $section['variant'] ) ? $section['variant'] : '';
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Extract props with defaults.
$eyebrow        = ! empty( $props['eyebrow'] )        ? $props['eyebrow']        : '';
$heading        = ! empty( $props['heading'] )        ? $props['heading']        : '';
$heading_accent = ! empty( $props['heading_accent'] ) ? $props['heading_accent'] : '';
$text           = ! empty( $props['text'] )           ? $props['text']           : '';
$columns        = ! empty( $props['columns'] )        ? (int) $props['columns']  : 4;
$members        = ! empty( $props['members'] )        ? $props['members']        : [];
$layout         = ! empty( $props['layout'] )         ? $props['layout']         : 'grid';
$cta            = ! empty( $props['cta'] )            ? $props['cta']            : null;

if ( empty( $members ) ) {
    return;
}

// ------------------------------------------------------------------
// Layout: grid (simple photo card grid)
// ------------------------------------------------------------------
if ( 'grid' === $layout ) : ?>

    <div class="anchor-section-pad">
    <div class="anchor-container">

        <?php // Centered heading group. ?>
        <?php if ( $heading || $eyebrow ) : ?>
            <div class="anchor-text-center anchor-section-intro">
                <?php
                anchor_render_component( 'heading-group', [
                    'eyebrow'        => $eyebrow,
                    'heading'        => $heading,
                    'heading_accent' => $heading_accent,
                    'tag'            => 'h2',
                    'align'          => 'center',
                ] );
                ?>

                <?php if ( $text ) : ?>
                    <p class="anchor-text-muted anchor-team-grid__text"><?php echo anchor_esc_content( $text ); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php // Team card grid. ?>
        <div class="anchor-grid anchor-grid--<?php echo esc_attr( $columns ); ?> anchor-reveal-stagger">
            <?php foreach ( $members as $member ) :
                $name  = ! empty( $member['name'] )  ? $member['name']  : '';
                $role  = ! empty( $member['role'] )  ? $member['role']  : '';
                $photo = ! empty( $member['photo'] ) ? $member['photo'] : '';
                $url   = ! empty( $member['url'] )   ? $member['url']   : '';

                $tag_open  = $url ? '<a href="' . esc_url( $url ) . '" class="anchor-team-card">' : '<div class="anchor-team-card">';
                $tag_close = $url ? '</a>' : '</div>';
            ?>
                <?php echo $tag_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="anchor-team-card__image">
                        <?php if ( $photo ) : ?>
                            <img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="anchor-team-card__overlay"></div>
                        <div class="anchor-team-card__info">
                            <?php if ( $name ) : ?>
                                <h3 class="anchor-team-card__name"><?php echo esc_html( $name ); ?></h3>
                            <?php endif; ?>
                            <?php if ( $role ) : ?>
                                <p class="anchor-team-card__role"><?php echo esc_html( $role ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php echo $tag_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
        </div>

        <?php // CTA link. ?>
        <?php if ( $cta && ! empty( $cta['label'] ) ) : ?>
            <div class="anchor-section-cta">
                <?php
                $cta_data = array_merge( [
                    'variant' => 'ghost',
                    'icon'    => 'arrow-right',
                ], $cta );
                anchor_render_component( 'button', $cta_data );
                ?>
            </div>
        <?php endif; ?>

    </div>
    </div>

<?php
// ------------------------------------------------------------------
// Layout: bios (full alternating bio layout)
// ------------------------------------------------------------------
else : ?>

    <div class="anchor-section-pad">
    <div class="anchor-container">

        <?php // Centered heading group. ?>
        <?php if ( $heading || $eyebrow ) : ?>
            <div class="anchor-text-center anchor-section-intro--lg">
                <?php
                anchor_render_component( 'heading-group', [
                    'eyebrow'        => $eyebrow,
                    'heading'        => $heading,
                    'heading_accent' => $heading_accent,
                    'tag'            => 'h2',
                    'align'          => 'center',
                ] );
                ?>

                <?php if ( $text ) : ?>
                    <p class="anchor-text-muted anchor-team-grid__text"><?php echo anchor_esc_content( $text ); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php // Bio entries. ?>
        <div class="anchor-team-bios">
            <?php foreach ( $members as $index => $member ) :
                $name  = ! empty( $member['name'] )  ? $member['name']  : '';
                $role  = ! empty( $member['role'] )  ? $member['role']  : '';
                $photo = ! empty( $member['photo'] ) ? $member['photo'] : '';
                $slug  = ! empty( $member['slug'] )  ? $member['slug']  : sanitize_title( $name );
                $bio   = isset( $member['bio'] )     ? $member['bio']   : '';
                $quote = ! empty( $member['quote'] ) ? $member['quote'] : '';

                $is_reversed = ( $index % 2 === 1 );
                $grid_class  = 'anchor-grid anchor-team-bio__grid' . ( $is_reversed ? ' anchor-team-bio__grid--reversed' : '' );
            ?>
                <div class="anchor-team-bio" id="<?php echo esc_attr( $slug ); ?>">
                    <div class="<?php echo esc_attr( $grid_class ); ?>">

                        <?php // Photo column. ?>
                        <div class="anchor-team-bio__photo-col">
                            <div class="anchor-team-bio__photo">
                                <?php if ( $photo ) : ?>
                                    <img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy">
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php // Content column. ?>
                        <div class="anchor-team-bio__content-col">
                            <?php if ( $role ) : ?>
                                <div class="anchor-badge anchor-badge--brand anchor-team-bio__role"><?php echo esc_html( $role ); ?></div>
                            <?php endif; ?>

                            <?php if ( $name ) : ?>
                                <h3 class="anchor-team-bio__name"><?php echo esc_html( $name ); ?></h3>
                            <?php endif; ?>

                            <?php // Bio paragraphs. ?>
                            <?php if ( ! empty( $bio ) ) : ?>
                                <?php if ( is_array( $bio ) ) : ?>
                                    <?php foreach ( $bio as $paragraph ) : ?>
                                        <p class="anchor-text-muted anchor-team-bio__paragraph"><?php echo anchor_esc_content( $paragraph ); ?></p>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="anchor-text-muted anchor-team-bio__paragraph"><?php echo anchor_esc_content( $bio ); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php // Quote. ?>
                            <?php if ( $quote ) : ?>
                                <blockquote class="anchor-team-bio__quote">
                                    <p class="anchor-text-drama anchor-team-bio__quote-text"><?php echo anchor_esc_content( $quote ); ?></p>
                                </blockquote>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php // CTA link. ?>
        <?php if ( $cta && ! empty( $cta['label'] ) ) : ?>
            <div class="anchor-section-cta">
                <?php
                $cta_data = array_merge( [
                    'variant' => 'ghost',
                    'icon'    => 'arrow-right',
                ], $cta );
                anchor_render_component( 'button', $cta_data );
                ?>
            </div>
        <?php endif; ?>

    </div>
    </div>

<?php endif; ?>
