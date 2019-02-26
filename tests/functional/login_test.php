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
