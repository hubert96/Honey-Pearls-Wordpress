<?php 

    // Facebook stopped returning the reviewer on the ratings edge, so no review
    // ever carries a name or a picture. The placeholder is the only option, and
    // building a Graph URL for it would have put the page token in the markup.
    // With 'Hide profile of review' on, neither is printed at all.
    $user_thumbnail = $handler_url . 'assets/images/profile-placeholder.svg';
    $show_profile   = empty($hide_profile);

?>

<!-- Start Review card -->
<div class="<?php echo esc_attr($card_classes) ?>"> <?php

	if($ekit_review_card_top_right_logo == 'yes') { ?>
        <!-- Top right logo -->
        <div class="ekit-review-card--top-right-logo">
            <?php \Elementor\Icons_Manager::render_icon( 
                    $controls_section_top_right_logo_icons, [ 
                        'aria-hidden' => 'true'
                    ] 
            )?>
        </div> <?php
	} ?>

	<?php if($show_profile) { ?>
    <!-- Start Thumbnail -->
    <div class="ekit-review-card--thumbnail <?php echo($thumbnail_badge ? esc_html__('ekit-review-card--thumbnail-badge', 'elementskit') : '') ?>">
        <div>
            <img class="thumbnail" src="<?php echo esc_url($user_thumbnail) ?>" alt="<?php echo esc_attr__( 'Profile picture', 'elementskit' ); ?>" />
            <?php if($thumbnail_badge) { ?>
                <div class="badge">
					<img src="<?php echo esc_url($handler_url) . esc_attr('assets/svg/fb-logo-f.svg'); ?>" alt="<?php echo esc_attr__( 'Facebook', 'elementskit' ); ?>">
                </div> <?php
            } ?>
        </div>
    </div>
    <!-- End Thumbnail -->

    <h5 class="ekit-review-card--name">
		<?php echo empty($item->reviewer->name) ? esc_html__('Anonymous', 'elementskit') : esc_html($item->reviewer->name); ?>
    </h5>
	<?php } ?>
    <p class='ekit-review-card--date small muted'><?php echo esc_html(gmdate('d M, Y', $time)); ?></p>

    <!-- Start Rating stars -->
    <div class="ekit-review-card--stars">
        <?php
            // A positive recommendation shows five filled stars, a negative one a single filled star.
            echo $this->get_stars_rating($star_icon === 'icon-star-1' ? 5 : 1); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>
    <!-- End Rating stars -->

    <?php
        if( isset($ekit_review_card_align_center) && $ekit_review_card_align_center === 'yes' ) {
            $comment_text_align = ' ekit-review-card-align-center';
        }
    ?>

    <p class="ekit-review-card--comment <?php echo esc_attr($comment_text_align); ?>">
        <?php 
        
        $txt = empty($item->review_text) ? '' : esc_html__($item->review_text, 'elementskit');  //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText

        echo $format_comment ? $this->get_formatted_text($txt, true) : $txt; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </p>

	<?php

    if($ekit_review_card_posted_on == 'yes') { ?>

        <div class="ekit-review-card--posted-on">
            <?php \Elementor\Icons_Manager::render_icon( 
                $ekit_fb_review_posted_on_icons, [ 
                    'aria-hidden' => 'true'
                ]
            )?>
            <div>
                <p class="small muted"><?php echo esc_html__('Posted on', 'elementskit')?></p>
                <h5 class='text-bold'><?php echo esc_html__('Facebook', 'elementskit')?></h5>
            </div>
        </div> <?php

    } ?>

</div>
<!-- End Review card -->
