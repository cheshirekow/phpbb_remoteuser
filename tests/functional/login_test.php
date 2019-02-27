<?php
/**
 * @package phpBB Extension - cheshirekow remoteuserauth
 * @copyright (c) 2019 Josh Bialkowski <josh.bialkowski@gmail.com>
 * @license http://opensource.org/licenses/gpl-2.0.php
 *          GNU General Public License v2
 */

namespace cheshirekow\remoteuserauth\tests\functional;

/**
 * @group functional
 */
class autologin_test extends \phpbb_functional_test_case
{
	static protected function setup_extensions()
	{
		return array('cheshirekow/remoteuserauth');
	}

	public function setUp()
	{
		parent::setUp();

		global $cache, $config;
		$cache = new \phpbb_mock_null_cache;
		$db = $this->get_db();
		$sql = 'UPDATE ' . CONFIG_TABLE . "
            SET config_value = 'remoteuser' WHERE config_name = 'auth_method'";
		$db->sql_query($sql);
		$config['auth_method'] = 'remoteuser';

		// NOTE(josh): seems to be required or else the auth setting change
		// doesn't get picked up
		$this->purge_cache();
	}

	public function tearDown()
	{
		global $config;
		$db = $this->get_db();
		$sql = 'UPDATE ' . CONFIG_TABLE . "
            SET config_value = 'db' WHERE config_name =  'auth_method'";
		$db->sql_query($sql);
		$config['auth_method'] = 'db';

		// NOTE(josh): seems to be required or else the auth setting change
		// remains active
		$this->purge_cache();

		parent::tearDown();
	}

	public function test_noheader_nologin()
	{
		// NOTE(josh): remove the header (if it's installed), logout, and ensure
		// that we are not automatically logged back in.
		$crawler = self::$client->request('GET', self::$root_url . 'index.php');
		$nodes = $crawler->filter('#username_logged_in');
		$this->assertEquals(0, count($nodes), 'alice is still logged in');
	}

	public function test_autologin_withheader()
	{
		// NOTE(josh): include the username in an http header `X-Remote-User`,
		// and verify that the `alice` is automatically logged in.
		self::$client->setHeader("X-Remote-User", 'alice');
		$crawler = self::$client->request('GET', self::$root_url . 'index.php');
		$this->assert_filter($crawler, '#username_logged_in');
		$this->assertContains('alice',
			$crawler->filter('#username_logged_in')->text());
		self::$client->removeHeader("X-Remote-User");
		$this->logout();
	}
}

/**
 * @group functional
 */
class enable_test extends \phpbb_functional_test_case
{
	static protected $webDriver;

	static protected function setup_extensions()
	{
		return array('cheshirekow/remoteuserauth');
	}

	// If we don't provide the X-Remote-User header then the REMOTE_USER
	// cgivar will be empty. The admin control panel should prevent us from
	// enabling the provider in this case.
	public function test_withoutheader()
	{
		$this->login();
		$this->admin_login();
		$this->add_lang_ext("cheshirekow/remoteuserauth",
			"info_acp_remoteuserauth");
		$crawler = self::request(
			'GET', 'adm/index.php?i=acp_board&mode=auth&sid=' . $this->sid);

		$form = $crawler->filter('#acp_board')->form();
		$form_values = $form->getValues();
		$form_values["config[auth_method]"] = "remoteuser";
		$form_values["submit"] = "Submit";
		$crawler = self::request(
			'POST', 'adm/index.php?i=acp_board&mode=auth&sid=' . $this->sid,
			$form_values);

		$this->assert_filter($crawler, 'div.errorbox');
		$nodes = $crawler->filter('div.errorbox');
		$expect_text = $this->lang(
			'REMOTE_USER_INVALID_USERNAME', '', 'admin');

		$this->assertContains($expect_text, $nodes->text());
		$this->logout();
	}

	// If we do provide the X-Remote-User header then the REMOTE_USER
	// cgivar will contain whatever value we set. The admin control panel
	// should allow us to enable the provider in this case.
	public function test_withheader()
	{
		$this->login();
		$this->admin_login();
		$this->add_lang_ext("cheshirekow/remoteuserauth",
			"info_acp_remoteuserauth");
		$crawler = self::request(
			'GET', 'adm/index.php?i=acp_board&mode=auth&sid=' . $this->sid);

		$form = $crawler->filter('#acp_board')->form();
		$form_values = $form->getValues();
		$form_values["config[auth_method]"] = "remoteuser";
		$form_values["submit"] = "Submit";
		self::$client->setHeader("X-Remote-User", 'admin');
		$crawler = self::request(
			'POST', 'adm/index.php?i=acp_board&mode=auth&sid=' . $this->sid,
			$form_values);

		$this->assert_filter($crawler, 'div.successbox');
		$nodes = $crawler->filter('div.successbox');
		$expect_text = $this->lang('CONFIG_UPDATED');
		$this->assertContains($expect_text, $nodes->text());

		// restore "db" as the auth_method so we don't leave the database
		// in a weird state.
		$form_values["config[auth_method]"] = "db";
		$crawler = self::request(
			'POST', 'adm/index.php?i=acp_board&mode=auth&sid=' . $this->sid,
			$form_values);
		self::$client->removeHeader("X-Remote-User");

		$this->assert_filter($crawler, 'div.successbox');
		$nodes = $crawler->filter('div.successbox');
		$expect_text = $this->lang('CONFIG_UPDATED');
		$this->assertContains($expect_text, $nodes->text());

		$this->logout();
	}
}
