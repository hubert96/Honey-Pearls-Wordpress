<?php
namespace HoneyPearls\Facebook;
defined('ABSPATH') || exit;
function card_data(): array {
    $saved=get_option('hp_fb_settings', []);
    $raw=$saved['cards'] ?? json_decode(file_get_contents(__DIR__.'/cards.json'),true);
    return is_array($raw) ? $raw : ['posts'=>[]];
}
function card_icon(string $name): string {
    if ($name==='comment' || $name==='share') return '<span aria-hidden="true" class="hp-fb-icon hp-fb-icon-'.$name.'"></span>';
    return '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="M7 10v11H3V10h4Zm0 0 5-8c2 0 3 1 2 4l-1 4h6a2 2 0 0 1 2 2l-2 7a3 3 0 0 1-3 2H7"/></svg>';
}
function render_card(array $post, string $captured): void {
    $url=post_url((string)($post['url'] ?? '')); if(!$url)return;
    $profile='https://www.facebook.com/honeypearls.patisserie';
    $text=(string)($post['text'] ?? ''); $parts=preg_split('/\R\R/', $text, 2);
    ?>
    <div class="hp-fb-card">
      <header class="hp-fb-header"><a href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer"><img class="hp-fb-avatar" src="<?php echo esc_url(plugins_url('assets/profile.jpg',FILE)); ?>" alt="Honey Pearls" width="40" height="40"></a><div><a class="hp-fb-name" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer">Honey Pearls - Greek Patisserie &amp; Bakery</a><a class="hp-fb-date" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($post['date'] ?? ''); ?> · <svg aria-label="Public post" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><ellipse cx="12" cy="12" rx="4" ry="10"/><path d="M2 12h20"/></svg></a></div><a class="hp-fb-brand" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open original Facebook post"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="#0866ff"/><path fill="white" d="M13.7 24v-9.3h3.1l.5-3.6h-3.6V8.8c0-1 .3-1.7 1.8-1.7h1.9V3.9a25 25 0 0 0-2.8-.2c-2.8 0-4.7 1.7-4.7 4.9v2.5H6.8v3.6h3.1V24z"/></svg></a></header>
      <div class="hp-fb-caption"><p><?php echo esc_html($parts[0]); ?></p><?php if(isset($parts[1])): ?><details><summary>See more</summary><p><?php echo nl2br(esc_html($parts[1])); ?></p></details><?php endif; ?></div>
      <a class="hp-fb-photo" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url($post['image'] ?? ''); ?>" alt="<?php echo esc_attr($parts[0]); ?>" loading="lazy"></a>
      <div class="hp-fb-reactions" title="<?php echo esc_attr('Last checked '.$captured); ?>"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo (int)($post['reactions'] ?? 0); ?> reactions on Facebook"><span class="hp-fb-reaction-icons"><img src="<?php echo esc_url(plugins_url('assets/like-2.svg',FILE)); ?>" alt="Like" width="18" height="18"><?php if(!empty($post['love'])): ?><img src="<?php echo esc_url(plugins_url('assets/love.svg',FILE)); ?>" alt="Love" width="18" height="18"><?php endif; ?></span><?php echo number_format_i18n((int)($post['reactions'] ?? 0)); ?></a><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo (int)($post['comment_count'] ?? 0); ?> comments · <?php echo (int)($post['shares'] ?? 0); ?> shares</a></div>
      <div class="hp-fb-actions"><?php foreach(['like'=>'Like','comment'=>'Comment','share'=>'Share'] as $icon=>$label): ?><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($label.' on Facebook'); ?>"><?php echo card_icon($icon); ?><span><?php echo esc_html($label); ?></span></a><?php endforeach; ?></div>
      <?php if(!empty($post['comments'])): ?><div class="hp-fb-comments" aria-label="Comments from Facebook"><?php foreach(array_slice($post['comments'],0,2) as $comment): ?><div class="hp-fb-comment"><span class="hp-fb-initial" aria-hidden="true"><?php echo esc_html(mb_substr((string)$comment['author'],0,1)); ?></span><div class="hp-fb-bubble"><strong><?php echo esc_html($comment['author']); ?></strong><p><?php echo esc_html($comment['text']); ?></p></div></div><?php endforeach; ?></div><?php endif; ?>
    </div><?php
}
