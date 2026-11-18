<!-- Start Review card -->
<div class="<?php echo esc_attr( $card_classes ) ?>"> <?php

	if($ekit_review_card_top_right_logo == 'yes') { ?>
        <!-- Top right logo -->
        <div class="ekit-review-card--top-right-logo">
            <?php
                $migrated = isset( $settings['__fa4_migrated']['controls_section_top_right_logo_icons'] );
                $is_new = empty( $controls_section_top_right_logo_icon );
                if ( $is_new || $migrated ) :
                    \Elementor\Icons_Manager::render_icon( $controls_section_top_right_logo_icons, [ 'aria-hidden' => 'true'] );
                else : ?>
                    <i class="<?php echo esc_attr( $controls_section_top_right_logo_icon ); ?>" aria-hidden="true"></i>
                <?php endif;
            ?>
        </div> <?php
	} ?>

    <!-- Start Thumbnail -->
    <div class="ekit-review-card--thumbnail <?php echo($thumbnail_badge ? 'ekit-review-card--thumbnail-badge' : '') ?>">
        <div>
            <img class="thumbnail" src="<?php echo esc_url( $this->get_user_thumbnail($item->user->image_url) ) ?>" alt="Yelp Thumbnail"> <?php

            if($thumbnail_badge) { ?>
                <div class="badge">
                    <svg class="ekit-yelp-badge-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false">
                        <path transform="translate(-798.567 -613.605)" d="M812.16,631.405a1.151,1.151,0,0,1-.226,2.153l-5.423,1.354a1.36,1.36,0,0,1-.279.034,1.176,1.176,0,0,1-1.143-1.023c-.036-.311-.066-.82-.066-1.133a11.952,11.952,0,0,1,.52-3.17,1.228,1.228,0,0,1,1.091-.783,1.364,1.364,0,0,1,.5.116Zm1.612-14.567a.849.849,0,0,1,.09,0,1.146,1.146,0,0,1,1.146,1.143v10.518a1.153,1.153,0,0,1-2.15.576l-5.252-9.1a1.367,1.367,0,0,1-.153-.575,1.258,1.258,0,0,1,.654-1.039A19.263,19.263,0,0,1,813.772,616.838Zm-4.918,23.233a1.316,1.316,0,0,1,.3-.768l3.743-4.157a1.152,1.152,0,0,1,2.006.812l-.2,5.591a1.157,1.157,0,0,1-1.149,1.109,1.287,1.287,0,0,1-.194-.016,12,12,0,0,1-4.006-1.619A1.288,1.288,0,0,1,808.854,640.071Zm9.169-6.3,5.316,1.729a1.232,1.232,0,0,1,.8,1.1,1.346,1.346,0,0,1-.11.489,11.936,11.936,0,0,1-2.658,3.41,1.351,1.351,0,0,1-.736.263,1.291,1.291,0,0,1-.98-.539l-2.965-4.742A1.157,1.157,0,0,1,818.023,633.766Zm6.147-4.96a1.41,1.41,0,0,1,.094.463,1.227,1.227,0,0,1-.831,1.112l-5.378,1.538a1.151,1.151,0,0,1-1.272-1.751l3.126-4.635a1.29,1.29,0,0,1,.955-.507,1.337,1.337,0,0,1,.76.286,11.834,11.834,0,0,1,2.542,3.494Z"/>
                    </svg>
                </div> <?php
            } ?>
        </div>
    </div>
    <!-- End Thumbnail -->

    <h5 class="ekit-review-card--name">
		<?php echo empty($item->user->name) ? esc_html__('Anonymous', 'elementskit') : esc_html( $item->user->name ); ?>
    </h5>
    <p class='ekit-review-card--date small muted'><?php echo esc_html( gmdate('d M, Y', $time) ); ?></p>

    <!-- Start Rating stars -->
    <div class="ekit-review-card--stars">
        <?php echo $this->get_stars_rating($item->rating) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns static star markup with no user data. ?>
    </div>
    <!-- End Rating stars -->

    <p class="ekit-review-card--comment">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_formatted_text() escapes via esc_html()/esc_attr(); else branch esc_html()'d.
        echo $format_comment ? $this->get_formatted_text($item->text, true) : esc_html( $item->text );
        ?>
    </p>

	<?php

    if($ekit_review_card_posted_on == 'yes') { ?>

        <div class="ekit-review-card--posted-on">
            <?php 
                $migrated = isset( $settings['__fa4_migrated']['ekit_yelp_review_posted_on_icons'] );
                $is_new = empty( $ekit_yelp_review_posted_on_icon );
                if ( $is_new || $migrated ) :
                    \Elementor\Icons_Manager::render_icon( $ekit_yelp_review_posted_on_icons, [ 'aria-hidden' => 'true'] );
                else : ?>
                    <i class="<?php echo esc_attr( $ekit_yelp_review_posted_on_icon ); ?>" aria-hidden="true"></i>
                <?php endif;
            ?>
            <div>
                <p class="small muted">
                    <?php echo esc_html__('Posted on', 'elementskit')?>
                </p>
                <h5 class='text-bold'><?php echo esc_html__('Yelp', 'elementskit')?></h5>
            </div>
        </div> <?php

    } ?>

</div>
<!-- End Review card -->
