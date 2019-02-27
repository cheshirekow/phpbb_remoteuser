<?php
/**
 * @package phpBB Extension - cheshirekow remoteuserauth
 * @copyright (c) 2019 Josh Bialkowski <josh.bialkowski@gmail.com>
 * @license http://opensource.org/licenses/gpl-2.0.php
 *          GNU General Public License v2
 */

use \phpbb\passwords\driver as pwd;

class remoteuserauth_provider_test extends phpbb_database_test_case
{
	protected $provider;
	protected $user;
	protected $request;

	protected function setup()
	{
		parent::setUp();

		global $phpbb_root_path, $phpEx;

		$db = $this->new_dbal();
		$config = new \phpbb\config\config(array());
		$lang_loader = new \phpbb\language\language_file_loader(
			$phpbb_root_path, $phpEx);

		$lang = new \phpbb\language\language($lang_loader);
		$this->request = $this->getMock('\phpbb\request\request');
		$this->user = new \phpbb\user($lang, '\phpbb\datetime');
		$driver_helper = new \phpbb\passwords\driver\helper($config);
		$passwords_drivers = array(
			'passwords.driver.bcrypt_2y' => new pwd\bcrypt_2y(
				$config, $driver_helper),
			'passwords.driver.bcrypt' => new pwd\bcrypt(
				$config, $driver_helper),
			'passwords.driver.salted_md5' => new pwd\salted_md5(
				$config, $driver_helper),
			'passwords.driver.phpass' => new pwd\phpass(
				$config, $driver_helper),
		);

		$passwords_helper = new \phpbb\passwords\helper;
		// Set up passwords manager
		$passwords_manager = new \phpbb\passwords\manager(
			$config, $passwords_drivers, $passwords_helper,
			array_keys($passwords_drivers));

		$phpbb_container = new phpbb_mock_container_builder();
		$this->provider = new \cheshirekow\remoteuserauth\provider(
			$db, $config, $passwords_manager, $this->request, $this->user,
			$phpbb_container, $phpbb_root_path, $phpEx);
        $this->provider->set_language($lang);

		if (version_compare(PHP_VERSION, '5.3.7', '<'))
		{
			$this->password_hash =
				'$2a$10$e01Syh9PbJjUkio66eFuUu4FhCE2nRgG7QPc1JACalsPXcIuG2bbi';
		}
		else
		{
			$this->password_hash =
				'$2y$10$4RmpyVu2y8Yf/lP3.yQBquKvE54TCUuEDEBJYY6FDDFN3LcbCGz9i';
		}
	}

	public function getDataSet()
	{
		if ((version_compare(PHP_VERSION, '5.3.7', '<')))
		{
			return $this->createXMLDataSet(dirname(__FILE__) .
				'/../../../../../tests/auth/fixtures/user_533.xml');
		}
		else
		{
			return $this->createXMLDataSet(dirname(__FILE__) .
				'/../../../../../tests/auth/fixtures/user.xml');
		}
	}

	/**
	 * Test to see if a user is identified to Apache. Expects false if they are.
	 */
	public function test_init()
	{
		$this->user->data['username'] = 'foobar';
		$this->request->expects($this->once())
			->method('is_set')
			->with('REMOTE_USER',
				\phpbb\request\request_interface::SERVER)
			->will($this->returnValue(true));
		$this->request->expects($this->once())
			->method('server')
			->with('REMOTE_USER')
			->will($this->returnValue('foobar'));

		$this->assertFalse($this->provider->init());
	}

	public function test_login()
	{
		$username = 'foobar';
		$password = 'example';

		$expected = array(
			'status' => LOGIN_SUCCESS,
			'error_msg' => false,
			'user_row' => array(
				'user_id' => '1',
				'username' => $username,
				'user_password' => $this->password_hash,
				'user_passchg' => '0',
				'user_email' => 'example@example.com',
				'user_type' => '0',
			),
		);

		$login_return = $this->provider->login($username, $password);
		// $this->assertEquals($expected, $login_return);
		$this->assertEquals($expected['status'], $login_return['status']);
		$this->assertEquals($expected['error_msg'], $login_return['error_msg']);
		foreach ($expected['user_row'] as $key => $value)
		{
			$this->assertEquals($value, $login_return['user_row'][$key]);
		}
	}

