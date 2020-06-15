<?php

/**
 * Comment Uploads
 *
 * @package		Solspace:Comment Uploads
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2020, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/legacy/comment-uploads
 * @license		https://docs.solspace.com/license-agreement/
 * @version		3.0.0
 */

require_once (__DIR__."/addon.setup.php");

class Comment_uploads_ext
{
	public $settings		= array();
	public $default_settings = array();
	public $name			= 'Comment Uploads';
	public $version			= '3.0.0';
	public $description		= '';
	public $settings_exist	= 'n';
	public $docs_url		= '';
	public $extension_name = __CLASS__;
	public $cache = array();
	private $encrypt_key    = 'cuep7ich9dec8myit7foc3payt7hot2ab4on8faic6yoc6ov9d';

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct($settings = array())
	{
		// --------------------------------------------
		//  Settings
		// --------------------------------------------

		$this->settings = $settings;
	}
	// END Comment_uploads_extension_base()


	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 *
	 * @access	public
	 * @return	null
	 */

	public function activate_extension()
	{

		$this->default_settings = serialize($this->default_settings);

		$default = array(
			'class'        => $this->extension_name,
			'settings'     => $this->default_settings,
			'priority'     => 10,
			'version'      => $this->version,
			'enabled'      => 'y'
		);


		$this->hooks = array(
			'upload_files' => array_merge(
				$default,
				array(
					'method'	=> 'upload_files',
					'hook'		=> 'insert_comment_start'
				)
			),

			'process_uploaded_files' => array_merge(
				$default,
				array(
					'method'	=> 'process_uploaded_files',
					'hook'		=> 'insert_comment_end'
				)
			),

			'change_enctype_attribute' => array_merge(
				$default,
				array(
					'method'	=> 'change_enctype_attribute',
					'hook'		=> 'comment_form_end'
				)
			),

			'add_hidden_field' => array_merge(
				$default,
				array(
					'method'	=> 'add_hidden_field',
					'hook'		=> 'comment_form_hidden_fields'
				)
			),

			'modify_tagdata' => array_merge(
				$default,
				array(
					'method'	=> 'modify_tagdata',
					'hook'		=> 'comment_entries_tagdata'
				)
			),

			'delete_comment_files' => array_merge(
				$default,
				array(
					'method'		=> 'delete_comment_files',
					'hook'			=> 'delete_comment_additional'
				)
			),

			'delete_entry_files' => array_merge(
				$default,
				array(
					'method'		=> 'delete_entry_files',
					'hook'			=> 'delete_entries_loop'
				)
			),
		);

		//if exp_comments_uploads tables isn't already here, create it
		$sql = "
			CREATE TABLE IF NOT EXISTS exp_comments_uploads (
				upload_id int(10) unsigned NOT NULL auto_increment,
				comment_id int(10) unsigned NOT NULL DEFAULT 0,
				entry_id int(10) unsigned NOT NULL DEFAULT 0,
				uploaded_file_path text,
				uploaded_file_url text,
				uploaded_file_name VARCHAR(255) DEFAULT '',
				uploaded_file_original_name VARCHAR(255) DEFAULT '',
				uploaded_file_type VARCHAR(255) DEFAULT '',
				uploaded_file_size INT unsigned DEFAULT 0,
				uploaded_file_is_image CHAR(1) DEFAULT 'y',
				uploaded_file_width CHAR(5) DEFAULT '0',
				uploaded_file_height CHAR(5) DEFAULT '0',
				uploaded_file_img_type VARCHAR(255) DEFAULT '',
				uploaded_file_size_str VARCHAR(255) DEFAULT '',
				PRIMARY KEY (upload_id),
				KEY (comment_id),
				KEY (entry_id)
			)";

		ee()->db->query($sql);

		// Get upload preferences
		$this->get_upload_prefs(FALSE, TRUE);

		// Does the upload directory already exist?
		if ($this->upload_pref == 0)
		{
			// Create a new EE upload directory, but only if we are first
			// able to create the directory we want.
			$success = FALSE;
			if (! is_dir($this->upload_path))
			{
				if (@mkdir($this->upload_path, 0777, TRUE))
				{
					$success = TRUE;
				}
			}
			elseif (is_dir($this->upload_path))
			{
				$success = TRUE;
			}
		}
		else
		{
			// Don't create the upload preference if it already exists
			$success = FALSE;
		}

		if ($success === TRUE)
		{
			$query = '';

			$query = ee()->db->insert_string('exp_upload_prefs', array(
					// 'id'				=> '',
					'site_id'			=> ee()->config->item('site_id'),
					'name'				=> 'Comment Uploads Directory',
					'server_path'		=> $this->upload_path,
					'url'				=> $this->upload_dir,
					'allowed_types'		=> 'all',
					'max_size'			=> '',
					'max_height'		=> '',
					'max_width'			=> '',
					'properties'		=> '',
					'pre_format'		=> '',
					'post_format'		=> '',
					'file_properties'	=> '',
					'file_pre_format'	=> '',
					'file_post_format'	=> ''
				)
			);

			ee()->db->query( $query );
		}

		//Comment_uploads 1.x Specific Upgrade Cleanup
		//Previously the EE1.x extension had an _ext class suffix.
		//By convention this is now _extension,
		//so we'll clean up any hooks that may have been left over
		//from a possible previous install
		$sql = "DELETE FROM exp_extensions WHERE class = 'Comment_uploads_ext'";

		ee()->db->query( $sql );


		foreach($this->hooks as $key => $hook)
		{
			ee()->db->insert('exp_extensions', $hook);
		}
	}
	// END activate_extension()


