<?php
defined('ABSPATH') or die('No script kiddies please!');
?>
<h1 class="ti-header-title"><?php echo esc_html(__('Want to get more customers and followers?', 'widgets-for-social-post-feed')); ?></h1>
<div class="ti-box">
<div class="ti-box-header"><?php
/* translators: %s: Platform name */
echo esc_html(sprintf(__('Increase SEO, trust and sales using %s feeds.', 'widgets-for-social-post-feed'), 'Facebook'));
?></div>
<a class="ti-btn" href="https://www.trustindex.io?url=/feed-widgets&a=sys&c=<?php echo esc_attr($tiCampaign1); ?>" target="_blank"><?php echo esc_html(__('Create a Free Account for More Features', 'widgets-for-social-post-feed')); ?></a>
<p class="ti-mt-1"><?php echo esc_html(__('Get more features with a professional package:', 'widgets-for-social-post-feed')); ?></p>
<ul class="ti-seo-list">
<li>
<strong><?php echo esc_html(__('Display unlimited number of posts', 'widgets-for-social-post-feed')); ?></strong><br />
<?php
/* translators: %s: Platform name */
echo esc_html(sprintf(__('You can test Trustindex with 10 posts in the free version. Upgrade to a subscription to display ALL your %s posts.', 'widgets-for-social-post-feed'), 'Facebook'));
?>
</li>
<li>
<strong><?php echo esc_html(__('Create unlimited number of feed widgets', 'widgets-for-social-post-feed')); ?></strong><br />
<?php echo esc_html(__('Build trust by using widgets that suit your website best.', 'widgets-for-social-post-feed')); ?>
</li>
<li>
<strong><?php
/* translators: %d: number of available feed platforms */
echo esc_html(sprintf(__("%d social feed platforms", 'widgets-for-social-post-feed'), 9));
?></strong><br />
<?php
/* translators: %s: platform names comma separated */
echo esc_html(sprintf(__('Add more posts to your widget from %s, etc. to enjoy more social content and to keep customers on your site for longer.', 'widgets-for-social-post-feed'), 'Facebook, Instagram, Twitter, TikTok, Google, YouTube, Pinterest, LinkedIn, Vimeo'));
?><br />
<?php echo wp_kses_post($pluginManagerInstance->displayImg('assets/img/platforms.svg', array('style' => 'margin-top: 5px; height: 30px'))); ?>
</li>
<li>
<strong><?php echo esc_html(__('Mix social feeds', 'widgets-for-social-post-feed')); ?></strong><br />
<?php echo esc_html(__('You can mix your posts from different platforms and display them in 1 feed widget.', 'widgets-for-social-post-feed')); ?>
</li>
<li>
<strong><?php echo esc_html(__('Higher SEO', 'widgets-for-social-post-feed')); ?></strong><br />
<?php echo esc_html(__('Your premium widgets will update every day with fresh content, so Google crawls your site more frequently - improving your SEO and search ranking in the long run.', 'widgets-for-social-post-feed')); ?>
</li>
<li>
<strong><?php echo esc_html(__('Get more followers', 'widgets-for-social-post-feed')); ?></strong><br />
<?php echo esc_html(__("With a Follow button in your widget header, you'll attract more and more followers, right on your website.", 'widgets-for-social-post-feed')); ?>
</li>
<li>
<strong><?php echo esc_html(__('Easy setup', 'widgets-for-social-post-feed')); ?></strong><br />
<?php echo esc_html(__('No need to have programming or designing skills to create eye-catching feed widgets.', 'widgets-for-social-post-feed')); ?>
</li>
<li>
<strong><?php echo esc_html(__('Hide any widget element', 'widgets-for-social-post-feed')); ?></strong><br />
<?php echo esc_html(__("Don't want to display a header, caption, comments and likes? No problem, you can easily hide multiple widget elements!", 'widgets-for-social-post-feed')); ?>
</li>
<li>
<strong><?php echo esc_html(__('Wide customization', 'widgets-for-social-post-feed')); ?></strong><br />
<?php echo esc_html(__('Adjust every element of the feed widget to your brand: style, color, size, fonts and more.', 'widgets-for-social-post-feed')); ?>
</li>
</ul>
<a class="ti-btn" href="https://www.trustindex.io?url=/feed-widget?a=sys&c=<?php echo esc_attr($tiCampaign2); ?>" target="_blank"><?php echo esc_html(__('Create a Free Account for More Features', 'widgets-for-social-post-feed')); ?></a>
<div class="ti-special-offer">
<?php echo wp_kses_post($pluginManagerInstance->displayImg('assets/img/special_30.jpg')); ?>
<p><?php echo esc_html(__('Now we offer you a 30%% discount off your subscription! Create your free account and benefit from the onboarding discount now!', 'widgets-for-social-post-feed')); ?></p>
<div class="clear"></div>
</div>
</div>
