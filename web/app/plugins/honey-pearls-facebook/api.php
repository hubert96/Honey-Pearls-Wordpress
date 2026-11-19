<?php
namespace HoneyPearls\Facebook;
defined('ABSPATH') || exit;
function seal_token(string $token): string {
    $iv=random_bytes(12);$encrypted=openssl_encrypt($token,'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,$iv,$tag);
    if($encrypted===false)throw new \RuntimeException('Could not encrypt Facebook credentials.');
    return base64_encode($iv.$tag.$encrypted);
}
function unseal_token(string $value): string {
    $raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)return '';
    return (string)openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
}
function api_response($response): array {
    if(is_wp_error($response))throw new \RuntimeException('Facebook could not be reached. Previous posts have been kept.');
    $body=json_decode(wp_remote_retrieve_body($response),true);$http=wp_remote_retrieve_response_code($response);
    if($http!==200||!is_array($body)||isset($body['error'])) {
        $code=(int)($body['error']['code']??0);
        throw new \RuntimeException($code===190?'Facebook authorization expired or was revoked. Reconnect Facebook.':'Facebook API failed (HTTP '.(int)$http.', code '.$code.'). Check Page permissions; previous posts have been kept.');
    }
    return $body;
}
function graph(string $path,string $token,array $params=[]): array {
    return api_response(wp_remote_get(add_query_arg($params,'https://graph.facebook.com/v27.0/'.ltrim($path,'/')),['timeout'=>25,'redirection'=>0,'headers'=>['Authorization'=>'Bearer '.$token]]));
}
function callback_url(): string { return \admin_url('admin-post.php?action=hp_facebook_callback'); }
function app_credentials(): array {
    $ig=(array)get_option('hp_ig_settings',[]);
    return ['id'=>(string)($ig['app_id']??''),'secret'=>unseal_token((string)get_option('hp_ig_app_secret',''))];
}
function api_status(string $message): void {update_option('hp_fb_status',['message'=>$message,'time'=>time()],false);}
function api_form(string $command,string $label): void {
    echo '<form method="post" action="'.esc_url(\admin_url('admin-post.php')).'" style="display:inline-block;margin:0 8px 12px 0">';
    wp_nonce_field('hp_facebook');echo '<input type="hidden" name="action" value="hp_facebook"><input type="hidden" name="command" value="'.esc_attr($command).'">';submit_button($label,'secondary','submit',false);echo '</form>';
}
function api_action(): void {
    if(!current_user_can('manage_options'))wp_die('Administrator access required.','',['response'=>403]);
    check_admin_referer('hp_facebook');$command=sanitize_key(wp_unslash($_POST['command']??''));
    try {
        if($command==='connect') {
            $app=app_credentials();if(!$app['id']||!$app['secret'])throw new \RuntimeException('Save the Meta App ID and App Secret in Honey Pearls Instagram first.');
            $state=bin2hex(random_bytes(32));update_user_meta(get_current_user_id(),'hp_fb_oauth',['hash'=>hash('sha256',$state),'expires'=>time()+600]);
            $args=['client_id'=>$app['id'],'redirect_uri'=>callback_url(),'state'=>$state,'response_type'=>'code','scope'=>'pages_show_list,pages_read_engagement,pages_read_user_content'];
            $config=(string)get_option('hp_fb_config_id','');if($config!==''){$args['config_id']=$config;$args['override_default_response_type']='true';unset($args['scope']);}
            wp_redirect(add_query_arg($args,'https://www.facebook.com/v27.0/dialog/oauth'));exit;
        } elseif($command==='config') {
            update_option('hp_fb_config_id',preg_replace('/[^0-9]/','',(string)wp_unslash($_POST['config_id']??'')),false);api_status('Facebook login configuration saved.');
        } elseif($command==='sync') {sync_feed();}
        elseif($command==='disconnect') {delete_option('hp_fb_auth');wp_clear_scheduled_hook('hp_fb_sync');api_status('Disconnected. Last saved posts remain available.');}
    }catch(\Throwable $e){api_status($e->getMessage());}
    wp_safe_redirect(\admin_url('options-general.php?page=hp-facebook'));exit;
}
function oauth_callback(): void {
    if(!current_user_can('manage_options'))wp_die('Administrator access required.','',['response'=>403]);
    $pending=(array)get_user_meta(get_current_user_id(),'hp_fb_oauth',true);delete_user_meta(get_current_user_id(),'hp_fb_oauth');
    try {
        $state=(string)wp_unslash($_GET['state']??'');
        if(empty($pending['hash'])||($pending['expires']??0)<time()||!hash_equals($pending['hash'],hash('sha256',$state)))throw new \RuntimeException('Facebook login expired or state was invalid. Start again.');
        $code=(string)wp_unslash($_GET['code']??'');if(!$code)throw new \RuntimeException('Facebook login was cancelled or no authorization code was returned.');
        $app=app_credentials();
        $token=api_response(wp_remote_get(add_query_arg(['client_id'=>$app['id'],'client_secret'=>$app['secret'],'redirect_uri'=>callback_url(),'code'=>$code],'https://graph.facebook.com/v27.0/oauth/access_token'),['timeout'=>25,'redirection'=>0]));
        if(empty($token['access_token']))throw new \RuntimeException('Facebook did not return an access token.');
        $long=api_response(wp_remote_get(add_query_arg(['client_id'=>$app['id'],'client_secret'=>$app['secret'],'grant_type'=>'fb_exchange_token','fb_exchange_token'=>$token['access_token']],'https://graph.facebook.com/v27.0/oauth/access_token'),['timeout'=>25,'redirection'=>0]));
        if(empty($long['access_token']))throw new \RuntimeException('Facebook did not return a long-lived token.');
        $pages=[];$after=null;
        for($i=0;$i<10;$i++) {
            $params=['fields'=>'id,name,access_token','limit'=>100];if($after)$params['after']=$after;
            $result=graph('me/accounts',$long['access_token'],$params);$pages=array_merge($pages,$result['data']??[]);
            $after=!empty($result['paging']['next'])?($result['paging']['cursors']['after']??null):null;if(!$after)break;
        }
        $matches=array_values(array_filter($pages,fn($page)=>stripos($page['name']??'','Honey Pearls')!==false && !empty($page['access_token'])));
        if(count($matches)!==1)throw new \RuntimeException('Facebook did not return exactly one Honey Pearls Page. Reconnect and select only the Honey Pearls Page with read permissions.');
        $page=$matches[0];if(!ctype_digit((string)$page['id']))throw new \RuntimeException('Facebook returned an invalid Page ID.');
        update_option('hp_fb_auth',['page_id'=>(string)$page['id'],'page_name'=>(string)$page['name'],'token'=>seal_token($page['access_token']),'connected'=>time()],false);
        sync_feed();
    }catch(\Throwable $e){api_status($e->getMessage());}
    wp_safe_redirect(\admin_url('options-general.php?page=hp-facebook'));exit;
}
function normalize_post(array $item): array {
    $url=post_url((string)($item['permalink_url']??''));if(!$url)return [];
    $comments=[];
    foreach(($item['comments']['data']??[]) as $c){if(!empty($c['is_hidden'])||empty($c['message']))continue;$comments[]=['author'=>(string)($c['from']['name']??'Facebook user'),'text'=>(string)$c['message']];if(count($comments)===2)break;}
    $time=strtotime($item['created_time']??'');$image=(string)($item['full_picture']??'');
    $host=strtolower((string)parse_url($image,PHP_URL_HOST));
    if(parse_url($image,PHP_URL_SCHEME)!=='https'||!str_ends_with($host,'.fbcdn.net'))$image='';
    return ['url'=>$url,'date'=>$time?gmdate('j F Y',$time):'','timestamp'=>$time?:0,'image'=>$image,'text'=>(string)($item['message']??''),'reactions'=>isset($item['reactions']['summary']['total_count'])?(int)$item['reactions']['summary']['total_count']:null,'comment_count'=>isset($item['comments']['summary']['total_count'])?(int)$item['comments']['summary']['total_count']:null,'shares'=>(int)($item['shares']['count']??0),'love'=>false,'comments'=>$comments];
}
function sync_feed(): void {
    $auth=(array)get_option('hp_fb_auth',[]);$token=unseal_token((string)($auth['token']??''));
    if(!$token||empty($auth['page_id']))throw new \RuntimeException('Connect Facebook before synchronizing.');
    if(get_transient('hp_fb_sync_lock'))return;set_transient('hp_fb_sync_lock',1,120);
    try {
        $r=graph($auth['page_id'].'/published_posts',$token,['fields'=>'id,permalink_url,message,created_time,full_picture,reactions.limit(0).summary(true),comments.limit(5).summary(true){id,from,message,is_hidden},shares','limit'=>12]);
        if(!isset($r['data'])||!is_array($r['data']))throw new \RuntimeException('Facebook returned an incomplete feed. Previous posts have been kept.');
        $posts=array_values(array_filter(array_map(__NAMESPACE__.'\\normalize_post',$r['data'])));
        if($r['data']&&!$posts)throw new \RuntimeException('Facebook returned unsupported post links. Previous posts have been kept.');
        usort($posts,fn($a,$b)=>$b['timestamp']<=>$a['timestamp']);
        update_option('hp_fb_feed',['source'=>'api','captured_at'=>gmdate('Y-m-d H:i').' UTC','synced_at'=>time(),'posts'=>$posts],false);
        api_status('Connected to '.$auth['page_name'].'. Synchronized '.count($posts).' posts from Facebook.');
    }finally{delete_transient('hp_fb_sync_lock');}
}
add_filter('cron_schedules',function($s){$s['hp_fb_quarter_hour']=['interval'=>900,'display'=>'Every 15 minutes'];return $s;});
add_action('init',function(){if(!empty(get_option('hp_fb_auth',[])['token'])&&!wp_next_scheduled('hp_fb_sync'))wp_schedule_event(time()+60,'hp_fb_quarter_hour','hp_fb_sync');});
add_action('hp_fb_sync',function(){try{sync_feed();}catch(\Throwable $e){api_status($e->getMessage());}});
add_action('admin_post_hp_facebook',__NAMESPACE__.'\\api_action');
add_action('admin_post_hp_facebook_callback',__NAMESPACE__.'\\oauth_callback');
function api_admin(): void {
    $auth=(array)get_option('hp_fb_auth',[]);$status=(array)get_option('hp_fb_status',[]);$feed=(array)get_option('hp_fb_feed',[]);
    echo '<h2>Automatic Facebook connection</h2><p>'.esc_html(empty($auth['token'])?'Not connected — saved previews are shown.':'Connected Page: '.($auth['page_name']??'')).'</p>';
    if(!empty($status['message']))echo '<p><strong>'.esc_html($status['message']).'</strong></p>';
    if(!empty($feed['captured_at']))echo '<p>Last successful sync: '.esc_html($feed['captured_at']).'. Refresh interval: 15 minutes via WordPress cron.</p>';
    echo '<p>Meta OAuth redirect URI: <code>'.esc_html(callback_url()).'</code></p>';
    echo '<form method="post" action="'.esc_url(\admin_url('admin-post.php')).'">';wp_nonce_field('hp_facebook');
    echo '<input type="hidden" name="action" value="hp_facebook"><input type="hidden" name="command" value="config"><label>Facebook Login configuration ID <input name="config_id" inputmode="numeric" value="'.esc_attr((string)get_option('hp_fb_config_id','')).'"></label> ';submit_button('Save login configuration','secondary','submit',false);echo '</form><p></p>';
    api_form('connect','Connect Facebook');api_form('sync','Sync Facebook now');if(!empty($auth['token']))api_form('disconnect','Disconnect Facebook');
}
