<?php
/**
 * Lists the posts in the specified topic.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', './');
require FORUM_ROOT.'include/dflayer.php';
if (file_exists(__DIR__ . '/config.php')) {
    include __DIR__ . '/config.php';
    $forum_df = new DFLayer($df_name);
}
require FORUM_ROOT.'include/common.php';

($hook = get_hook('vt_start')) ? eval($hook) : null;

if ($forum_user['g_read_board'] == '0')
	message($lang_common['No view']);

// Load the viewtopic.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/topic.php';


$action = isset($_GET['action']) ? $_GET['action'] : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
if ($id < 1 && $pid < 1)
	message($lang_common['Bad request']);


// If a post ID is specified we determine topic ID and page number so we can redirect to the correct message
if ($pid)
{
	$rows = $forum_df->fetch_all_from_file('posts'); // Nutzt deine vorhandene Funktion

    $result = []; //Results list

    foreach ($rows as $row) {
    	if (isset($row['id']) && $row['id'] == $pid) {
    	    $selected = [
                'topic_id' => $row['topic_id'],
                'posted' => $row['posted']
            ];
            $result[] = $selected;
        }
    }

	($hook = get_hook('vt_qr_get_post_info')) ? eval($hook) : null;
	$topic_info = isset($result[0]) ? $result[0] : null;

	if (!$topic_info)
	{
		message($lang_common['Bad request']);
	}

	$id = $topic_info['topic_id'];

	// Determine on what page the post is located (depending on $forum_user['disp_posts'])
	$rows = $forum_df->fetch_all_from_file('posts');

    $count = 0;

    foreach ($rows as $row) {
    	if (!isset($row['topic_id'], $row['posted'])) {
    	    continue;
        }

        if (
            $row['topic_id'] == $topic_info['topic_id'] &&
            $row['posted'] < $topic_info['posted']
        ) {
        	$count++;
        }
    }

    $num_posts = $count + 1;

	($hook = get_hook('vt_qr_get_post_page')) ? eval($hook) : null;

	$_GET['p'] = ceil($num_posts / $forum_user['disp_posts']);
}

// If action=new, we redirect to the first new post (if any)
else if ($action == 'new')
{
	if (!$forum_user['is_guest'])
	{
		// We need to check if this topic has been viewed recently by the user
		$tracked_topics = get_tracked_topics();
		$last_viewed = isset($tracked_topics['topics'][$id]) ? $tracked_topics['topics'][$id] : $forum_user['last_visit'];

		($hook = get_hook('vt_find_new_post')) ? eval($hook) : null;

		$query = array(
			'SELECT'	=> 'MIN(p.id)',
			'FROM'		=> 'posts AS p',
			'WHERE'		=> 'p.topic_id='.$id.' AND p.posted>'.$last_viewed
		);

		($hook = get_hook('vt_qr_get_first_new_post')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		$first_new_post_id = $forum_db->result($result);

		if ($first_new_post_id)
		{
			header('Location: '.str_replace('&amp;', '&', forum_link($forum_url['post'], $first_new_post_id)));
			exit;
		}
	}

	header('Location: '.str_replace('&amp;', '&', forum_link($forum_url['topic_last_post'], $id)));
	exit;
}

// If action=last, we redirect to the last post
else if ($action == 'last')
{
	$query = array(
		'SELECT'	=> 't.last_post_id',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.id='.$id
	);

	($hook = get_hook('vt_qr_get_last_post')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$last_post_id = $forum_db->result($result);

	if ($last_post_id)
	{
		header('Location: '.str_replace('&amp;', '&', forum_link($forum_url['post'], $last_post_id)));
		exit;
	}
}


// Fetch some info about the topic
$topics = $forum_df->fetch_all_from_file('topics');
$forums = $forum_df->fetch_all_from_file('forums');
$forum_perms = $forum_df->fetch_all_from_file('forum_perms');
$subscriptions = (!$forum_user['is_guest'] && $forum_config['o_subscriptions'] == '1')
    ? $forum_df->fetch_all_from_file('subscriptions')
    : [];

$result = [];

foreach ($topics as $t) {
    if (
        isset($t['id']) && $t['id'] == $id &&
        (!isset($t['moved_to']) || $t['moved_to'] === null)
    ) {
        // INNER JOIN forums f ON f.id = t.forum_id
        $forum = null;
        foreach ($forums as $f) {
            if (isset($f['id']) && $f['id'] == $t['forum_id']) {
                $forum = $f;
                break;
            }
        }

        if (!$forum) {
            continue; //INNER JOIN fails, so skip topic
        }

        $perm = null;
        foreach ($forum_perms as $fp) {
            if (
                isset($fp['forum_id'], $fp['group_id']) &&
                $fp['forum_id'] == $forum['id'] &&
                $fp['group_id'] == $forum_user['g_id']
            ) {
                $perm = $fp;
                break;
            }
        }

        $read_forum_allowed = !isset($perm['read_forum']) || $perm['read_forum'] == 1;
        if (!$read_forum_allowed) {
            continue;
        }

        // Optional: LEFT JOIN subscriptions
        $is_subscribed = 0;
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $s) {
                if (
                    isset($s['topic_id'], $s['user_id']) &&
                    $s['topic_id'] == $t['id'] &&
                    $s['user_id'] == $forum_user['id']
                ) {
                    $is_subscribed = 1;
                    break;
                }
            }
        }

        //Compile results
        $row = [
            'subject'        => $t['subject'],
            'first_post_id'  => $t['first_post_id'],
            'closed'         => $t['closed'] ?? '0',
            'num_replies'    => $t['num_replies'] ?? '0',
            'sticky'         => $t['sticky'] ?? '0',
            'forum_id'       => $forum['id'],
            'forum_name'     => $forum['forum_name'],
            'moderators'     => $forum['moderators'] ?? '',
            'post_replies'   => isset($perm['post_replies']) ? $perm['post_replies'] : null,
        ];

        if (!empty($subscriptions)) {
            $row['is_subscribed'] = $is_subscribed;
        }

        $result[] = $row;
    }
}

($hook = get_hook('vt_qr_get_topic_info')) ? eval($hook) : null;
$topic_info = !empty($result) ? $result[0] : null;
$cur_topic = $topic_info;

if (!$cur_topic)
{
	message($lang_common['Bad request']);
}

($hook = get_hook('vt_modify_topic_info')) ? eval($hook) : null;

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_topic['moderators'] != '') ? unserialize($cur_topic['moderators']) : array();
$forum_page['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;

// Can we or can we not post replies?
if ($cur_topic['closed'] == '0' || $forum_page['is_admmod'])
	$forum_user['may_post'] = (($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1' || $forum_page['is_admmod']) ? true : false;
else
	$forum_user['may_post'] = false;

// Add/update this topic in our list of tracked topics
if (!$forum_user['is_guest'])
{
	$tracked_topics = get_tracked_topics();
	$tracked_topics['topics'][$id] = time();
	set_tracked_topics($tracked_topics);
}

// Determine the post offset (based on $_GET['p'])
$forum_page['num_pages'] = ceil(($cur_topic['num_replies'] + 1) / $forum_user['disp_posts']);
$forum_page['page'] = (!isset($_GET['p']) || !is_numeric($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $forum_page['num_pages']) ? 1 : $_GET['p'];
$forum_page['start_from'] = $forum_user['disp_posts'] * ($forum_page['page'] - 1);
$forum_page['finish_at'] = min(($forum_page['start_from'] + $forum_user['disp_posts']), ($cur_topic['num_replies'] + 1));
$forum_page['items_info'] = generate_items_info($lang_topic['Posts'], ($forum_page['start_from'] + 1), ($cur_topic['num_replies'] + 1));

($hook = get_hook('vt_modify_page_details')) ? eval($hook) : null;

// Navigation links for header and page numbering for title/meta description
if ($forum_page['page'] < $forum_page['num_pages'])
{
	$forum_page['nav']['last'] = '<link rel="last" href="'.forum_sublink($forum_url['topic'], $forum_url['page'], $forum_page['num_pages'], array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.$forum_page['num_pages'].'" />';
	$forum_page['nav']['next'] = '<link rel="next" href="'.forum_sublink($forum_url['topic'], $forum_url['page'], ($forum_page['page'] + 1), array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.($forum_page['page'] + 1).'" />';
}
if ($forum_page['page'] > 1)
{
	$forum_page['nav']['prev'] = '<link rel="prev" href="'.forum_sublink($forum_url['topic'], $forum_url['page'], ($forum_page['page'] - 1), array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' '.($forum_page['page'] - 1).'" />';
	$forum_page['nav']['first'] = '<link rel="first" href="'.forum_link($forum_url['topic'], array($id, sef_friendly($cur_topic['subject']))).'" title="'.$lang_common['Page'].' 1" />';
}

if ($forum_config['o_censoring'] == '1')
	$cur_topic['subject'] = censor_words($cur_topic['subject']);

// Generate paging and posting links
$forum_page['page_post']['paging'] = '<p class="paging"><span class="pages">'.$lang_common['Pages'].'</span> '.paginate($forum_page['num_pages'], $forum_page['page'], $forum_url['topic'], $lang_common['Paging separator'], array($id, sef_friendly($cur_topic['subject']))).'</p>';

if ($forum_user['may_post'])
	$forum_page['page_post']['posting'] = '<p class="posting"><a class="newpost" href="'.forum_link($forum_url['new_reply'], $id).'"><span>'.$lang_topic['Post reply'].'</span></a></p>';
else if ($forum_user['is_guest'])
	$forum_page['page_post']['posting'] = '<p class="posting">'.sprintf($lang_topic['Login to post'], '<a href="'.forum_link($forum_url['login']).'">'.$lang_common['login'].'</a>', '<a href="'.forum_link($forum_url['register']).'">'.$lang_common['register'].'</a>').'</p>';
else if ($cur_topic['closed'] == '1')
	$forum_page['page_post']['posting'] = '<p class="posting">'.$lang_topic['Topic closed info'].'</p>';
else
	$forum_page['page_post']['posting'] = '<p class="posting">'.$lang_topic['No permission'].'</p>';

// Setup main options
$forum_page['main_title'] = $lang_topic['Topic options'];

if (!$forum_user['is_guest'] && $forum_config['o_subscriptions'] == '1')
{
	if ($cur_topic['is_subscribed'])
		$forum_page['main_head_options']['unsubscribe'] = '<span><a class="sub-option" href="'.forum_link($forum_url['unsubscribe'], array($id, generate_form_token('unsubscribe'.$id.$forum_user['id']))).'"><em>'.$lang_topic['Unsubscribe'].'</em></a></span>';
	else
		$forum_page['main_head_options']['subscribe'] = '<span><a class="sub-option" href="'.forum_link($forum_url['subscribe'], array($id, generate_form_token('subscribe'.$id.$forum_user['id']))).'" title="'.$lang_topic['Subscribe info'].'">'.$lang_topic['Subscribe'].'</a></span>';
}

if ($forum_page['is_admmod'])
{
	$forum_page['main_foot_options'] = array(
		'move' => '<span class="first-item"><a class="mod-option" href="'.forum_link($forum_url['move'], array($cur_topic['forum_id'], $id)).'">'.$lang_topic['Move'].'</a></span>',
		'delete' => '<span><a class="mod-option" href="'.forum_link($forum_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a></span>',
		'close' => (($cur_topic['closed'] == '1') ? '<span><a class="mod-option" href="'.forum_link($forum_url['open'], array($cur_topic['forum_id'], $id, generate_form_token('open'.$id))).'">'.$lang_topic['Open'].'</a></span>' : '<span><a class="mod-option" href="'.forum_link($forum_url['close'], array($cur_topic['forum_id'], $id, generate_form_token('close'.$id))).'">'.$lang_topic['Close'].'</a></span>'),
		'sticky' => (($cur_topic['sticky'] == '1') ? '<span><a class="mod-option" href="'.forum_link($forum_url['unstick'], array($cur_topic['forum_id'], $id, generate_form_token('unstick'.$id))).'">'.$lang_topic['Unstick'].'</a></span>' : '<span><a class="mod-option" href="'.forum_link($forum_url['stick'], array($cur_topic['forum_id'], $id, generate_form_token('stick'.$id))).'">'.$lang_topic['Stick'].'</a></span>')
	);

	if ($cur_topic['num_replies'] != 0)
		$forum_page['main_foot_options']['moderate_topic'] = '<span><a class="mod-option" href="'.forum_sublink($forum_url['moderate_topic'], $forum_url['page'], $forum_page['page'], array($cur_topic['forum_id'], $id)).'">'.$lang_topic['Moderate topic'].'</a></span>';
}

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($cur_topic['forum_name'], forum_link($forum_url['forum'], array($cur_topic['forum_id'], sef_friendly($cur_topic['forum_name'])))),
	$cur_topic['subject']
);

// Setup main heading
$forum_page['main_title'] = (($cur_topic['closed'] == '1') ? $lang_topic['Topic closed'].' ' : '').'<a class="permalink" href="'.forum_link($forum_url['topic'], array($id, sef_friendly($cur_topic['subject']))).'" rel="bookmark" title="'.$lang_topic['Permalink topic'].'">'.forum_htmlencode($cur_topic['subject']).'</a>';

if ($forum_page['num_pages'] > 1)
	$forum_page['main_head_pages'] = sprintf($lang_common['Page info'], $forum_page['page'], $forum_page['num_pages']);

($hook = get_hook('vt_pre_header_load')) ? eval($hook) : null;

// Allow indexing if this is a permalink
if (!$pid)
	define('FORUM_ALLOW_INDEX', 1);

define('FORUM_PAGE', 'viewtopic');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('vt_main_output_start')) ? eval($hook) : null;

?>
	<div class="main-head">
<?php

	if (!empty($forum_page['main_head_options']))
		echo "\t\t".'<p class="options">'.implode(' ', $forum_page['main_head_options']).'</p>'."\n";

?>
		<h2 class="hn"><span><?php echo $forum_page['items_info'] ?></span></h2>
	</div>
	<div id="forum<?php echo $cur_topic['forum_id'] ?>" class="main-content main-topic">
<?php

if (!defined('FORUM_PARSER_LOADED'))
	require FORUM_ROOT.'include/parser.php';

$forum_page['item_count'] = 0;	// Keep track of post numbers

// 1. Retrieve the posts ids
//Load all posts
$posts = $forum_df->fetch_all_from_file('posts');

//Only posts with matching topic_id
$filtered = [];
foreach ($posts as $p) {
	if (isset($p['topic_id']) && $p['topic_id'] == $id) {
		$filtered[] = $p;
	}
}

//Sort by p.id (ascending)
usort($filtered, function ($a, $b) {
	return $a['id'] <=> $b['id'];
});

//Apply limit
$start = $forum_page['start_from'];
$limit = $forum_user['disp_posts'];
$limited = array_slice($filtered, $start, $limit);

//Now extract only the column p.id
$result = [];
foreach ($limited as $post) {
	$result[] = $post['id'];
}

($hook = get_hook('vt_qr_get_posts_id')) ? eval($hook) : null;

$posts_id = array();
foreach ($result as $id) {
	$posts_id[] = $id;
}


if (!empty($posts_id))
{
	// 2. Retrieve the posts (and their respective poster/online status) by known id`s
	$posts = $forum_df->fetch_all_from_file('posts');
	$users = $forum_df->fetch_all_from_file('users');
	$groups = $forum_df->fetch_all_from_file('groups');
	$online = $forum_df->fetch_all_from_file('online');

    $users_by_id = [];
    foreach ($users as $user) {
    	$users_by_id[$user['id']] = $user;
    }

    $groups_by_id = [];
    foreach ($groups as $group) {
    	$groups_by_id[$group['g_id']] = $group;
    }

    $online_by_user_id = [];
    foreach ($online as $entry) {
    	if ($entry['user_id'] != 1 && $entry['idle'] == 0) {
    	    $online_by_user_id[$entry['user_id']] = $entry;
        }
    }

    $result = [];
    
    foreach ($posts as $post) {
    	if (!in_array($post['id'], $posts_id)) {
    	    continue;
        }

        $user = $users_by_id[$post['poster_id']] ?? null;
        $group = $user ? ($groups_by_id[$user['group_id']] ?? null) : null;
        $is_online = isset($online_by_user_id[$post['poster_id']]) ? $post['poster_id'] : null;

        $row = [
            'email' => $user['email'] ?? null,
            'title' => $user['title'] ?? null,
            'url' => $user['url'] ?? null,
            'location' => $user['location'] ?? null,
            'signature' => $user['signature'] ?? null,
            'email_setting' => $user['email_setting'] ?? null,
            'num_posts' => $user['num_posts'] ?? null,
            'registered' => $user['registered'] ?? null,
            'admin_note' => $user['admin_note'] ?? null,
            'avatar' => $user['avatar'] ?? null,
            'avatar_width' => $user['avatar_width'] ?? null,
            'avatar_height' => $user['avatar_height'] ?? null,

            'id' => $post['id'],
            'username' => $post['poster'],
            'poster_id' => $post['poster_id'],
            'poster_ip' => $post['poster_ip'],
            'poster_email' => $post['poster_email'] ?? '',
            'message' => $post['message'],
            'hide_smilies' => $post['hide_smilies'] ?? 0,
            'posted' => $post['posted'],
            'edited' => $post['edited'] ?? '',
            'edited_by' => $post['edited_by'] ?? '',

            'g_id' => $group['g_id'] ?? null,
            'g_user_title' => $group['g_user_title'] ?? null,

            'is_online' => $is_online,
        ];

        $result[] = $row;
    }

    usort($result, function ($a, $b) {
    	return $a['id'] <=> $b['id'];
    });

	($hook = get_hook('vt_qr_get_posts')) ? eval($hook) : null;

	$user_data_cache = array();
	foreach ($result as $cur_post)
	{
		($hook = get_hook('vt_post_loop_start')) ? eval($hook) : null;

		++$forum_page['item_count'];

		$forum_page['post_ident'] = array();
		$forum_page['author_ident'] = array();
		$forum_page['author_info'] = array();
		$forum_page['post_options'] = array();
		$forum_page['post_contacts'] = array();
		$forum_page['post_actions'] = array();
		$forum_page['message'] = array();

		// Generate the post heading
		$forum_page['post_ident']['num'] = '<span class="post-num">'.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span>';

		if ($cur_post['poster_id'] > 1)
			$forum_page['post_ident']['byline'] = '<span class="post-byline">'.sprintf((($cur_post['id'] == $cur_topic['first_post_id']) ? $lang_topic['Topic byline'] : $lang_topic['Reply byline']), (($forum_user['g_view_users'] == '1') ? '<a title="'.sprintf($lang_topic['Go to profile'], forum_htmlencode($cur_post['username'])).'" href="'.forum_link($forum_url['user'], $cur_post['poster_id']).'">'.forum_htmlencode($cur_post['username']).'</a>' : '<strong>'.forum_htmlencode($cur_post['username']).'</strong>')).'</span>';
		else
			$forum_page['post_ident']['byline'] = '<span class="post-byline">'.sprintf((($cur_post['id'] == $cur_topic['first_post_id']) ? $lang_topic['Topic byline'] : $lang_topic['Reply byline']), '<strong>'.forum_htmlencode($cur_post['username']).'</strong>').'</span>';

		$forum_page['post_ident']['link'] = '<span class="post-link"><a class="permalink" rel="bookmark" title="'.$lang_topic['Permalink post'].'" href="'.forum_link($forum_url['post'], $cur_post['id']).'">'.format_time($cur_post['posted']).'</a></span>';

		if ($cur_post['edited'] != '')
			$forum_page['post_ident']['edited'] = '<span class="post-edit">'.sprintf($lang_topic['Last edited'], forum_htmlencode($cur_post['edited_by']), format_time($cur_post['edited'])).'</span>';


		($hook = get_hook('vt_row_pre_post_ident_merge')) ? eval($hook) : null;

		if (isset($user_data_cache[$cur_post['poster_id']]['author_ident']))
			$forum_page['author_ident'] = $user_data_cache[$cur_post['poster_id']]['author_ident'];
		else
		{
			// Generate author identification
			if ($cur_post['poster_id'] > 1)
			{
				if ($forum_config['o_avatars'] == '1' && $forum_user['show_avatars'] != '0')
				{
					$forum_page['avatar_markup'] = generate_avatar_markup($cur_post['poster_id'], $cur_post['avatar'], $cur_post['avatar_width'], $cur_post['avatar_height'], $cur_post['username']);

					if (!empty($forum_page['avatar_markup']))
						$forum_page['author_ident']['avatar'] = '<li class="useravatar">'.$forum_page['avatar_markup'].'</li>';
				}

				$forum_page['author_ident']['username'] = '<li class="username">'.(($forum_user['g_view_users'] == '1') ? '<a title="'.sprintf($lang_topic['Go to profile'], forum_htmlencode($cur_post['username'])).'" href="'.forum_link($forum_url['user'], $cur_post['poster_id']).'">'.forum_htmlencode($cur_post['username']).'</a>' : '<strong>'.forum_htmlencode($cur_post['username']).'</strong>').'</li>';
				$forum_page['author_ident']['usertitle'] = '<li class="usertitle"><span>'.get_title($cur_post).'</span></li>';

				if ($cur_post['is_online'] == $cur_post['poster_id'])
					$forum_page['author_ident']['status'] = '<li class="userstatus"><span>'.$lang_topic['Online'].'</span></li>';
				else
					$forum_page['author_ident']['status'] = '<li class="userstatus"><span>'.$lang_topic['Offline'].'</span></li>';
			}
			else
			{
				$forum_page['author_ident']['username'] = '<li class="username"><strong>'.forum_htmlencode($cur_post['username']).'</strong></li>';
				$forum_page['author_ident']['usertitle'] = '<li class="usertitle"><span>'.get_title($cur_post).'</span></li>';
			}
		}

		if (isset($user_data_cache[$cur_post['poster_id']]['author_info']))
			$forum_page['author_info'] = $user_data_cache[$cur_post['poster_id']]['author_info'];
		else
		{
			// Generate author information
			if ($cur_post['poster_id'] > 1)
			{
				if ($forum_config['o_show_user_info'] == '1')
				{
					if ($cur_post['location'] != '')
					{
						if ($forum_config['o_censoring'] == '1')
							$cur_post['location'] = censor_words($cur_post['location']);

						$forum_page['author_info']['from'] = '<li><span>'.$lang_topic['From'].' <strong>'.forum_htmlencode($cur_post['location']).'</strong></span></li>';
					}

					$forum_page['author_info']['registered'] = '<li><span>'.$lang_topic['Registered'].' <strong>'.format_time($cur_post['registered'], 1).'</strong></span></li>';

					if ($forum_config['o_show_post_count'] == '1' || $forum_user['is_admmod'])
						$forum_page['author_info']['posts'] = '<li><span>'.$lang_topic['Posts info'].' <strong>'.forum_number_format($cur_post['num_posts']).'</strong></span></li>';
				}

				if ($forum_user['is_admmod'])
				{
					if ($cur_post['admin_note'] != '')
						$forum_page['author_info']['note'] = '<li><span>'.$lang_topic['Note'].' <strong>'.forum_htmlencode($cur_post['admin_note']).'</strong></span></li>';
				}
			}
		}

		// Generate IP information for moderators/administrators
		if ($forum_user['is_admmod'])
			$forum_page['author_info']['ip'] = '<li><span>'.$lang_topic['IP'].' <a href="'.forum_link($forum_url['get_host'], $cur_post['id']).'">'.$cur_post['poster_ip'].'</a></span></li>';

		// Generate author contact details
		if ($forum_config['o_show_user_info'] == '1')
		{
			if (isset($user_data_cache[$cur_post['poster_id']]['post_contacts']))
				$forum_page['post_contacts'] = $user_data_cache[$cur_post['poster_id']]['post_contacts'];
			else
			{
				if ($cur_post['poster_id'] > 1)
				{
					if ($cur_post['url'] != '')
						$forum_page['post_contacts']['url'] = '<span class="user-url'.(empty($forum_page['post_contacts']) ? ' first-item' : '').'"><a class="external" href="'.forum_htmlencode(($forum_config['o_censoring'] == '1') ? censor_words($cur_post['url']) : $cur_post['url']).'">'.sprintf($lang_topic['Visit website'], '<span>'.sprintf($lang_topic['User possessive'], forum_htmlencode($cur_post['username'])).'</span>').'</a></span>';
					if ((($cur_post['email_setting'] == '0' && !$forum_user['is_guest']) || $forum_user['is_admmod']) && $forum_user['g_send_email'] == '1')
						$forum_page['post_contacts']['email'] = '<span class="user-email'.(empty($forum_page['post_contacts']) ? ' first-item' : '').'"><a href="mailto:'.forum_htmlencode($cur_post['email']).'">'.$lang_topic['E-mail'].'<span>&#160;'.forum_htmlencode($cur_post['username']).'</span></a></span>';
					else if ($cur_post['email_setting'] == '1' && !$forum_user['is_guest'] && $forum_user['g_send_email'] == '1')
						$forum_page['post_contacts']['email'] = '<span class="user-email'.(empty($forum_page['post_contacts']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['email'], $cur_post['poster_id']).'">'.$lang_topic['E-mail'].'<span>&#160;'.forum_htmlencode($cur_post['username']).'</span></a></span>';
				}
				else
				{
					if ($cur_post['poster_email'] != '' && $forum_user['is_admmod'] && $forum_user['g_send_email'] == '1')
						$forum_page['post_contacts']['email'] = '<span class="user-email'.(empty($forum_page['post_contacts']) ? ' first-item' : '').'"><a href="mailto:'.forum_htmlencode($cur_post['poster_email']).'">'.$lang_topic['E-mail'].'<span>&#160;'.forum_htmlencode($cur_post['username']).'</span></a></span>';
				}
			}

			($hook = get_hook('vt_row_pre_post_contacts_merge')) ? eval($hook) : null;

			if (!empty($forum_page['post_contacts']))
				$forum_page['post_options']['contacts'] = '<p class="post-contacts">'.implode(' ', $forum_page['post_contacts']).'</p>';
		}

		// Generate the post options links
		if (!$forum_user['is_guest'])
		{
			$forum_page['post_actions']['report'] = '<span class="report-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['report'], $cur_post['id']).'">'.$lang_topic['Report'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';

			if (!$forum_page['is_admmod'])
			{
				if ($cur_topic['closed'] == '0')
				{
					if ($cur_post['poster_id'] == $forum_user['id'])
					{
						if (($forum_page['start_from'] + $forum_page['item_count']) == 1 && $forum_user['g_delete_topics'] == '1')
							$forum_page['post_actions']['delete'] = '<span class="delete-topic'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a></span>';
						if (($forum_page['start_from'] + $forum_page['item_count']) > 1 && $forum_user['g_delete_posts'] == '1')
							$forum_page['post_actions']['delete'] = '<span class="delete-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_post['id']).'">'.$lang_topic['Delete'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
						if ($forum_user['g_edit_posts'] == '1')
							$forum_page['post_actions']['edit'] = '<span class="edit-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['edit'], $cur_post['id']).'">'.$lang_topic['Edit'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
					}

					if (($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
						$forum_page['post_actions']['quote'] = '<span class="quote-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['quote'], array($id, $cur_post['id'])).'">'.$lang_topic['Quote'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
				}
			}
			else
			{
				if (($forum_page['start_from'] + $forum_page['item_count']) == 1)
					$forum_page['post_actions']['delete'] = '<span class="delete-topic'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_topic['first_post_id']).'">'.$lang_topic['Delete topic'].'</a></span>';
				else
					$forum_page['post_actions']['delete'] = '<span class="delete-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['delete'], $cur_post['id']).'">'.$lang_topic['Delete'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';

				$forum_page['post_actions']['edit'] = '<span class="edit-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['edit'], $cur_post['id']).'">'.$lang_topic['Edit'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
				$forum_page['post_actions']['quote'] = '<span class="quote-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['quote'], array($id, $cur_post['id'])).'">'.$lang_topic['Quote'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
			}
		}
		else
		{
			if ($cur_topic['closed'] == '0')
			{
				if (($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
					$forum_page['post_actions']['quote'] = '<span class="report-post'.(empty($forum_page['post_actions']) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['quote'], array($id, $cur_post['id'])).'">'.$lang_topic['Quote'].'<span> '.$lang_topic['Post'].' '.forum_number_format($forum_page['start_from'] + $forum_page['item_count']).'</span></a></span>';
			}
		}

		($hook = get_hook('vt_row_pre_post_actions_merge')) ? eval($hook) : null;

		if (!empty($forum_page['post_actions']))
			$forum_page['post_options']['actions'] = '<p class="post-actions">'.implode(' ', $forum_page['post_actions']).'</p>';

		// Give the post some class
		$forum_page['item_status'] = array(
			'post',
			($forum_page['item_count'] % 2 != 0) ? 'odd' : 'even'
		);

		if ($forum_page['item_count'] == 1)
			$forum_page['item_status']['firstpost'] = 'firstpost';

		if (($forum_page['start_from'] + $forum_page['item_count']) == $forum_page['finish_at'])
			$forum_page['item_status']['lastpost'] = 'lastpost';

		if ($cur_post['id'] == $cur_topic['first_post_id'])
			$forum_page['item_status']['topicpost'] = 'topicpost';
		else
			$forum_page['item_status']['replypost'] = 'replypost';


		// Generate the post title
		if ($cur_post['id'] == $cur_topic['first_post_id'])
			$forum_page['item_subject'] = sprintf($lang_topic['Topic title'], $cur_topic['subject']);
		else
			$forum_page['item_subject'] = sprintf($lang_topic['Reply title'], $cur_topic['subject']);

		$forum_page['item_subject'] = forum_htmlencode($forum_page['item_subject']);

		// Perform the main parsing of the message (BBCode, smilies, censor words etc)
		$forum_page['message']['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

		// Do signature parsing/caching
		if ($cur_post['signature'] != '' && $forum_user['show_sig'] != '0' && $forum_config['o_signatures'] == '1')
		{
			if (!isset($signature_cache[$cur_post['poster_id']]))
				$signature_cache[$cur_post['poster_id']] = parse_signature($cur_post['signature']);

			$forum_page['message']['signature'] = '<div class="sig-content"><span class="sig-line"><!-- --></span>'.$signature_cache[$cur_post['poster_id']].'</div>';
		}

		($hook = get_hook('vt_row_pre_display')) ? eval($hook) : null;

		// Do user data caching for the post
		if ($cur_post['poster_id'] > 1 && !isset($user_data_cache[$cur_post['poster_id']]))
		{
			$user_data_cache[$cur_post['poster_id']] = array(
				'author_ident'	=> $forum_page['author_ident'],
				'author_info'	=> $forum_page['author_info'],
				'post_contacts'	=> $forum_page['post_contacts']
			);

			($hook = get_hook('vt_row_add_user_data_cache')) ? eval($hook) : null;
		}

?>
		<div class="<?php echo implode(' ', $forum_page['item_status']) ?>">
			<div id="p<?php echo $cur_post['id'] ?>" class="posthead">
				<h3 class="hn post-ident"><?php echo implode(' ', $forum_page['post_ident']) ?></h3>
			</div>
			<div class="postbody<?php if ($cur_post['is_online'] == $cur_post['poster_id']) echo ' online'; ?>">
				<div class="post-author">
					<ul class="author-ident">
						<?php echo implode("\n\t\t\t\t\t\t", $forum_page['author_ident'])."\n" ?>
					</ul>
					<ul class="author-info">
						<?php echo implode("\n\t\t\t\t\t\t", $forum_page['author_info'])."\n" ?>
					</ul>
				</div>
				<div class="post-entry">
					<h4 id="pc<?php echo $cur_post['id'] ?>" class="entry-title hn"><?php echo $forum_page['item_subject'] ?></h4>
					<div class="entry-content">
						<?php echo implode("\n\t\t\t\t\t\t", $forum_page['message'])."\n" ?>
					</div>
<?php ($hook = get_hook('vt_row_new_post_entry_data')) ? eval($hook) : null; ?>
				</div>
			</div>
<?php if (!empty($forum_page['post_options'])): ?>
			<div class="postfoot">
				<div class="post-options">
					<?php echo implode("\n\t\t\t\t\t", $forum_page['post_options'])."\n" ?>
				</div>
			</div>
<?php endif; ?>
		</div>
<?php

	}
}

?>
	</div>

	<div class="main-foot">
<?php

	if (!empty($forum_page['main_foot_options']))
		echo "\n\t\t\t".'<p class="options">'.implode(' ', $forum_page['main_foot_options']).'</p>';

?>
		<h2 class="hn"><span><?php echo $forum_page['items_info'] ?></span></h2>
	</div>
<?php

($hook = get_hook('vt_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->



// Display quick post if enabled
if ($forum_config['o_quickpost'] == '1' &&
	!$forum_user['is_guest'] &&
	($cur_topic['post_replies'] == '1' || ($cur_topic['post_replies'] == '' && $forum_user['g_post_replies'] == '1')) &&
	($cur_topic['closed'] == '0' || $forum_page['is_admmod']))
{

// START SUBST - <!-- forum_qpost -->
ob_start();

($hook = get_hook('vt_qpost_output_start')) ? eval($hook) : null;

// Setup form
$forum_page['form_action'] = forum_link($forum_url['new_reply'], $id);
$forum_page['form_attributes'] = array();

$forum_page['hidden_fields'] = array(
	'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
	'form_user'		=> '<input type="hidden" name="form_user" value="'.((!$forum_user['is_guest']) ? forum_htmlencode($forum_user['username']) : 'Guest').'" />',
	'csrf_token'	=> '<input type="hidden" name="csrf_token" value="'.generate_form_token($forum_page['form_action']).'" />'
);

if (!$forum_user['is_guest'] && $forum_config['o_subscriptions'] == '1' && ($forum_user['auto_notify'] == '1' || $cur_topic['is_subscribed']))
	$forum_page['hidden_fields']['subscribe'] = '<input type="hidden" name="subscribe" value="1" />';

// Setup help
$forum_page['main_head_options'] = array();
if ($forum_config['p_message_bbcode'] == '1')
	$forum_page['text_options']['bbcode'] = '<span'.(empty($forum_page['text_options']) ? ' class="first-item"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'bbcode').'" title="'.sprintf($lang_common['Help page'], $lang_common['BBCode']).'">'.$lang_common['BBCode'].'</a></span>';
if ($forum_config['p_message_img_tag'] == '1')
	$forum_page['text_options']['img'] = '<span'.(empty($forum_page['text_options']) ? ' class="first-item"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'img').'" title="'.sprintf($lang_common['Help page'], $lang_common['Images']).'">'.$lang_common['Images'].'</a></span>';
if ($forum_config['o_smilies'] == '1')
	$forum_page['text_options']['smilies'] = '<span'.(empty($forum_page['text_options']) ? ' class="first-item"' : '').'><a class="exthelp" href="'.forum_link($forum_url['help'], 'smilies').'" title="'.sprintf($lang_common['Help page'], $lang_common['Smilies']).'">'.$lang_common['Smilies'].'</a></span>';

($hook = get_hook('vt_quickpost_pre_display')) ? eval($hook) : null;

?>
<div class="main-subhead">
	<h2 class="hn"><span><?php echo $lang_topic['Quick post'] ?></span></h2>
</div>
<div id="brd-qpost" class="main-content main-frm">
<?php if (!empty($forum_page['text_options'])) echo "\t".'<p class="content-options options">'.sprintf($lang_common['You may use'], implode(' ', $forum_page['text_options'])).'</p>'."\n" ?>
	<div id="req-msg" class="req-warn ct-box error-box">
		<p class="important"><?php echo $lang_topic['Required warn'] ?></p>
	</div>
	<form class="frm-form frm-ctrl-submit" method="post" accept-charset="utf-8" action="<?php echo $forum_page['form_action'] ?>"<?php if (!empty($forum_page['form_attributes'])) echo ' '.implode(' ', $forum_page['form_attributes']) ?>>
		<div class="hidden">
			<?php echo implode("\n\t\t\t\t", $forum_page['hidden_fields'])."\n" ?>
		</div>
<?php ($hook = get_hook('vt_quickpost_pre_fieldset')) ? eval($hook) : null; ?>
		<fieldset class="frm-group group1">
			<legend class="group-legend"><strong><?php echo $lang_common['Write message legend'] ?></strong></legend>
<?php ($hook = get_hook('vt_quickpost_pre_message_box')) ? eval($hook) : null; ?>
			<div class="txt-set set1">
				<div class="txt-box textarea required">
					<label for="fld1"><span><?php echo $lang_common['Write message'] ?></span></label>
					<div class="txt-input"><span class="fld-input"><textarea id="fld1" name="req_message" rows="10" cols="95" required spellcheck="true" ></textarea></span></div>
				</div>
			</div>
<?php ($hook = get_hook('vt_quickpost_pre_fieldset_end')) ? eval($hook) : null; ?>
		</fieldset>
<?php ($hook = get_hook('vt_quickpost_fieldset_end')) ? eval($hook) : null; ?>
		<div class="frm-buttons">
			<span class="submit primary"><input type="submit" name="submit_button" value="<?php echo $lang_common['Submit'] ?>" /></span>
			<span class="submit"><input type="submit" name="preview" value="<?php echo $lang_common['Preview'] ?>" /></span>
		</div>
	</form>
</div>
<?php

($hook = get_hook('vt_quickpost_end')) ? eval($hook) : null;

$tpl_temp = forum_trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_qpost -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_qpost -->

}

// Increment "num_views" for topic
if ($forum_config['o_topic_views'] == '1')
{
	$query = array(
		'UPDATE'	=> 'topics',
		'SET'		=> 'num_views=num_views+1',
		'WHERE'		=> 'id='.$id,
	);

	($hook = get_hook('vt_qr_increment_num_views')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);
}

$forum_id = $cur_topic['forum_id'];

require FORUM_ROOT.'footer.php';
