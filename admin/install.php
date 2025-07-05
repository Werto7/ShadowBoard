<?php
/**
 * Installation script.
 *
 * Used to actually install PunBB.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */
define('MIN_PHP_VERSION', '5.4.0');
define('MIN_MYSQL_VERSION', '4.1.2');

define('FORUM_ROOT', '../');
define('FORUM', 1);
define('FORUM_DEBUG', 1);

if (file_exists(FORUM_ROOT.'config.php'))
	exit('The file \'config.php\' already exists which would mean that PunBB is already installed. You should go <a href="'.FORUM_ROOT.'index.php">here</a> instead.');


// Make sure we are running at least MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
	exit('You are running PHP version '.PHP_VERSION.'. PunBB requires at least PHP '.MIN_PHP_VERSION.' to run properly. You must upgrade your PHP installation before you can continue.');

// Disable error reporting for uninitialized variables
error_reporting(E_ALL);

// Turn off PHP time limit
@set_time_limit(0);

require FORUM_ROOT.'include/constants.php';
// We need some stuff from functions.php
require FORUM_ROOT.'include/functions.php';

// Load UTF-8 functions
require FORUM_ROOT.'include/utf8/utf8.php';
require FORUM_ROOT.'include/utf8/ucwords.php';
require FORUM_ROOT.'include/utf8/trim.php';

require FORUM_ROOT.'include/dflayer.php';

// Strip out "bad" UTF-8 characters
forum_remove_bad_characters();

//
// Generate output to be used for config.php
//
function generate_config_file()
{
	global $df_name, $base_url, $cookie_name;

	$config_body = '<?php'."\n\n"
        .'$df_name = \''.addslashes($df_name ?? '')."';\n"   //Make sure df_name is not null
        .'$p_connect = false;'."\n\n"
        .'$base_url = \''.$base_url.'\';'."\n\n"
        .'$cookie_name = '."'".$cookie_name."';\n"
        .'$cookie_domain = '."'';\n"
        .'$cookie_path = '."'/';\n"
        .'$cookie_secure = 0;'."\n\n"
        .'if (!defined(\'FORUM\')) {'."\n"
        .'    define(\'FORUM\', 1);'."\n"
        .'}';



	// Add forum options
	$config_body .= "\n\n// Enable DEBUG mode by removing // from the following line\n//define('FORUM_DEBUG', 1);";
	$config_body .= "\n\n// Enable show DB Queries mode by removing // from the following line\n//define('FORUM_SHOW_QUERIES', 1);";
	$config_body .= "\n\n// Enable forum IDNA support by removing // from the following line\n//define('FORUM_ENABLE_IDNA', 1);";
	$config_body .= "\n\n// Disable forum CSRF checking by removing // from the following line\n//define('FORUM_DISABLE_CSRF_CONFIRM', 1);";
	$config_body .= "\n\n// Disable forum hooks (extensions) by removing // from the following line\n//define('FORUM_DISABLE_HOOKS', 1);";
	$config_body .= "\n\n// Disable forum output buffering by removing // from the following line\n//define('FORUM_DISABLE_BUFFERING', 1);";
	$config_body .= "\n\n// Disable forum async JS loader by removing // from the following line\n//define('FORUM_DISABLE_ASYNC_JS_LOADER', 1);";
	$config_body .= "\n\n// Disable forum extensions version check by removing // from the following line\n//define('FORUM_DISABLE_EXTENSIONS_VERSION_CHECK', 1);";

	return $config_body;
}

$language = isset($_GET['lang']) ? $_GET['lang'] : (isset($_POST['req_language']) ? forum_trim($_POST['req_language']) : 'English');
$language = preg_replace('#[\.\\\/]#', '', $language);
if (!file_exists(FORUM_ROOT.'lang/'.$language.'/install.php'))
	exit('The language pack you have chosen doesn\'t seem to exist or is corrupt. Please recheck and try again.');

// Load the language files
require FORUM_ROOT.'lang/'.$language.'/install.php';
require FORUM_ROOT.'lang/'.$language.'/admin_settings.php';

