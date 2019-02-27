<?php
/**
 * @copyright (c) Josh Bialkowski <josh.bialkowski@gmail.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'REMOTE_USER' => 'Remote User',
	'REMOTE_USER_VARNAME' => 'Remote User CGI-Var',
	'REMOTE_USER_VARNAME_EXPLAIN' =>
	'CGI variable in which the server will place the username (if ' .
	'authenticated through Remote User Token)',
	'REMOTE_USER_SETUP_BEFORE_USE' =>
	'You have to setup remote user authentication before you switch phpBB' .
	' to this authentication method. Make sure your reverse proxy is passing' .
	' in the right variable.',
	'REMOTE_USER_INVALID_USERNAME' =>
	'You have to setup remote user authentication before you switch phpBB to' .
	' this authentication method. The reverse proxy is providing a username ' .
	' (%s) other than your current one (%s).',
));
