<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Comment Uploads
 *
 * @package		Solspace:Comment Uploads
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2020, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/legacy/comment-uploads
 * @license		https://docs.solspace.com/license-agreement/
 * @version		2.1.0
 */

require_once 'constants.comment_uploads.php';

$config['name']    								= 'Comment Uploads';
$config['version'] 								= COMMENT_UPLOADS_VERSION;
$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/comment_uploads';