if (isset($_POST['generate_config']))
{
	header('Content-Type: text/x-delimtext; name="config.php"');
	header('Content-disposition: attachment; filename=config.php');

	$db_type = $_POST['db_type'];
	$db_host = $_POST['db_host'];
	$db_name = $_POST['db_name'];
	$db_username = $_POST['db_username'];
	$db_password = $_POST['db_password'];
	$db_prefix = $_POST['db_prefix'];
	$base_url = $_POST['base_url'];
	$cookie_name = $_POST['cookie_name'];

	echo generate_config_file();
	exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: cache-control: no-store', false);

if (!isset($_POST['form_sent']))
{
	// Determine available database extensions
	$db_extensions = array();

	if (function_exists('mysqli_connect'))
	{
		$db_extensions[] = array('mysqli', 'MySQL Improved');
		$db_extensions[] = array('mysqli_innodb', 'MySQL Improved (InnoDB)');
	}

	if (function_exists('mysql_connect'))
	{
		$db_extensions[] = array('mysql', 'MySQL Standard');
		$db_extensions[] = array('mysql_innodb', 'MySQL Standard (InnoDB)');
	}

	if (function_exists('sqlite_open'))
		$db_extensions[] = array('sqlite', 'SQLite');

	if (class_exists('SQLite3'))
		$db_extensions[] = array('sqlite3', 'SQLite3');

	if (function_exists('pg_connect'))
		$db_extensions[] = array('pgsql', 'PostgreSQL');

	if (empty($db_extensions))
		error($lang_install['No database support']);

	// Make an educated guess regarding base_url
	$base_url_guess = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://').preg_replace('/:80$/', '', $_SERVER['HTTP_HOST']).substr(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), 0, -6);
	if (substr($base_url_guess, -1) == '/')
		$base_url_guess = substr($base_url_guess, 0, -1);

	// Check for available language packs
	$languages = get_language_packs();

?>
<!DOCTYPE html>
<!--[if lt IE 7 ]> <html class="oldie ie6" lang="en" dir="ltr"> <![endif]-->
<!--[if IE 7 ]>    <html class="oldie ie7" lang="en" dir="ltr"> <![endif]-->
<!--[if IE 8 ]>    <html class="oldie ie8" lang="en" dir="ltr"> <![endif]-->
<!--[if gt IE 8]><!--> <html lang="en" dir="ltr"> <!--<![endif]-->
<head>
	<meta charset="utf-8" />
	<title>PunBB Installation</title>
	<link rel="stylesheet" type="text/css" href="<?php echo FORUM_ROOT ?>style/Oxygen/Oxygen.min.css" />
</head>
<body>
<div id="brd-install" class="brd-page">
<div id="brd-wrap" class="brd">

<div id="brd-head" class="gen-content">
	<p id="brd-title"><strong><?php printf($lang_install['Install PunBB'], FORUM_VERSION) ?></strong></p>
	<p id="brd-desc"><?php echo $lang_install['Install intro'] ?></p>
</div>

<div id="brd-main" class="main">

	<div class="main-head">
		<h1 class="hn"><span><?php printf($lang_install['Install PunBB'], FORUM_VERSION) ?></span></h1>
	</div>

<?php

	if (count($languages) > 1)
	{

?>	<form class="frm-form" method="get" accept-charset="utf-8" action="install.php">
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_install['Choose language'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<fieldset class="frm-group group1">
			<legend class="group-legend"><strong><?php echo $lang_install['Choose language legend'] ?></strong></legend>
			<div class="sf-set set1">
				<div class="sf-box text">
					<label for="fld0"><span><?php echo $lang_install['Installer language'] ?></span> <small><?php echo $lang_install['Choose language help'] ?></small></label><br />
					<span class="fld-input"><select id="fld0" name="lang">
<?php

		foreach ($languages as $lang)
			echo "\t\t\t\t\t".'<option value="'.$lang.'"'.($language == $lang ? ' selected="selected"' : '').'>'.$lang.'</option>'."\n";

?>					</select></span>
				</div>
			</div>
		</fieldset>
		<div class="frm-buttons">
			<span class="submit primary"><input type="submit" name="changelang" value="<?php echo $lang_install['Choose language'] ?>" /></span>
		</div>
	</div>
	</form>
<?php

	}

?>    <form class="frm-form frm-suggest-username" method="post" accept-charset="utf-8" action="install.php">
	<div class="hidden">
		<input type="hidden" name="form_sent" value="1" />
	</div>

	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_install['Part1'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box info-box">
			<p><?php echo $lang_install['Part1 intro'] ?></p>
		</div>
		<fieldset class="frm-group group1">
			<legend class="group-legend"><strong><?php echo $lang_install['Part1 legend'] ?></strong></legend>
			<div class="sf-set set1 prepend-top">
				<div class="sf-box text required">
					<label for="admin_username"><span><?php echo $lang_install['Admin username'] ?></span> <small><?php echo $lang_install['Username help'] ?></small></label><br />
					<span class="fld-input"><input id="admin_username" type="text" data-suggest-role="username" name="req_username" size="35" maxlength="25" required /></span>
				</div>
			</div>
			<div class="sf-set set2">
				<div class="sf-box text required">
					<label for="fld8"><span><?php echo $lang_install['Admin password'] ?></span> <small><?php echo $lang_install['Password help'] ?></small></label><br />
					<span class="fld-input"><input id="fld8" type="text" name="req_password1" size="35" required autocomplete="off" /></span>
				</div>
			</div>
		</fieldset>
	</div>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_install['Part2'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="ct-box info-box">
			<p><?php echo $lang_install['Part2 intro'] ?></p>
			<ul class="spaced list-clean">
				<li><span><strong><?php echo $lang_install['Base URL'] ?></strong> <?php echo $lang_install['Base URL info'] ?></span></li>
			</ul>
		</div>
		<fieldset class="frm-group group1">
			<legend class="group-legend"><strong><?php echo $lang_install['Part2 legend'] ?></strong></legend>
			<div class="sf-set set3">
				<div class="sf-box text required">
					<label for="fld10"><span><?php echo $lang_install['Base URL'] ?></span> <small><?php echo $lang_install['Base URL help'] ?></small></label><br />
					<span class="fld-input"><input id="fld10" type="url" name="req_base_url" value="<?php echo $base_url_guess ?>" size="35" maxlength="100" required /></span>
				</div>
			</div>
<?php

	if (count($languages) > 1)
	{

?>			<div class="sf-set set4">
				<div class="sf-box text">
					<label for="fld11"><span><?php echo $lang_install['Default language'] ?></span> <small><?php echo $lang_install['Default language help'] ?></small></label><br />
					<span class="fld-input"><select id="fld11" name="req_language">
<?php

		foreach ($languages as $lang)
			echo "\t\t\t\t\t".'<option value="'.$lang.'"'.($language == $lang ? ' selected="selected"' : '').'>'.$lang.'</option>'."\n";

?>					</select></span>
				</div>
			</div>
<?php

	}
	else
	{

?>			<div class="hidden">
				<input type="hidden" name="req_language" value="<?php echo $languages[0] ?>" />
			</div>
<?php
	}

	if (file_exists(FORUM_ROOT.'extensions/pun_repository/manifest.xml'))
	{

?>			<div class="sf-set set5">
				<div class="sf-box checkbox">
					<span class="fld-input"><input id="fld12" type="checkbox" name="install_pun_repository" value="1" checked="checked" /></span>
					<label for="fld12"><span><?php echo $lang_install['Pun repository'] ?></span> <?php echo $lang_install['Pun repository help'] ?></label><br />
				</div>
			</div>
<?php

	}

?>
		</fieldset>
		<div class="frm-buttons">
			<span class="submit primary"><input type="submit" name="start" value="<?php echo $lang_install['Start install'] ?>" /></span>
		</div>
	</div>
	</form>
</div>

</div>
</div>
	<script src="<?php echo FORUM_ROOT ?>include/js/min/punbb.common.min.js"></script>
	<script src="<?php echo FORUM_ROOT ?>include/js/min/punbb.install.min.js"></script>
</body>
</html>
<?php

}
else
{
	//
	// Strip slashes only if magic_quotes_gpc is on.
	//
	function unescape($str)
	{
		return $str;
	}

    $df_name = __DIR__ . '/../../data';
    if (!is_dir($df_name)) {
        if (!mkdir($df_name, 0777, true)) {
            die('Fehler: Der Ordner "' . $df_name . '" konnte nicht erstellt werden.');
        }
    }
	$username = unescape(forum_trim($_POST['req_username']));
	$password1 = unescape(forum_trim($_POST['req_password1']));
	$default_lang = preg_replace('#[\.\\\/]#', '', unescape(forum_trim($_POST['req_language'])));
	$install_pun_repository = !empty($_POST['install_pun_repository']);

	// Make sure base_url doesn't end with a slash
	if (substr($_POST['req_base_url'], -1) == '/')
		$base_url = substr($_POST['req_base_url'], 0, -1);
	else
		$base_url = $_POST['req_base_url'];

	// Validate form
	if (utf8_strlen($df_name) == 0)
		error($lang_install['Missing data folder name']);
	if (utf8_strlen($username) < 2)
		error($lang_install['Username too short']);
	if (utf8_strlen($username) > 25)
		error($lang_install['Username too long']);
	if (utf8_strlen($password1) < 4)
		error($lang_install['Pass too short']);
	if (strtolower($username) == 'guest')
		error($lang_install['Username guest']);
	if (preg_match('/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/', $username) || preg_match('/((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))/', $username))
		error($lang_install['Username IP']);
	if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		error($lang_install['Username reserved chars']);
	if (preg_match('/(?:\[\/?(?:b|u|i|h|colou?r|quote|code|img|url|email|list)\]|\[(?:code|quote|list)=)/i', $username))
		error($lang_install['Username BBCode']);

	// Validate email
	if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/email.php';

	// Make sure board title and description aren't left blank
	$board_title = 'My PunBB forum';
	$board_descrip = 'Unfortunately no one can be told what PunBB is — you have to see it for yourself';

	if (utf8_strlen($base_url) == 0)
		error($lang_install['Missing base url']);

	if (!file_exists(FORUM_ROOT.'lang/'.$default_lang.'/common.php'))
		error($lang_install['Invalid language']);

    $forum_df = new DFLayer($df_name);

	// Make sure ShadowBoard isn't already installed
    if (file_exists($df_name.'/users.json')) {
        $users_json = file_get_contents($df_name.'/users.json'); // Ganze JSON-Datei einlesen
        $users_data = json_decode($users_json, true); // Als assoziatives Array dekodieren

        if (!is_array($users_data)) {
            error('Benutzerdatei konnte nicht gelesen werden oder ist fehlerhaft.', __FILE__, __LINE__);
        }

        foreach ($users_data as $user) {
            if (isset($user['id']) && intval($user['id']) === 1) { // Prüfen, ob Benutzer mit ID 1 existiert
                error(sprintf($lang_install['ShadowBoard already installed'], $df_name));
            }
        }
    }

	// Create all files
    if (!file_exists($df_name.'/bans.json')) {
        file_put_contents($df_name.'/bans.json', "");
    }
    
    if (!file_exists($df_name.'/categories.json')) {
        file_put_contents($df_name.'/categories.json', "");
    }

    if (!file_exists($df_name.'/censoring.json')) {
        file_put_contents($df_name.'/censoring.json', "");
    }

    if (!file_exists($df_name.'/config.json')) {
        file_put_contents($df_name.'/config.json', "");
    }

    if (!file_exists($df_name.'/extensions.json')) {
        file_put_contents($df_name.'/extensions.json', "");
    }
    
    if (!file_exists($df_name.'/extension_hooks.json')) {
        file_put_contents($df_name.'/extension_hooks.json', "");
    }
    
    if (!file_exists($df_name.'/forum_perms.json')) {
        file_put_contents($df_name.'/forum_perms.json', "");
    }
    
    if (!file_exists($df_name.'/forums.json')) {
        file_put_contents($df_name.'/forums.json', "");
    }
    
    if (!file_exists($df_name.'/groups.json')) {
        file_put_contents($df_name.'/groups.json', "");
    }
    
    if (!file_exists($df_name.'/online.json')) {
        file_put_contents($df_name.'/online.json', "");
    }
    
    if (!file_exists($df_name.'/posts.json')) {
        file_put_contents($df_name.'/posts.json', "");
    }
    
    if (!file_exists($df_name.'/ranks.json')) {
        file_put_contents($df_name.'/ranks.json', "");
    }

    if (!file_exists($df_name.'/reports.json')) {
        file_put_contents($df_name.'/reports.json', "");
    }

    if (!file_exists($df_name.'/search_cache.json')) {
        file_put_contents($df_name.'/search_cache.json', "");
    }

    if (!file_exists($df_name.'/search_matches.json')) {
        file_put_contents($df_name.'/search_matches.json', "");
    }

    if (!file_exists($df_name.'/search_words.json')) {
        file_put_contents($df_name.'/search_words.json', "");
    }

    if (!file_exists($df_name.'/subscriptions.json')) {
        file_put_contents($df_name.'/subscriptions.json', "");
    }

    if (!file_exists($df_name.'/forum_subscriptions.json')) {
        file_put_contents($df_name.'/forum_subscriptions.json', "");
    }

    if (!file_exists($df_name.'/topics.json')) {
        file_put_contents($df_name.'/topics.json', "");
    }

    if (!file_exists($df_name.'/users.json')) {
        file_put_contents($df_name.'/users.json', "");
    }

	$now = time();

	// Insert the four preset groups
	
	$query = array(
		'INSERT'	=> 'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_id',
		'INTO'		=> 'groups',
		'VALUES'	=> '\'Administrators\', Administrator, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 1'
	);
	
	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

    $query = array(
		'INSERT'	=> 'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_id',
		'INTO'		=> 'groups',
		'VALUES'	=> '\'Guest\', NULL, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 60, 30, 0, 2'
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	$query = array(
		'INSERT'	=> 'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_id',
		'INTO'		=> 'groups',
		'VALUES'	=> '\'Members\', NULL, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 60, 30, 60, 3'
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	$query = array(
		'INSERT'	=> 'g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_id',
		'INTO'		=> 'groups',
		'VALUES'	=> '\'Moderators\', Moderator, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 4'
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	// Insert guest and first admin user
	$query = array(
		'INSERT'	=> 'group_id, username, password, id',
		'INTO'		=> 'users',
		'VALUES'	=> '2, \'Guest\', \'Guest\', 1'
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	$salt = random_key(12);
	
	$query = array(
	    'INSERT'	=> 'group_id, username, password, language, num_posts, last_post, registered, last_visit, salt, id',
	    'INTO'		=> 'users',
	    'VALUES'	=> array(
		    1,
		    $forum_df->escape($username),
		    forum_hash($password1, $salt),
		    $forum_df->escape($default_lang),
		    1,
		    $now,
		    $now,
		    $now,
		    $forum_df->escape($salt),
		    2
	    )
    );

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $data    = array_combine($columns, $query['VALUES']);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }
	
	$new_uid = $forum_df->get_new_uid();

	// Enable/disable avatars depending on file_uploads setting in PHP configuration
	$avatars = in_array(strtolower(@ini_get('file_uploads')), array('on', 'true', '1')) ? 1 : 0;

	// Enable/disable automatic check for updates depending on PHP environment (require cURL, fsockopen or allow_url_fopen)
	$check_for_updates = (function_exists('curl_init') || function_exists('fsockopen') || in_array(strtolower(@ini_get('allow_url_fopen')), array('on', 'true', '1'))) ? 1 : 0;

	// Insert config data
	$config = array(
		'o_cur_version'				=> "'".FORUM_VERSION."'",
		'o_database_revision'		=> "'".FORUM_DB_REVISION."'",
		'o_board_title'				=> $forum_df->escape($board_title),
		'o_board_desc'				=> $forum_df->escape($board_descrip),
		'o_default_timezone'		=> "'0'",
		'o_time_format'				=> "'H:i:s'",
		'o_date_format'				=> "'Y-m-d'",
		'o_check_for_updates'		=> "'$check_for_updates'",
		'o_check_for_versions'		=> "'$check_for_updates'",
		'o_timeout_visit'			=> "'5400'",
		'o_timeout_online'			=> "'300'",
		'o_redirect_delay'			=> "'0'",
		'o_show_version'			=> "'0'",
		'o_show_user_info'			=> "'1'",
		'o_show_post_count'			=> "'1'",
		'o_signatures'				=> "'1'",
		'o_smilies'					=> "'1'",
		'o_smilies_sig'				=> "'1'",
		'o_make_links'				=> "'1'",
		'o_default_lang'			=> "'".$forum_df->escape($default_lang)."'",
		'o_default_style'			=> "'Oxygen'",
		'o_default_user_group'		=> "'3'",
		'o_topic_review'			=> "'15'",
		'o_disp_topics_default'		=> "30",
		'o_disp_posts_default'		=> "25",
		'o_indent_num_spaces'		=> "'4'",
		'o_quote_depth'				=> "'3'",
		'o_quickpost'				=> "'1'",
		'o_users_online'			=> "'1'",
		'o_censoring'				=> "'0'",
		'o_ranks'					=> "'1'",
		'o_show_dot'				=> "'0'",
		'o_topic_views'				=> "'1'",
		'o_quickjump'				=> "'1'",
		'o_gzip'					=> "'0'",
		'o_additional_navlinks'		=> "''",
		'o_report_method'			=> "'0'",
		'o_regs_report'				=> "'0'",
		'o_default_email_setting'	=> "'1'",
		'o_avatars'					=> "'$avatars'",
		'o_avatars_dir'				=> "'img/avatars'",
		'o_avatars_width'			=> "'60'",
		'o_avatars_height'			=> "'60'",
		'o_avatars_size'			=> "'15360'",
		'o_search_all_forums'		=> "'1'",
		'o_sef'						=> "'Default'",
		'o_subscriptions'			=> "'1'",
		'o_smtp_host'				=> "NULL",
		'o_smtp_user'				=> "NULL",
		'o_smtp_pass'				=> "NULL",
		'o_smtp_ssl'				=> "'0'",
		'o_regs_allow'				=> "'1'",
		'o_regs_verify'				=> "'0'",
		'o_announcement'			=> "'0'",
		'o_announcement_heading'	=> "'".$lang_install['Default announce heading']."'",
		'o_announcement_message'	=> "'".$lang_install['Default announce message']."'",
		'o_rules'					=> "'0'",
		'o_rules_message'			=> "'".$lang_install['Default rules']."'",
		'o_maintenance'				=> "'0'",
		'o_maintenance_message'		=> "'".$lang_admin_settings['Maintenance message default']."'",
		'o_default_dst'				=> "'0'",
		'p_message_bbcode'			=> "'1'",
		'p_message_img_tag'			=> "'1'",
		'p_message_all_caps'		=> "'1'",
		'p_subject_all_caps'		=> "'1'",
		'p_sig_all_caps'			=> "'1'",
		'p_sig_bbcode'				=> "'1'",
		'p_sig_img_tag'				=> "'0'",
		'p_sig_length'				=> "'400'",
		'p_sig_lines'				=> "'4'",
		'p_allow_banned_email'		=> "'1'",
		'p_allow_dupe_email'		=> "'0'",
		'p_force_guest_email'		=> "'1'",
		'o_show_moderators'			=> "'0'",
		'o_mask_passwords'			=> "'1'"
	);
	
	try {
        $forum_df->write_to_file($config, 'config');
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	// Insert some other default data
	$id = $forum_df->get_new_uid('categories');
	$query = array(
		'INSERT'	=> 'id, cat_name, disp_position',
		'INTO'		=> 'categories',
		'VALUES'	=> (int) $id.',\''.$lang_install['Default category name'].'\', 1'
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	$forum_id = $forum_df->get_new_uid('forums');

    $query = array(
	    'INSERT' => 'id, forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, disp_position, cat_id',
	    'INTO'   => 'forums',
	    'VALUES' => (int) $forum_id.', '.$lang_install['Default forum name'].', '.$lang_install['Default forum descrip'].', 1, 1, '.$now.', 1, \''.$forum_df->escape($username).'\', 1, 1'
    );

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	$topic_id = $forum_df->get_new_uid('topics');

    $query = array(
		'INSERT'	=> 'id, poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, forum_id',
		'INTO'		=> 'topics',
		'VALUES'	=> (int) $topic_id.','.$forum_df->escape($username).', '.$lang_install['Default topic subject'].', '.$now.', 1, '.$now.', 1, '.$forum_df->escape($username).', '.$forum_df->get_new_uid().''
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }
	
	$query = array(
        'INSERT' => 'poster, poster_id, poster_ip, message, posted, topic_id, id',
        'INTO'   => 'posts',
        'VALUES' => array(
            $forum_df->escape($username),
            2,
            '127.0.0.1',
            $lang_install['Default post contents'],
            $now,
            $forum_df->get_new_uid(),
            1
        )
    );

    //Convert to columns
    $columns = array_map('trim', explode(',', $query['INSERT']));

    //Combine now (column name => value)
    $data = array_combine($columns, $query['VALUES']);

	try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	// Add new post to search table
	require FORUM_ROOT.'include/search_idx.php';
	update_search_index('post', $forum_df->get_new_uid(), $lang_install['Default post contents'], $lang_install['Default topic subject']);

	// Insert the default ranks
	$query = array(
		'INSERT'	=> 'rank, min_posts',
		'INTO'		=> 'ranks',
		'VALUES'	=> '\''.$lang_install['Default rank 1'].'\', 0'
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }

	$query = array(
		'INSERT'	=> 'rank, min_posts',
		'INTO'		=> 'ranks',
		'VALUES'	=> '\''.$lang_install['Default rank 2'].'\', 10'
	);

	$columns = array_map('trim', explode(',', $query['INSERT']));
    $values  = array_map('trim', explode(',', $query['VALUES']));
    $data = array_combine($columns, $values);

    try {
        $forum_df->write_to_file($data, $query['INTO']);
    } catch (Exception $e) {
        error($e->getMessage(), __FILE__, __LINE__);
    }


	$alerts = array();

	// Check if the cache directory is writable and clear cache dir
	if (is_writable(FORUM_ROOT.'cache/'))
	{
		$cache_dir = dir(FORUM_ROOT.'cache/');
		if ($cache_dir)
		{
			while (($entry = $cache_dir->read()) !== false)
			{
				if (substr($entry, strlen($entry)-4) == '.php')
					@unlink(FORUM_ROOT.'cache/'.$entry);
			}
			$cache_dir->close();
		}
	}
	else
	{
		$alerts[] = '<li><span>'.$lang_install['No cache write'].'</span></li>';
	}

	// Check if default avatar directory is writable
	if (!is_writable(FORUM_ROOT.'img/avatars/'))
		$alerts[] = '<li><span>'.$lang_install['No avatar write'].'</span></li>';

	// Check if we disabled uploading avatars because file_uploads was disabled
	if ($avatars == '0')
		$alerts[] = '<li><span>'.$lang_install['File upload alert'].'</span></li>';

	// Add some random bytes at the end of the cookie name to prevent collisions
	$cookie_name = 'forum_cookie_'.random_key(6, false, true);

	/// Generate the config.php file data
	$config = generate_config_file();

	// Attempt to write config.php and serve it up for download if writing fails
	$written = false;
	if (is_writable(FORUM_ROOT))
	{
		$fh = @fopen(FORUM_ROOT.'config.php', 'wb');
		if ($fh)
		{
			fwrite($fh, $config);
			fclose($fh);

			$written = true;
		}
	}


	if ($install_pun_repository && is_readable(FORUM_ROOT.'extensions/pun_repository/manifest.xml'))
	{
		require FORUM_ROOT.'include/xml.php';

		$ext_data = xml_to_array(file_get_contents(FORUM_ROOT.'extensions/pun_repository/manifest.xml'));

		if (!empty($ext_data))
		{
			$query = array(
				'INSERT'	=> 'id, title, version, description, author, uninstall, uninstall_note, dependencies',
				'INTO'		=> 'extensions',
				'VALUES'	=> '\'pun_repository\', \''.$forum_db->escape($ext_data['extension']['title']).'\', \''.$forum_db->escape($ext_data['extension']['version']).'\', \''.$forum_db->escape($ext_data['extension']['description']).'\', \''.$forum_db->escape($ext_data['extension']['author']).'\', NULL, NULL, \'||\'',
			);

			$columns = array_map('trim', explode(',', $query['INSERT']));
            $values  = array_map('trim', explode(',', $query['VALUES']));
            $data = array_combine($columns, $values);

            try {
                $forum_df->write_to_file($data, $query['INTO']);
            } catch (Exception $e) {
                error($e->getMessage(), __FILE__, __LINE__);
            }

			if (isset($ext_data['extension']['hooks']['hook']))
			{
				foreach ($ext_data['extension']['hooks']['hook'] as $ext_hook)
				{
					$cur_hooks = explode(',', $ext_hook['attributes']['id']);
					foreach ($cur_hooks as $cur_hook)
					{
						$query = array(
							'INSERT'	=> 'id, extension_id, code, installed, priority',
							'INTO'		=> 'extension_hooks',
							'VALUES'	=> '\''.$forum_db->escape(forum_trim($cur_hook)).'\', \'pun_repository\', \''.$forum_db->escape(forum_trim($ext_hook['content'])).'\', '.time().', '.(isset($ext_hook['attributes']['priority']) ? $ext_hook['attributes']['priority'] : 5)
						);

						$columns = array_map('trim', explode(',', $query['INSERT']));
                        $values  = array_map('trim', explode(',', $query['VALUES']));
                        $data = array_combine($columns, $values);

                        try {
                            $forum_df->write_to_file($data, $query['INTO']);
                        } catch (Exception $e) {
                            error($e->getMessage(), __FILE__, __LINE__);
                        }
					}
				}
			}
		}
	}

    require_once FORUM_ROOT.'include/xml.php';

?>
<!DOCTYPE html>
<!--[if lt IE 7 ]> <html class="oldie ie6" lang="en" dir="ltr"> <![endif]-->
<!--[if IE 7 ]>    <html class="oldie ie7" lang="en" dir="ltr"> <![endif]-->
<!--[if IE 8 ]>    <html class="oldie ie8" lang="en" dir="ltr"> <![endif]-->
<!--[if gt IE 8]><!--> <html lang="en" dir="ltr"> <!--<![endif]-->
<head>
	<meta charset="utf-8" />
	<title>PunBB Installation</title>
	<link rel="stylesheet" type="text/css" href="<?php echo FORUM_ROOT ?>style/Oxygen/Oxygen.min.css" />
</head>
<body>
<div id="brd-install" class="brd-page">
	<div id="brd-wrap" class="brd">
		<div id="brd-head" class="gen-content">
			<p id="brd-title"><strong><?php printf($lang_install['Install PunBB'], FORUM_VERSION) ?></strong></p>
			<p id="brd-desc"><?php printf($lang_install['Success description'], FORUM_VERSION) ?></p>
		</div>
		<div id="brd-main" class="main basic">
			<div class="main-content main-frm">
<?php if (!empty($alerts)): ?>
				<div class="ct-box error-box">
					<p class="warn"><strong><?php echo $lang_install['Warning'] ?></strong></p>
					<ul>
						<?php echo implode("\n\t\t\t\t", $alerts)."\n" ?>
					</ul>
				</div>
<?php endif;

if (!$written)
{
?>
				<div class="ct-box info-box">
					<p class="warn"><?php echo $lang_install['No write info 1'] ?></p>
					<p class="warn"><?php printf($lang_install['No write info 2'], '<a href="'.FORUM_ROOT.'index.php">'.$lang_install['Go to index'].'</a>') ?></p>
				</div>
				<form class="frm-form" method="post" accept-charset="utf-8" action="install.php">
					<div class="hidden">
					<input type="hidden" name="generate_config" value="1" />
					<input type="hidden" name="db_type" value="<?php echo $db_type ?>" />
					<input type="hidden" name="db_host" value="<?php echo $db_host ?>" />
					<input type="hidden" name="db_name" value="<?php echo forum_htmlencode($db_name) ?>" />
					<input type="hidden" name="db_username" value="<?php echo forum_htmlencode($db_username) ?>" />
					<input type="hidden" name="db_password" value="<?php echo forum_htmlencode($db_password) ?>" />
					<input type="hidden" name="db_prefix" value="<?php echo forum_htmlencode($db_prefix) ?>" />
					<input type="hidden" name="base_url" value="<?php echo forum_htmlencode($base_url) ?>" />
					<input type="hidden" name="cookie_name" value="<?php echo forum_htmlencode($cookie_name) ?>" />
					</div>
					<div class="frm-buttons">
						<span class="submit"><input type="submit" value="<?php echo $lang_install['Download config'] ?>" /></span>
					</div>
				</form>
<?php
}
else
{
?>
				<div class="ct-box info-box">
					<p class="warn"><?php printf($lang_install['Write info'], '<a href="'.FORUM_ROOT.'index.php">'.$lang_install['Go to index'].'</a>') ?></p>
				</div>
<?php
}
?>
			</div>
		</div>
	</div>
</div>
</body>
</html>
<?php
}