	public function test_empty_acp_login()
	{
		$username = 'foobar';
		$password = '';

		$this->request->expects($this->once())
			->method('is_set')
			->with('REMOTE_USER',
				\phpbb\request\request_interface::SERVER)
			->will($this->returnValue(true));
		$this->request->expects($this->at(1))
			->method('server')
			->with('REMOTE_USER')
			->will($this->returnValue($username));

		$expected = array(
			'status' => LOGIN_SUCCESS,
			'error_msg' => false,
			'user_row' => array(
				'user_id' => '1',
				'username' => $username,
				'user_password' => $this->password_hash,
				'user_passchg' => '0',
				'user_email' => 'example@example.com',
				'user_type' => '0',
			),
		);

		$login_return = $this->provider->login($username, $password);
		// $this->assertEquals($expected, $login_return);
		$this->assertEquals($expected['status'], $login_return['status']);
		$this->assertEquals($expected['error_msg'], $login_return['error_msg']);
		foreach ($expected['user_row'] as $key => $value)
		{
			$this->assertEquals($value, $login_return['user_row'][$key]);
		}
	}

	public function test_autologin()
	{
		$this->request->expects($this->once())
			->method('is_set')
			->with('REMOTE_USER',
				\phpbb\request\request_interface::SERVER)
			->will($this->returnValue(true));
		$this->request->expects($this->at(1))
			->method('server')
			->with('REMOTE_USER')
			->will($this->returnValue('foobar'));

		$expected = array(
			'user_id' => 1,
			'user_type' => 0,
			'group_id' => 3,
			'user_permissions' => '',
			'user_perm_from' => 0,
			'user_ip' => '',
			'user_regdate' => 0,
			'username' => 'foobar',
			'username_clean' => 'foobar',
			'user_password' => $this->password_hash,
			'user_passchg' => 0,
			'user_email' => 'example@example.com',
			'user_email_hash' => 0,
			'user_birthday' => '',
			'user_lastvisit' => 0,
			'user_lastmark' => 0,
			'user_lastpost_time' => 0,
			'user_lastpage' => '',
			'user_last_confirm_key' => '',
			'user_last_search' => 0,
			'user_warnings' => 0,
			'user_last_warning' => 0,
			'user_login_attempts' => 0,
			'user_inactive_reason' => 0,
			'user_inactive_time' => 0,
			'user_posts' => 0,
			'user_lang' => '',
			'user_timezone' => '',
			'user_dateformat' => 'd M Y H:i',
			'user_style' => 0,
			'user_rank' => 0,
			'user_colour' => '',
			'user_new_privmsg' => 0,
			'user_unread_privmsg' => 0,
			'user_last_privmsg' => 0,
			'user_message_rules' => 0,
			'user_full_folder' => -3,
			'user_emailtime' => 0,
			'user_topic_show_days' => 0,
			'user_topic_sortby_type' => 't',
			'user_topic_sortby_dir' => 'd',
			'user_post_show_days' => 0,
			'user_post_sortby_type' => 't',
			'user_post_sortby_dir' => 'a',
			'user_notify' => 0,
			'user_notify_pm' => 1,
			'user_notify_type' => 0,
			'user_allow_pm' => 1,
			'user_allow_viewonline' => 1,
			'user_allow_viewemail' => 1,
			'user_allow_massemail' => 1,
			'user_options' => 230271,
			'user_avatar' => '',
			'user_avatar_type' => '',
			'user_avatar_width' => 0,
			'user_avatar_height' => 0,
			'user_sig' => '',
			'user_sig_bbcode_uid' => '',
			'user_sig_bbcode_bitfield' => '',
			'user_jabber' => '',
			'user_actkey' => '',
			'user_newpasswd' => '',
			'user_form_salt' => '',
			'user_new' => 1,
			'user_reminded' => 0,
			'user_reminded_time' => 0,
		);

		$this->assertEquals($expected, $this->provider->autologin());
	}

	public function test_validate_session()
	{
		$user = array(
			'username' => 'foobar',
			'user_type',
		);
		$this->request->expects($this->once())
			->method('is_set')
			->with('REMOTE_USER',
				\phpbb\request\request_interface::SERVER)
			->will($this->returnValue(true));
		$this->request->expects($this->once())
			->method('server')
			->with('REMOTE_USER')
			->will($this->returnValue('foobar'));

		$this->assertTrue($this->provider->validate_session($user));
	}
}
