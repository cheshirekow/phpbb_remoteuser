<?php
// Copyright 2019 Josh Bialkowski <josh.bialkowski@gmail.com>
namespace cheshirekow\remoteuserauth;

/**
 * RemoteUser authentication with database fallback provider for phpBB3
 */
class provider extends \phpbb\auth\provider\db
{
	/**
	 * {@inheritdoc}
	 */
	public function init()
	{
		if (empty($config['remote_user_varname']))
		{
			$this->config['remote_user_varname'] = "REMOTE_USER";
		}
		$varname = $this->config['remote_user_varname'];

		if (!$this->request->is_set($varname,
			\phpbb\request\request_interface::SERVER))
		{
			return $this->user->lang['REMOTE_USER_SETUP_BEFORE_USE'];
		}

		$remoteuser = htmlspecialchars_decode(
			$this->request->server($varname));
		if ($this->user->data['username'] !== $remoteuser)
		{
			return sprintf($this->user->lang['REMOTE_USER_INVALID_USERNAME'],
				$remoteuser, $this->user->data['username']);
		}
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function login($username, $password)
	{
		// Auth plugins get the password untrimmed.
		// For compatibility we trim() here.
		$password = trim($password);

		if (!$username)
		{
			return array(
				'status' => LOGIN_ERROR_USERNAME,
				'error_msg' => 'LOGIN_ERROR_USERNAME',
				'user_row' => array('user_id' => ANONYMOUS),
			);
		}
		$username_clean = utf8_clean_string($username);

		// If password is supplied then defer to base class (database) login
		// method
		if ($password)
		{
			return parent::login($username, $password);
		}

		$err = array(
			'status' => LOGIN_ERROR_PASSWORD,
			'error_msg' => 'NO_PASSWORD_SUPPLIED',
			'user_row' => array('user_id' => ANONYMOUS),
		);

		if (empty($config['remote_user_varname']))
		{
			$this->config['remote_user_varname'] = "REMOTE_USER";
		}
		$varname = $this->config['remote_user_varname'];

		// If no password was supplied and there is no pre-auth supplied by
		// the webserver, then we simply error out.
		if (!$this->request->is_set($varname,
			\phpbb\request\request_interface::SERVER))
		{
			return $err;
		}

		// If the requested username does not match the pre-auth supplied by
		// the webserver then we don't allow a login. We could ignore the
		// requested username but that might be unexpected.
		$remote_user = htmlspecialchars_decode($this->request->server($varname));
		if ($remote_user != $username_clean)
		{
			return $err;
		}

		// Successful login...
		$sql = 'SELECT * FROM ' . USERS_TABLE . "
            WHERE username_clean = '" .
		$this->db->sql_escape($username_clean) . "'";

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return array(
			'status' => LOGIN_SUCCESS,
			'error_msg' => false,
			'user_row' => $row,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function acp()
	{
		// These are fields required in the config table
		return array(
			'remote_user_varname',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_acp_template($new_config)
	{
		// NOTE(josh): this doesn't work
		// $tplpath = $this->phpbb_root_path.
		//     '/ext/cheshirekow/remoteuserauth' .
		//     '/adm/style/auth_provider_remoteuser.html';
		// NOTE(josh): $this->extension_manager is null
		// $tplpath = $this->extension_manager->get_extension_path(
		// 	'cheshirekow/remoteuserauth', true) .
		// 	'adm/style/auth_provider_remoteuser.hml';
		$tplpath = '../../ext/cheshirekow/remoteuserauth' .
			'/adm/style/auth_provider_remoteuser.html';

		return array(
			'TEMPLATE_FILE' => $tplpath,
			'TEMPLATE_VARS' => array(
				'AUTH_REMOTE_USER_VARNAME' => $new_config['remote_user_varname'],
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function autologin()
	{
		if (empty($config['remote_user_varname']))
		{
			$this->config['remote_user_varname'] = "REMOTE_USER";
		}
		$varname = $this->config['remote_user_varname'];
		if (!$this->request->is_set($varname,
			\phpbb\request\request_interface::SERVER))
		{
			return array();
		}

		$remote_user = htmlspecialchars_decode(
			$this->request->server($varname));

		if (!empty($remote_user))
		{
			set_var($remote_user, $remote_user, 'string', true);

			$sql = 'SELECT * FROM ' . USERS_TABLE . "
                WHERE username = '" . $this->db->sql_escape($remote_user) . "'";
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($row)
			{
				if ($row['user_type'] == USER_INACTIVE ||
					$row['user_type'] == USER_IGNORE)
				{
					return array();
				}
				else
				{
					return $row;
				}
			}

			if (!function_exists('user_add'))
			{
				include $this->phpbb_root_path
				. 'includes/functions_user.'
				. $this->php_ext;
			}

			// create the user if she does not exist yet
			user_add($this->user_row($remote_user));

			$userquery = $this->db->sql_escape(utf8_clean_string($remote_user));
			$sql = 'SELECT * FROM ' . USERS_TABLE . "
                WHERE username_clean = '" . $userquery . "'";

			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($row)
			{
				return $row;
			}
		}

		return array();
	}

	/**
	 * This function generates an array which can be passed to the user_add
	 * function in order to create a user
	 *
	 * @param   string  $username   The username of the new user.
	 * @return   array  Contains data that can be passed directly to
	 *                  the user_add function.
	 */
	private function user_row($username)
	{
		// first retrieve default group id
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $this->db->sql_escape('REGISTERED') . "'
				AND group_type = " . GROUP_SPECIAL;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error('NO_GROUP');
		}

		// generate user account data
		return array(
			'username' => $username,
			'user_password' => substr(md5(rand()), 0, 7),
			'user_email' => '',
			'group_id' => (int) $row['group_id'],
			'user_type' => USER_NORMAL,
			'user_ip' => $this->user->ip,
			'user_new' => ($this->config['new_member_post_limit']) ? 1 : 0,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function validate_session($user)
	{
		if (empty($config['remote_user_varname']))
		{
			$this->config['remote_user_varname'] = "REMOTE_USER";
		}
		$varname = $this->config['remote_user_varname'];

		if ($this->request->is_set($varname,
			\phpbb\request\request_interface::SERVER))
		{
			$remote_user = $this->request->server($varname);
			if ($remote_user)
			{
				// Server has provided a pre-auth username. Check that it
				// matches the active username
				return ($remote_user === $user['username']);
			}
		}

		// Remote user identity is not provided by the server. A valid session
		// is determined by the user type.
		if ($user['user_type'] == USER_IGNORE)
		{
			return true;
		}
		return false;
	}
}