	// --------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * @access	public
	 * @return	null
	 */

	public function disable_extension()
	{

		ee()->db->query("DROP TABLE IF EXISTS exp_comments_uploads");

		ee()->db->query("DELETE FROM exp_extensions WHERE class = '".__CLASS__."'");

	}
	// END disable_extension()

	// --------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 *
	 * @access	public
	 * @return	null
	 */

	public function update_extension()
	{

	}
	// END update_extension()

	// --------------------------------------------------------------------


	public function settings_form()
	{
		return "This page intentionally left blank";
	}

	/**
	 * Get upload preferences
	 *
	 * @return	null
	 */

	function get_upload_prefs($require_param = FALSE, $cp_call = FALSE)
	{

		if (ee()->session->cache('comment_uploads', 'upload_prefs'))
		{

			$this->upload_pref = ee()->session->cache('comment_uploads', 'upload_prefs')['upload_pref'];
			$this->upload_path = ee()->session->cache('comment_uploads', 'upload_prefs')['server_path'];
			$this->upload_dir = ee()->session->cache('comment_uploads', 'upload_prefs')['url'];
			$this->upload_types = ee()->session->cache('comment_uploads', 'upload_prefs')['allowed_types'];
			$this->upload_max_size = ee()->session->cache('comment_uploads', 'upload_prefs')['max_size'];
			$this->upload_max_height = ee()->session->cache('comment_uploads', 'upload_prefs')['max_height'];
			$this->upload_max_width = ee()->session->cache('comment_uploads', 'upload_prefs')['max_width'];

			return;
		}

		if(isset(ee()->TMPL) AND is_object(ee()->TMPL))
		{

			// First see if we've been given a parameter specifying the upload directory to use
			if (ee()->TMPL->fetch_param('upload_dir_id') != '' OR ee()->TMPL->fetch_param('upload_dir_name') != '')
			{
				if ($id = ee()->TMPL->fetch_param('upload_dir_id'))
				{
					$query = ee()->db->query("
						SELECT id, server_path, url, allowed_types, max_size, max_height, max_width
						FROM exp_upload_prefs
						WHERE id = '". ee()->db->escape_str($id) ."'
						LIMIT 1
						");
				}
				elseif ($name = ee()->TMPL->fetch_param('upload_dir_name'))
				{
					$query = ee()->db->query("
						SELECT id, server_path, url, allowed_types, max_size, max_height, max_width
						FROM exp_upload_prefs
						WHERE name = '". ee()->db->escape_str($name) ."'
						LIMIT 1
						");
				}
			}
			elseif (ee()->input->get_post('upload_dir') !== FALSE && ee()->input->get_post('upload_dir') != '')
			{
				ee()->load->library('encrypt');
				$dir = ee()->encrypt->decode(ee()->input->get_post('upload_dir'), $this->encrypt_key);

				$query = ee()->db->query("
					SELECT id, server_path, url, allowed_types, max_size, max_height, max_width
					FROM exp_upload_prefs
					WHERE id = '". ee()->db->escape_str($dir) ."'
					LIMIT 1
					");
			}
		}
		elseif (ee()->input->get_post('upload_dir') !== FALSE && ee()->input->get_post('upload_dir') != '')
		{
			ee()->load->library('encrypt');
			$dir = ee()->encrypt->decode(ee()->input->get_post('upload_dir'), $this->encrypt_key);

			$query = ee()->db->query("
				SELECT id, server_path, url, allowed_types, max_size, max_height, max_width
				FROM exp_upload_prefs
				WHERE id = '". ee()->db->escape_str($dir) ."'
				LIMIT 1
				");
		}


		// Leave if we have what we need
		if (isset($query) && $query->num_rows() > 0)
		{
			$cache_data = array();
			$this->upload_pref = $cache_data['upload_pref'] = $query->row('id');
			$this->upload_path = $cache_data['server_path'] = $query->row('server_path');
			$this->upload_dir = $cache_data['url'] = $query->row('url');
			$this->upload_types = $cache_data['allowed_types'] = $query->row('allowed_types');
			$this->upload_max_size = $cache_data['max_size'] = $query->row('max_size');
			$this->upload_max_height = $cache_data['max_height'] = $query->row('max_height');
			$this->upload_max_width = $cache_data['max_width'] = $query->row('max_width');

			ee()->session->set_cache('comment_uploads', 'upload_prefs', $cache_data);

			return;
		}

		$query = ee()->db->query("
			SELECT id, server_path, url, allowed_types, max_size, max_height, max_width
			FROM exp_upload_prefs
			WHERE name = 'Comment Uploads Directory'
			LIMIT 1
			");

		if ($query->num_rows() > 0)
		{
			$cache_data = array();
			$this->upload_pref = $cache_data['upload_pref'] = $query->row('id');
			$this->upload_path = $cache_data['server_path'] = $query->row('server_path');
			$this->upload_dir = $cache_data['url'] = $query->row('url');
			$this->upload_types = $cache_data['allowed_types'] = $query->row('allowed_types');
			$this->upload_max_size = $cache_data['max_size'] = $query->row('max_size');
			$this->upload_max_height = $cache_data['max_height'] = $query->row('max_height');
			$this->upload_max_width = $cache_data['max_width'] = $query->row('max_width');

			ee()->session->set_cache('comment_uploads', 'upload_prefs', $cache_data);

			return;
		}

		// If $require_param === TRUE, we are required to have found our answer by this ponit.
		// If we're still stumped, scram.
		if ($require_param === TRUE)
		{
			return FALSE;
		}

		// Still here? I guess you're stuck with the defaults
		$cache_data = array();
		$this->upload_pref = $cache_data['upload_pref'] = 0;
		$this->upload_path = $cache_data['server_path'] = str_replace('avatars/', 'uploads/', ee()->config->item('avatar_path')) . 'comments/';
		$this->upload_dir = $cache_data['url'] = str_replace('avatars/', 'uploads/', ee()->config->item('avatar_url')) . 'comments/';
		$this->upload_types = $cache_data['allowed_types'] = '*';
		$this->upload_max_size = $cache_data['max_size'] = 0;
		$this->upload_max_height = $cache_data['max_height'] = 0;
		$this->upload_max_width = $cache_data['max_width'] = 0;

		ee()->session->set_cache('comment_uploads', 'upload_prefs', $cache_data);
	}
	/* END get_upload_prefs() */

	/**
	 * Upload files
	 *
	 * @return	null
	 */

	function upload_files()
	{
		// No point in going on if there's no file to work with
		if ( ! isset($_FILES['userfile']) )
		{
			return;
		}

		// Get upload preferences
		$this->get_upload_prefs();


		// Collect the file(s) into an array
		if ( is_array($_FILES['userfile']['name']) )
		{
			$files = $_FILES;
			$file_count = count($_FILES['userfile']['name']);
		}
		else
		{
			$files = array(
				'userfile' => array(
					'name'		=> array($_FILES['userfile']['name']),
					'tmp_name'	=> array($_FILES['userfile']['tmp_name']),
					'size'		=> array($_FILES['userfile']['size']),
					'type'		=> array($_FILES['userfile']['type'])
				)
			);

			$file_count = 1;
		}

		//Use AOB's CI Upload library
		$config['upload_path'] = $this->upload_path;

		$config['allowed_types'] = ( $this->upload_types == 'all' ) ? '*': $this->upload_types;	// CI wants '*' but EE 2 wants 'all' in exp_upload_prefs.

		$config['max_size']	= $this->upload_max_size;

		$config['max_width']  = $this->upload_max_width;

		$config['max_height']  = $this->upload_max_height;

		ee()->load->library('upload');

		ee()->upload->initialize($config);



		// Our hands are a little tied. EE's upload class goes straight to
		// the $_FILES array for its data. Well, Brent, write your own
		// dang upload class. That, or get sneaky. I opt for the
		// latter.

		for($i = 0; $i < $file_count; $i++)
		{
			if ( trim($files['userfile']['name'][$i]) === '')
			{
				continue;
			}

			/** --------------------------------------------
			/**  Create Our Array, Making It Usable by EE's Upload Class
			/** --------------------------------------------*/

			$_FILES = array(
				'userfile' => array(
					'name'		=> $files['userfile']['name'][$i],
					'tmp_name'	=> $files['userfile']['tmp_name'][$i],
					'size'		=> $files['userfile']['size'][$i],
					'type'		=> $files['userfile']['type'][$i]
				)
			);

			$success = FALSE;

			// Let the Upload class do its magic
			if ( ee()->upload->do_upload())
			{
				$success = TRUE;
			}
			else
			{
				$error = array('error' => ee()->upload->display_errors());
			}

			// If the upload fails, we'll throw an error
			if ( ! $success )
			{
				return ee()->output->fatal_error(ee()->lang->line('upload_failed') . $error['error']);
			}

			$file_data = ee()->upload->data();

			// Save the file info for later

			$file_info = ee()->session->cache('comment_uploads', 'files') ? ee()->session->cache('comment_uploads', 'files') : array();

			$file_info[] = array(
				'path'		=> $file_data['file_path'],
				'image_path'=> $file_data['full_path'],
				'url'		=> $this->upload_dir.$file_data['file_name'],
				'name'		=> $file_data['file_name'],
				'type'		=> $file_data['file_type'],
				'size'		=> $file_data['file_size'],
				'is_image'	=> $file_data['is_image'],
				'width'		=> $file_data['image_width'],
				'height'	=> $file_data['image_height'],
				'img_type'	=> $file_data['image_type'],
				'size_str'	=> $file_data['image_size_str'],
			);

			ee()->session->set_cache('comment_uploads', 'files', $file_info);
		}
	}
	/* END upload_files() */


	/**
	 * Process uploaded files
	 *
	 * @return	null
	 */

	function process_uploaded_files($data, $moderate, $id)
	{
		if ( ! ee()->session->cache('comment_uploads', 'files') )
		{
			return;
		}

		$success = array();

		$number = 0;

		foreach( ee()->session->cache('comment_uploads', 'files') as $k => $filedata )
		{
			$number++;

			// Create new filename
			$new_name = $data['entry_id'] . '-' . $id . '-' . date('YmdHis') . '-' . $number;

			// Find the extension
			$exts = explode('.', $filedata['name']);
			$n = count($exts)-1;
			$ext = (isset($exts[$n])) ? $exts[$n] : '';

			// Modify the filename
			$name = $new_name . '.' . $ext;

			// Rename the file
			if ( rename($filedata['image_path'], $filedata['path'].$name))
			{
				// Add the file to the DB
				$newdata = array(
					// 'upload_id'						=> '',
					'comment_id'					=> $id,
					'entry_id'						=> $data['entry_id'],
					'uploaded_file_path'			=> $filedata['path'].$name,
					'uploaded_file_url'				=> str_replace($filedata['name'], $name, $filedata['url']),
					'uploaded_file_name'			=> $name,
					'uploaded_file_original_name'	=> $filedata['name'],
					'uploaded_file_type'			=> $filedata['type'],
					'uploaded_file_size'			=> $filedata['size'],
					'uploaded_file_is_image'		=> ($filedata['is_image'] == TRUE) ? 'y' : 'n',
					'uploaded_file_width'			=> $filedata['width'],
					'uploaded_file_height'			=> $filedata['height'],
					'uploaded_file_img_type'		=> $filedata['img_type'],
					'uploaded_file_size_str'		=> $filedata['size_str']
				);
				ee()->db->query(ee()->db->insert_string('exp_comments_uploads', $newdata));
			}
		}
	}
	/* END process_uploaded_files() */


	/**
	 * Modify tagdata
	 *
	 * @return
	 * @param	str	$tagdata	Comment entries tagdata
	 * @param	arr	$data		Data for the current comment
	 */

	function modify_tagdata($tagdata = '', $data = array())
	{
		// ee()->load->library('logger');
		// ee()->logger->developer('Parsing Comment ID: '.$data['comment_id']);

		// No reason to go on if none of our variables is present
		if ( ! strstr($tagdata, LD.'uploaded_files') )
		{
			//are we trying to delete files?
			//if we are carry on
			//we'll check they're valid to delete laters
			if( ! ee()->TMPL->fetch_param('delete_uploaded_file') AND !ee()->TMPL->fetch_param('delete_files_from_comment') ) return $tagdata;
		}

		// Set the cache, but do the query if on a multi-entry page with changing entry_ids
		if ( ! ee()->session->cache('comment_uploads', 'data') || ( isset(ee()->session->cache('comment_uploads', 'data')['cu_entry_id']) && ee()->session->cache('comment_uploads', 'data')['cu_entry_id'] != ee()->db->escape_str($data['entry_id']) ) )
		{
			$sql = "/* Comment Uploads modify_tagdata */
				SELECT *
				FROM exp_comments_uploads
				WHERE entry_id = '".ee()->db->escape_str($data['entry_id'])."'
				";

			$query = ee()->db->query( $sql );

			if($query->num_rows() > 0)
			{
				foreach ( $query->result_array() as $row )
				{
					$cache_data[$row['comment_id']][]	= $row;
					$cache_data['cu_entry_id']			= ee()->db->escape_str($data['entry_id']);
				}

				ee()->session->set_cache('comment_uploads', 'data', $cache_data);
			}
		}

		$tagdata = $this->_fetch_last_call_data( $tagdata );
		$files = (isset(ee()->session->cache('comment_uploads', 'data')[$data['comment_id']])) ? ee()->session->cache('comment_uploads', 'data')[$data['comment_id']] : array();

		$count = ($files) ? count($files) : 0;
		$uploaded_files = ($count > 0) ? TRUE : FALSE;

		// Delete files
		$can_delete = $this->_user_can_delete_files($data['author_id'], $data['channel_id']);
		$deleted_file_data = array();
		$file_deleted = FALSE;
		$num_files_deleted = 0;

		if ( $this->_get_files_to_delete($data['comment_id']) === TRUE )
		{
			if ($can_delete === TRUE)
			{
				// Any single file deleted?
				if ( ee()->TMPL->fetch_param('delete_uploaded_file') )
				{
					$deleted_file_data = $this->delete_file($data['comment_id']);
				}
				// How about all of a comment's files?
				elseif ( ee()->TMPL->fetch_param('delete_files_from_comment') )
				{
					$deleted_file_data = $this->delete_comment_files(FALSE);
				}

				$file_deleted = ( empty($deleted_file_data) ) ? FALSE : TRUE;
				$num_files_deleted = (is_array($deleted_file_data)) ? count($deleted_file_data) : 0;
			}
		}

		$count -= $num_files_deleted;

		$cvars = array(
			'uploaded_files_count'			=> $count, // deprecated
			'uploaded_files_total'			=> $count,
			'uploaded_file_user_can_delete'	=> $can_delete,
			'uploaded_files'				=> $uploaded_files,
			'deleted_files'					=> $file_deleted,
			'deleted_files_count'			=> $num_files_deleted
		);

		$tagdata = ee()->functions->prep_conditionals($tagdata, $cvars);
		$tagdata = ee()->TMPL->swap_var_single('uploaded_files_count', $count, $tagdata); // deprecated
		$tagdata = ee()->TMPL->swap_var_single('uploaded_files_total', $count, $tagdata);


		// Does the {uploaded_files}{/uploaded_files} var pair exist?
		if ( $count > 0 && strstr($tagdata, LD."/".'uploaded_files'.RD) )
		{
			if ( preg_match('/'.LD.'uploaded_files'.RD.'(.+?)'.LD.preg_quote("/",'/').'uploaded_files'.RD.'/s', $tagdata, $match) )
			{
				$temp_tagdata = '';
				$fcount = 0;
				foreach( $files as $filedata )
				{
					if (isset($deleted_file_data[$filedata['upload_id']])) continue;

					$fcount++;
					$vars = array();
					$filedata['uploaded_file_id'] = $filedata['upload_id'];
					$filedata['uploaded_file_count'] = $fcount;
					$filedata['uploaded_file_is_image'] = ($filedata['uploaded_file_is_image'] == 'y') ? TRUE : FALSE;
					$filedata['uploaded_file_size_kb'] = number_format($filedata['uploaded_file_size'] / 1024, 2);
					$filedata['uploaded_file_size_mb'] = number_format($filedata['uploaded_file_size_kb'] / 1024, 2);
					$tdata = ee()->functions->prep_conditionals($match[1], $filedata);
					foreach( $filedata as $k => $v )
					{
						// Why doesn't swap_var_single accept array input? *sigh*
						$tdata = ee()->TMPL->swap_var_single($k, $v, $tdata);
					}
					$temp_tagdata .= $tdata;
				}
				$tagdata = str_replace($match[0], $temp_tagdata, $tagdata);
			}
		}
		elseif ( $count == 0 )
		{
			$tagdata = preg_replace('/'.LD.'uploaded_files'.RD.'(.+?)'.LD.preg_quote("/",'/').'uploaded_files'.RD.'/s', '', $tagdata);
		}

		// Does the {deleted_files}{/deleted_files} var pair exist?
		if ( $num_files_deleted > 0 && strstr($tagdata, LD."/".'deleted_files'.RD) )
		{
			$total = count($deleted_file_data);
			$tagdata = ee()->TMPL->swap_var_single('deleted_files_total', $total, $tagdata);
			if ( preg_match('/'.LD.'deleted_files'.RD.'(.+?)'.LD.preg_quote("/",'/').'deleted_files'.RD.'/s', $tagdata, $match) )
			{
				$temp_tagdata = '';
				$fcount = 0;
				foreach( $deleted_file_data as $filedata )
				{
					$fcount++;
					$vars = array();
					$filedata['deleted_file_count'] = $fcount;
					$filedata['deleted_file_is_image'] = ($filedata['uploaded_file_is_image'] == 'y') ? TRUE : FALSE;
					$filedata['deleted_file_size_kb'] = number_format($filedata['uploaded_file_size'] / 1024, 2);
					$filedata['deleted_file_size_mb'] = number_format($filedata['deleted_file_size_kb'] / 1024, 2);
					$tdata = ee()->functions->prep_conditionals($match[1], $filedata);
					foreach( $filedata as $k => $v )
					{
						// Why doesn't swap_var_single accept array input? *sigh*
						$tdata = ee()->TMPL->swap_var_single(str_replace('uploaded', 'deleted', $k), $v, $tdata);
					}
					$temp_tagdata .= $tdata;
				}
				$tagdata = str_replace($match[0], $temp_tagdata, $tagdata);
			}
		}
		elseif ( $count == 0 )
		{
			$tagdata = preg_replace('/'.LD.'deleted_files'.RD.'(.+?)'.LD.preg_quote("/",'/').'deleted_files'.RD.'/s', '', $tagdata);
		}

		return $tagdata;
	}
	/* END modify_tagdata() */

	/**
	 * Fetch last call data
	 *
	 * @return	mixed
	 * @param	mixed	$data	Alt data, if no last call data exists
	 */

	function _fetch_last_call_data($data)
	{

		return ( ee()->extensions->last_call !== FALSE ) ? ee()->extensions->last_call : $data;

	}
	/* END _fetch_last_call_data() */

	function _user_can_delete_files($author_id, $weblog_id)
	{

		// Super Admins are special
		if ( ee()->session->userdata['group_id'] == 1 )
		{
			return TRUE;
		}
		// Folks who can edit or moderate comments are in
		if ( ee()->session->userdata['can_edit_all_comments'] == 'y' OR ee()->session->userdata['can_moderate_comments'] == 'y')
		{
			return TRUE;
		}
		// The author of the comment can delete her own files.
		// But not guests!
		elseif ( $author_id != 0 && ee()->session->userdata['member_id'] == $author_id && ee()->session->userdata['can_edit_own_comments'] == 'y' )
		{
			return TRUE;
		}
		/* TODO
		elseif ( $this->_group_can_delete_fields($weblog_id) === TRUE )
		{
			return TRUE;
		}
		*/

		return FALSE;
	}
	/* END _user_can_delete_files() */


	// --------------------------------------------------------------------

	function _get_files_to_delete($comment_id = FALSE, $upload_id = FALSE)
	{


		if ( ee()->TMPL->fetch_param('delete_uploaded_file') == '' && ee()->TMPL->fetch_param('delete_files_from_comment') == '')
		{
			return FALSE;
		}

		if ( ! ee()->session->cache('comment_uploads', 'delete') )
		{
			if ( $file = ee()->TMPL->fetch_param('delete_uploaded_file') )
			{
				$query = ee()->db->query('	SELECT *
										FROM exp_comments_uploads
										WHERE upload_id = '. ee()->db->escape_str($file) .'
										LIMIT 1
										');
				if ($query->num_rows() > 0)
				{
					foreach ($query->result_array() as $row)
					{
						$cached_data = ee()->session->cache('comment_uploads', 'delete') ? ee()->session->cache('comment_uploads', 'delete') : array(
							'upload_id' => array(),
							'comment_id' => array(),
						);

						$cached_data['upload_id'][$row['upload_id']] = $row;
						$cached_data['comment_id'][$row['comment_id']][$row['upload_id']] = $row;

						ee()->session->set_cache('comment_uploads', 'delete', $cached_data);

						// ee()->session->set_cache('comment_uploads', 'delete', $cached_data)['upload_id'][$row['upload_id']] = $row;
						// ee()->session->set_cache('comment_uploads', 'delete', $cached_data)['comment_id'][$row['comment_id']][$row['upload_id']] = $row;
					}
				}
				else
				{
					ee()->session->set_cache('comment_uploads', 'delete', array());
				}
			}
			elseif ( $comment = ee()->TMPL->fetch_param('delete_files_from_comment') )
			{
				$query = ee()->db->query('	SELECT *
										FROM exp_comments_uploads
										WHERE comment_id = '. ee()->db->escape_str($comment)
				);
				if ($query->num_rows() > 0)
				{
					foreach ($query->result_array() as $row)
					{
						$cached_data = ee()->session->cache('comment_uploads', 'delete') ? ee()->session->cache('comment_uploads', 'delete') : array(
							'upload_id' => array(),
							'comment_id' => array(),
						);

						$cached_data['upload_id'][$row['upload_id']] = $row;
						$cached_data['comment_id'][$row['comment_id']][$row['upload_id']] = $row;

						ee()->session->set_cache('comment_uploads', 'delete', $cached_data);

						// ee()->session->set_cache('comment_uploads', 'delete')['upload_id'][$row['upload_id']] = $row;
						// ee()->session->set_cache('comment_uploads', 'delete')['comment_id'][$row['comment_id']][$row['upload_id']] = $row;
					}
				}
				else
				{
					ee()->session->set_cache('comment_uploads', 'delete', array());
				}
			}
		}

		if ($comment_id !== FALSE)
		{
			return (isset(ee()->session->cache('comment_uploads', 'delete')['comment_id'][$comment_id])) ? TRUE : FALSE;
		}
		elseif ($upload_id !== FALSE)
		{
			return (isset(ee()->session->cache('comment_uploads', 'delete')['upload_id'][$upload_id])) ? TRUE : FALSE;
		}
	}
	/* END _get_files_to_delete() */


	/**
	 * Add hidden field
	 *
	 * @return	array
	 * @param	array	$fields	Array of hidden fields
	 */
	function add_hidden_field($fields)
	{

		$fields = $this->_fetch_last_call_data( $fields );

		if ( is_numeric(ee()->TMPL->fetch_param('upload_dir_id')) OR ee()->TMPL->fetch_param('upload_dir_name') !== FALSE )
		{
			$dir = $this->get_upload_prefs(TRUE);
			if ($dir !== FALSE)
			{
				ee()->load->library('encrypt');
				$fields['upload_dir'] = ee()->encrypt->encode($this->upload_pref, $this->encrypt_key);
			}
		}

		return $fields;
	}
	/* END add_hidden_field() */


	/**
	 * Change enctype="" attribute
	 *
	 * @return	str	Modified comment form
	 * @param	str	$tagdata
	 */

	function change_enctype_attribute($tagdata = '')
	{


		$tagdata = $this->_fetch_last_call_data( $tagdata );

		if ( ee()->TMPL->fetch_param('enable_uploads') )
		{
			if ( strstr($tagdata, 'enctype'))
			{
				$tagdata = preg_replace('#enctype=["\'].*?["\']#', 'enctype="multipart/form-data"', $tagdata);
			}
			else
			{
				$tagdata = str_replace('action=', 'enctype="multipart/form-data" action=', $tagdata);
			}
		}

		return $tagdata;
	}
	/* END change_enctype_attribute() */


	/**
	 * Delete comment files
	 *
	 * @return	null
	 */

	function delete_comment_files($CP = TRUE)
	{


		$deleted_file_data = array();

		if ( $CP !== FALSE )
		{
			// Anybody trying to pull shenanigans and submit bad data?
			if (! preg_match("/^[0-9]+$/", str_replace('|', '', ee()->input->get_post('comment_ids'))))
			{
				return $DSP->no_access_message();
			}

			$ids = str_replace('|', ', ', ee()->input->get_post('comment_ids'));
			// Find the files to delete
			$query = ee()->db->query('
				SELECT uploaded_file_path
				FROM exp_comments_uploads
				WHERE comment_id IN ('. ee()->db->escape_str($ids) .')
				');

			// Dump 'em
			foreach ($query->result_array() as $row)
			{
				// First check to ensure the file exists
				if (is_file($row['uploaded_file_path']))
				{
					unlink($row['uploaded_file_path']);
				}
			}

			// Delete from the DB
			ee()->db->query('DELETE FROM exp_comments_uploads WHERE comment_id IN ('. ee()->db->escape_str($ids) .')');
		}
		else
		{
			$id = ee()->TMPL->fetch_param('delete_files_from_comment');
			if (! ee()->session->cache('comment_uploads', 'delete'))
			{
				$this->_get_files_to_delete();
			}

			if (! isset(ee()->session->cache('comment_uploads', 'delete')['comment_id'][$id]))
			{
				return FALSE;
			}

			// Dump 'em
			foreach (ee()->session->cache('comment_uploads', 'delete')['comment_id'][$id] as $row)
			{
				// First check to ensure the file exists
				if (is_file($row['uploaded_file_path']))
				{
					unlink($row['uploaded_file_path']);
				}
				$deleted_file_data[$row['upload_id']] = $row;
			}
			unset(ee()->session->cache('comment_uploads', 'delete')['comment_id'][$id]);

			// Delete from the DB
			ee()->db->query('DELETE FROM exp_comments_uploads WHERE comment_id IN ('. ee()->db->escape_str($id) .')');

			return $deleted_file_data;
		}
	}
	/* END delete_comment_files() */

	// --------------------------------------------------------------------

	/**
	 * Delete entry files
	 *
	 * @return	null
	 */

	function delete_entry_files($entry_id, $weblog_id)
	{


		// Find the files to delete
		$query = ee()->db->query('
			SELECT uploaded_file_path
			FROM exp_comments_uploads
			WHERE entry_id = '. ee()->db->escape_str($entry_id)
		);

		if ($query->num_rows() > 0)
		{
			// Dump 'em
			foreach ($query->result_array() as $row)
			{
				// Make sure it exists
				if (file_exists($row['uploaded_file_path']))
				{
					unlink($row['uploaded_file_path']);
				}
			}

			// Delete from the DB
			ee()->db->query('DELETE FROM exp_comments_uploads WHERE entry_id = '. ee()->db->escape_str($entry_id));
		}
	}
	/* END delete_entry_files() */


	function delete_file($comment_id)
	{


		$file = ee()->TMPL->fetch_param('delete_uploaded_file');

		if ( $file == '' OR ! is_integer($file*1) )
		{
			return FALSE;
		}

		if (! ee()->session->cache('comment_uploads', 'delete'))
		{
			$this->_get_files_to_delete();
		}

		if (isset(ee()->session->cache('comment_uploads', 'delete')['comment_id'][$comment_id]) && isset(ee()->session->cache('comment_uploads', 'delete')['upload_id'][$file]))
		{
			$deleted_file_data[ee()->session->cache('comment_uploads', 'delete')['upload_id'][$file]['upload_id']] = ee()->session->cache('comment_uploads', 'delete')['upload_id'][$file];

			// Check to ensure the file exists
			if (is_file(ee()->session->cache('comment_uploads', 'delete')['upload_id'][$file]['uploaded_file_path']))
			{
				unlink(ee()->session->cache('comment_uploads', 'delete')['upload_id'][$file]['uploaded_file_path']);
			}
			unset(ee()->session->cache('comment_uploads', 'delete')['comment_id'][$comment_id]);
			unset(ee()->session->cache('comment_uploads', 'delete')['upload_id'][$file]);

			ee()->db->query('DELETE FROM exp_comments_uploads WHERE upload_id = '. ee()->db->escape_str($file));

			return $deleted_file_data;
		}

		return FALSE;
	}
	/* END delete_file() */

}
// END Class Comment_uploads_extension