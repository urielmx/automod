<?php
/** 
*
* @package acp
* @version $Id$
* @copyright (c) 2005 phpBB Group 
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

/**
* @package acp
*/
class acp_mods
{
	var $u_action;

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $cache;
		global $phpbb_root_path, $phpbb_admin_path, $phpEx, $table_prefix;

		// start the page
		$user->add_lang('acp/mods');
		$this->tpl_name = 'acp_mods';
		$this->page_title = 'ACP_CAT_MODS';

		include($phpbb_root_path . "includes/functions_transfer.$phpEx");

		// get any url vars
		$action = request_var('action', '');
		$mod_id = request_var('mod_id', 0);
		$mod_url = request_var('mod_url', '');
		$mod_path = request_var('mod_path', '');

		switch ($mode)
		{
			case 'frontend':

				switch ($action)
				{
					case 'pre_install':
						$this->pre_install($mod_path);
					break;
					
					case 'install':
						$this->install($mod_path);
					break;
					
					case 'pre_uninstall':
						$this->pre_uninstall($mod_id);
					break;
					
					case 'uninstall':
						$this->uninstall($mod_id);
					break;
					
					case 'details':
						$mod_ident = ($mod_id) ? $mod_id : $mod_path;
						$this->list_details($mod_ident);
					break;
					
					default:
						$template->assign_vars(array(
							'S_FRONTEND'		=> true)
						);
						
						$this->list_installed();
						$this->list_uninstalled();
					break;
				}

				return;

			break;

		}
	}

	/**
	* List all the installed mods
	*/
	function list_installed()
	{
		global $db, $template;

		$sql = 'SELECT mod_id, mod_name
			FROM ' . MODS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('installed', array(
				'MOD_ID'		=> $row['mod_id'],
				'MOD_NAME'		=> $row['mod_name'],

				'U_DETAILS'		=> $this->u_action . '&amp;action=details&amp;mod_id=' . $row['mod_id'],
				'U_UNINSTALL'	=> $this->u_action . '&amp;action=pre_uninstall&amp;mod_id=' . $row['mod_id'])
			);
		}
		$db->sql_freeresult($result);

		return;
	}

	/**
	* List all mods available locally
	*/
	function list_uninstalled()
	{
		global $phpbb_root_path, $db, $template;

		// get available MOD paths
		$available_mods = $this->find_mods("{$phpbb_root_path}store/mods");

		// get installed MOD paths
		$installed_paths = array();
		$sql = 'SELECT mod_path
			FROM ' . MODS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$installed_paths[] = $row['mod_path'];
		}
		$db->sql_freeresult($result);

		// we don't care about any xml files not in the main directory
		$available_mods = array_diff($available_mods['main'], $installed_paths);
		unset($installed_paths);

		// show only available MODs that paths aren't in the DB
		foreach ($available_mods as $file)
		{
			$details = $this->mod_details($file);

			$template->assign_block_vars('uninstalled', array(
				'MOD_NAME'	=> $details['MOD_NAME'],
				'MOD_PATH'	=> $details['MOD_PATH'],

				'U_INSTALL'	=> $this->u_action . '&amp;action=pre_install&amp;mod_path=' . $details['MOD_PATH'],
				'U_DETAILS'	=> $this->u_action . '&amp;action=details&amp;mod_path=' . $details['MOD_PATH'],
			));
		}

		return;
	}

	/**
	* Lists mod details
	*/
	function list_details($mod_ident)
	{
		global $template;

		$template->assign_vars(array(
			'S_DETAILS'	=> true,
			'U_BACK'	=> $this->u_action
		));				

		$details = $this->mod_details($mod_ident);

		if (!empty($details['AUTHOR_DETAILS']))
		{
			foreach ($details['AUTHOR_DETAILS'] as $author_details)
			{
				$template->assign_block_vars('author_list', $author_details);
			}
			unset($details['AUTHOR_DETAILS']);
		}

		if (!empty($details['MOD_HISTORY']))
		{
			$template->assign_var('S_CHANGELOG', true);

			foreach ($details['MOD_HISTORY'] as $mod_version)
			{
				$template->assign_block_vars('changelog', array(
					'VERSION'	=> $mod_version['VERSION'],
					'DATE'		=> $mod_version['DATE'],
				));

				foreach ($mod_version['CHANGES'] as $changes)
				{
					$template->assign_block_vars('changelog.changes', array(
						'CHANGE'	=> $changes,
					));
				}
			}
		}

		unset($details['MOD_HISTORY']);

		$template->assign_vars($details);

		if (!empty($details['AUTHOR_NOTES']))
		{
			$template->assign_var('S_AUTHOR_NOTES', true);
		}
		if (!empty($details['MOD_INSTALL_TIME']))
		{
			$template->assign_var('S_INSTALL_TIME', true);
		}

		return;
	}

	/**
	* Returns array of mod information
	*/
	function mod_details($mod_ident)
	{
		global $phpbb_root_path, $phpEx;

		if (is_int($mod_ident))
		{
			global $db;

			$mod_id = intval($mod_ident);

			$sql = 'SELECT *
				FROM ' . MODS_TABLE . "
					WHERE mod_id = $mod_id";
			$result = $db->sql_query($sql);
			if ($row = $db->sql_fetchrow($result))
			{
				$details = array(
					'MOD_ID'			=> $row['mod_id'],
					'MOD_PATH'			=> $row['mod_path'],
					'MOD_INSTALL_TIME'	=> $row['mod_time'],
					'MOD_DEPENDENCIES'	=> unserialize($row['mod_dependencies']), // ?
					'MOD_NAME'			=> htmlspecialchars($row['mod_name']),
					'MOD_DESCRIPTION'	=> htmlspecialchars($row['mod_description']),
					'MOD_VERSION'		=> $row['mod_version'],

					'AUTHOR_NOTES'	=> $row['mod_author_notes'],
					'AUTHOR_NAME'	=> $row['mod_author_name'],
					'AUTHOR_EMAIL'	=> $row['mod_author_email'],
					'AUTHOR_URL'	=> $row['mod_author_url']
				);
			}
			else
			{
				// ERROR, MISSING MOD ID
				// temporary, to be sure we don't get random blank screens 
				die('no mod');
			}
			$db->sql_freeresult($result);
		}
		else
		{
			$mod_path = $mod_ident;

			$ext = substr(strrchr($mod_path, '.'), 1);

			include_once($phpbb_root_path . 'includes/mod_parser.' . $phpEx);
			$parser = new parser($ext);
			$parser->set_file($mod_path);

			$details = $parser->get_details();

			unset($parser);
		}

		return $details;
	}

	/**
	* Returns complex array of all mod actions
	*/
	function mod_actions($mod_ident)
	{
		global $phpbb_root_path, $phpEx;

		if (is_int($mod_ident))
		{
			global $db;
			
			$sql = 'SELECT mod_actions
				FROM ' . MODS_TABLE . "
					WHERE mod_id = $mod_ident";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			if ($row)
			{
				return unserialize($row['mod_actions']);
			}
			else
			{
				// @TODO: lang
				//trigger_error('NO SUCH MOD IN DB', E_USER_WARNING);
			}
		}
		else
		{
			$mod_path = $mod_ident;
			$actions = array();
			$ext = substr(strrchr($mod_path, '.'), 1);

			include_once($phpbb_root_path . 'includes/mod_parser.' . $phpEx);
			$parser = new parser($ext);
			$parser->set_file($mod_path);
			
			$actions = $parser->get_actions();

			unset($parser);
		}
		return $actions;
	}
	
	/**
	* Parses and displays all Edits, Copies, and SQL modifcations
	*/
	function pre_install($mod_path)
	{
		global $phpbb_root_path, $phpEx, $template, $db;

		// mod_path empty?
		if (empty($mod_path))
		{
			// ERROR
			return false;
		}
		
		$actions = $this->mod_actions($mod_path);
		$details = $this->mod_details($mod_path);

		// check for "child" MODX files and attempt to decide which ones we need
		$actions = $this->find_children($mod_path, $actions, 'pre_install');

		include($phpbb_root_path . 'includes/editor.' . $phpEx);
		$editor = new editor($phpbb_root_path);

		$mod_root = explode('/', str_replace($phpbb_root_path, '', $mod_path));
		array_pop($mod_root);
		$mod_root = implode('/', $mod_root) . '/';
		
		$dependenices = $details['MOD_DEPENDENCIES'];
						
		$template->assign_vars(array(
			'S_PRE_INSTALL'	=> true,
			
			'MOD_PATH'		=> $mod_path,

			'U_INSTALL'		=> $this->u_action . '&amp;action=install&amp;mod_path=' . $mod_path,
			'U_BACK'		=> $this->u_action,
		));

		// Show author notes
		if (!empty($details['AUTHOR_NOTES']))
		{
			$template->assign_vars(array(
				'S_AUTHOR_NOTES'	=> $details['AUTHOR_NOTES'],
				
				'AUTHOR_NOTES'		=> $details['AUTHOR_NOTES'],
			));
		}

		// Only display full actions if the user has requested them.
		if (!defined('DEBUG') || !isset($_GET['full_details']))
		{
			return;
		}

		// Show new files
		if (isset($actions['NEW_FILES']) && !empty($actions['NEW_FILES']))
		{
			$template->assign_var('S_NEW_FILES', true);

			foreach ($actions['NEW_FILES'] as $source => $target)
			{
				if (!file_exists("$phpbb_root_path$mod_root$source") && strpos($source, '*.*') !== false)
				{
					$template->assign_block_vars('new_files', array(
						'S_MISSING_FILE' => true,
						
						'SOURCE'		=> $source,
						'TARGET'		=> $target,
					));
				}
				else
				{
					$template->assign_block_vars('new_files', array(
						'SOURCE'		=> $source,
						'TARGET'		=> $target,
					));
				}
			}
		}
		
		// Show SQL
		if (isset($actions['SQL']) && !empty($actions['SQL']))
		{
			$template->assign_var('S_SQL', true);
			
			$this->parse_sql($actions['SQL']);

			foreach ($actions['SQL'] as $query)
			{
				$template->assign_block_vars('sql_queries', array(
					'QUERY'		=> $query,
				));
			}
		}
		
		// Show edits
		if (isset($actions['EDITS']) && !empty($actions['EDITS']))
		{
			$template->assign_var('S_EDITS', true);
			
			foreach ($actions['EDITS'] as $file => $finds)
			{
				if (!file_exists("$phpbb_root_path$file"))
				{
					$template->assign_block_vars('edit_files', array(
						'S_MISSING_FILE' => true,
						
						'FILENAME'	=> $file
					));
				}
				else
				{
					$template->assign_block_vars('edit_files', array(
						'FILENAME'	=> $file
					));
				
					$editor->open_file($file);

					$editor->fold_edits($file, $dependenices);
			
					foreach ($finds as $find => $action)
					{
						if (!$editor->check_find($file, $find))
						{
							$template->assign_block_vars('edit_files.finds', array(
								'S_MISSING_FIND'	=> true,

								'FIND_STRING'		=> htmlspecialchars($find)
							));
						}
						else
						{
							$template->assign_block_vars('edit_files.finds', array(
								'FIND_STRING'	=> htmlspecialchars($find)
							));

							// check if we have an inline find	
							if (isset($action['in-line-edit']))
							{
								foreach ($action['in-line-edit'] as $inline_find => $inline_action_ary)
								{
									$template->assign_block_vars('edit_files.finds.actions', array(
										'NAME'		=> 'In-Line Find', // LANG!
										'COMMAND'	=> (is_array($inline_find)) ? htmlspecialchars(implode('<br />', $inline_find)) : htmlspecialchars($inline_find),
									));

									if (!$editor->check_find($file, $inline_find))
									{
										$template->assign_block_vars('edit_files.finds', array(
											'S_MISSING_FIND'	=> true,

											'FIND_STRING'		=> htmlspecialchars($inline_find)
										));
									}
									else
									{
										foreach ($inline_action_ary as $inline_action => $inline_command)
										{
											$template->assign_block_vars('edit_files.finds.actions', array(
												'NAME'		=> $inline_action, // LANG!
												'COMMAND'	=> (is_array($inline_command)) ? htmlspecialchars(implode('<br />', $inline_command)) : htmlspecialchars($inline_command),
											));
										}
									}
								}
							}
							else
							{
								foreach ($action as $name => $command)
								{
									$template->assign_block_vars('edit_files.finds.actions', array(
										'NAME'		=> $name, // LANG!
										'COMMAND'	=> (is_array($command)) ? htmlspecialchars(implode('<br />', $command)) : htmlspecialchars($command),
									));
								}
							}
						}
					}
					
					$editor->unfold_edits($file, $dependenices);

					$editor->close_file($file);
				}
			}
		}

		return;
	}

	/**
	* Preforms all Edits, Copies, and SQL queries
	*/
	function install($mod_path)
	{
		global $phpbb_root_path, $phpEx, $db, $template;

		// mod_path empty?
		if (empty($mod_path))
		{
			// ERROR
			return false;
		}

		$actions = $this->mod_actions($mod_path);
		// check for "child" MODX files and attempt to decide which ones we need
		$actions = $this->find_children($mod_path, $actions, 'install');

		$details = $this->mod_details($mod_path);

		// Insert database data
		$sql = 'INSERT INTO ' . MODS_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'mod_time'			=> (int) time(),
			'mod_dependencies'	=> (string) serialize($details['MOD_DEPENDENCIES']),
			'mod_name'			=> (string) $details['MOD_NAME'],
			'mod_description'	=> (string) $details['MOD_DESCRIPTION'],
			'mod_version'		=> (string) $details['MOD_VERSION'],
			'mod_path'			=> (string) $details['MOD_PATH'],
			'mod_author_notes'	=> (string) $details['AUTHOR_NOTES'],
			'mod_author_name'	=> (string) $details['AUTHOR_NAME'],
			'mod_author_email'	=> (string) $details['AUTHOR_EMAIL'],
			'mod_author_url'	=> (string) $details['AUTHOR_URL'],
			'mod_actions'		=> (string) serialize($actions),
			// TODO: Utilize these columns
			'mod_languages'		=> '',
			'mod_styles'		=> '',
		));
		$db->sql_query($sql);

		// get mod id
		$mod_id = $db->sql_nextid();
		
		include($phpbb_root_path . 'includes/editor.' . $phpEx);
		$editor = new editor($phpbb_root_path);

		// get mod install root && make temporary edited folder root
		// @todo...don't explode and implode this quite so much
		$mod_root = explode('/', str_replace($phpbb_root_path, '', $mod_path));
		array_pop($mod_root);
		$mod_root = implode('/', $mod_root) . '/';
		$edited_root = "{$mod_root}_edited/";

		// get mod dependencies
		// This will be the _PHPBB ID_ for any mod that this mod requires
		// The fold/unfold edit functions below will ignore any edits preformed by any mod with the ID in this array
		$dependenices = $details['MOD_DEPENDENCIES'];
		$mod_phpbb_id = (!empty($details['PHPBB_ID'])) ? $details['PHPBB_ID'] : $mod_id;

		// preform file edits
		if (isset($actions['EDITS']) && !empty($actions['EDITS'])) // this is some beefy looping
		{
			$template->assign_var('S_EDITS', true);
	
			foreach ($actions['EDITS'] as $filename => $finds)
			{
				if (!file_exists("$phpbb_root_path$filename"))
				{
					$template->assign_block_vars('edit_files', array(
						'S_MISSING_FILE' => true,
						
						'FILENAME'	=> $filename,
					));
				}
				else
				{
					$template->assign_block_vars('edit_files', array(
						'FILENAME'	=> $filename,
					));
			
					$file_ext = substr(strrchr($filename, '.'), 1);
					
					$editor->open_file($filename);

					$editor->fold_edits($filename, $dependenices);
				
					foreach ($finds as $string => $commands)
					{
						$template->assign_block_vars('edit_files.finds', array(
							'FIND_STRING'	=> $string,
						));

						foreach ($commands as $type => $contents)
						{
							$status = false;
							$contents_orig = $contents;

							switch (strtoupper($type)) // LANG!
							{
								case 'AFTER ADD':
									$contents = $editor->add_anchor($contents, $file_ext, $mod_phpbb_id);
									$status = $editor->add_string($filename, $string, $contents, 'AFTER');
								break;
								
								case 'BEFORE ADD':
									$contents = $editor->add_anchor($contents, $file_ext, $mod_phpbb_id);
									$status = $editor->add_string($filename, $string, $contents, 'BEFORE', false);
								break;

								case 'INCREMENT':
									//$contents = "";
									$status = $editor->inc_string($filename, $string, $contents);
								break;

								case 'REPLACE WITH':
									//$contents = $editor->add_wrap($string, $file_ext, $mod_phpbb_id) . "\n{$contents}";
									$contents = $editor->add_anchor($contents, $file_ext, $mod_phpbb_id);
									$status = $editor->replace_string($filename, $string, $contents);
								break;

								case 'IN-LINE-EDIT':
									// these aren't quite as straight forward.  Still have multi-level arrays to sort through
									foreach ($contents as $inline_find => $inline_edit)
									{
										$line = $editor->check_find($filename, $inline_find, true);

										foreach ($inline_edit as $inline_action => $inline_contents)
										{
											$inline_contents = $inline_contents[0];
											$contents = $contents_orig = $inline_contents;
											switch (strtoupper($inline_action))
											{
												case 'IN-LINE-BEFORE-ADD':
													$status = $editor->add_string($filename, $inline_find, $inline_contents, 'BEFORE', true, $line['start'], $line['end']);
												break;

												case 'IN-LINE-AFTER-ADD':
													$status = $editor->add_string($filename, $inline_find, $inline_contents, 'AFTER', true, $line['start'], $line['end']);
												break;

												case 'IN-LINE-REPLACE':
													$status = $editor->replace_string($filename, $inline_find, $contents, $line['start'], $line['end']);
												break;

												default:
													die("Error, unrecognised command $type"); // ERROR!
												break;
											}
										}
									}
								break;

								default:
									die("Error, unrecognised command $type"); // ERROR!
								break;
							}

							$template->assign_block_vars('edit_files.finds.actions', array(
								'S_SUCCESS'	=> $status,

								'NAME'		=> $type, // LANG!
								'COMMAND'	=> htmlspecialchars($contents_orig),
							));
						}
					}

					$editor->unfold_edits($filename, $dependenices);

					$editor->close_file($filename, "$edited_root$filename");
				}
			}
		}

		// Move included files
		if (isset($actions['NEW_FILES']) && !empty($actions['NEW_FILES']))
		{
			$template->assign_var('S_NEW_FILES', true);
	
			foreach ($actions['NEW_FILES'] as $source => $target)
			{
				if (!$editor->copy_content($mod_root . str_replace('*.*', '', $source), str_replace('*.*', '', $target)))
				{
					$template->assign_block_vars('new_files', array(
						'SOURCE'		=> $source,
						'TARGET'		=> $target,
					));
				}
				else
				{
					$template->assign_block_vars('new_files', array(
						'S_SUCCESS'		=> true,
					
						'SOURCE'		=> $source,
						'TARGET'		=> $target,
					));
				}
			}
		}

		// Preform SQL queries
		if (isset($actions['SQL']) && !empty($actions['SQL']))
		{
			$template->assign_var('S_SQL', true);
			
			$this->parse_sql($actions['SQL']);
			
			$db->sql_return_on_error(true);
		
			foreach ($actions['SQL'] as $query)
			{
				if (!$db->sql_query($query)) // more than this please
				{
					$template->assign_block_vars('sql_queries', array(
						//'QUERY'		=> $row['mod_id'],
						'QUERY'		=> $query,
					));
				}
				else
				{
					$template->assign_block_vars('sql_queries', array(
						'S_SUCCESS'	=> true,

						'QUERY'		=> $query,
					));
				}
			}
			
			$db->sql_return_on_error(false);
		}

		// Display Do-It-Yourself Actions...per the MODX spec, these should be displayed last
		if (!empty($actions['DIY_INSTRUCTIONS']))
		{
			$template->assign_var('S_DIY', true);

			foreach ($actions['DIY_INSTRUCTIONS'] as $instruction)
			{
				$template->assign_block_vars('diy_instructions', array(
					'DIY_INSTRUCTION'	=> $instruction,
				));
			}
		}

		// Move edited files back, and delete temp stoarge folder
		$editor->copy_content($edited_root, '');
		
		// Add log
		add_log('admin', 'LOG_MOD_ADD', $details['MOD_NAME']);
		
		// Finish, by sending template data
		$template->assign_vars(array(
			'S_INSTALL'		=> true,

			'U_RETURN'		=> $this->u_action
		));
	}

	/**
	* Pre-Uninstall a mod
	*/
	function pre_uninstall($mod_id)
	{
		global $phpbb_root_path, $phpEx, $template;
		
		// mod_id blank?
		if (!$mod_id)
		{
			// ERROR
			return false;
		}
		
		$template->assign_vars(array(
			'S_PRE_UNINSTALL'		=> true,
			
			'MOD_ID'	=> $mod_id,

			'U_UNINSTALL'		=> $this->u_action . '&amp;action=uninstall&amp;mod_id=' . $mod_id,
			'U_BACK'			=> $this->u_action,
		));

		$actions = $this->mod_actions($mod_id);
		$details = $this->mod_details($mod_id);

		include($phpbb_root_path . 'includes/editor.' . $phpEx);
		$editor = new editor($phpbb_root_path);

		$dependenices = $details['MOD_DEPENDENCIES'];

		if (!empty($details['AUTHOR_NOTES']))
		{
			$template->assign_vars(array(
				'S_AUTHOR_NOTES' => true,
				
				'AUTHOR_NOTES' => $details['AUTHOR_NOTES'],
			));
		}

		// Show new files
		if (isset($actions['NEW_FILES']) && !empty($actions['NEW_FILES']))
		{
			$template->assign_var('S_REMOVING_FILES', true);

			foreach ($actions['NEW_FILES'] as $source => $target)
			{
				if (!file_exists("$phpbb_root_path$target") && strpos($source, '*.*') === false)
				{
					$template->assign_block_vars('removing_files', array(
						'S_MISSING_FILE' => true,

						'FILENAME'		=> $target
					));
				}
				else
				{
					$template->assign_block_vars('removing_files', array(
						'FILENAME'		=> $target)
					);
				}
			}
		}
		
		// Show SQL
		if (isset($actions['SQL']) && !empty($actions['SQL']))
		{
			$template->assign_var('S_SQL', true);
			
			$this->parse_sql($actions['SQL']);

			foreach ($actions['SQL'] as $query)
			{
				$reverse_query = $this->reverse_query($query);
				
				if ($reverse_query === false)
				{
					$template->assign_block_vars('sql_queries', array(
						'S_UNKNOWN_REVERSE' => true,

						'ORIGINAL_QUERY'	=> $query,
					));
				}
				else
				{
					$template->assign_block_vars('sql_queries', array(
						'ORIGINAL_QUERY'	=> $query,
						'REVERSE_QUERY'		=> $reverse_query)
					);
				}
			}
		}

		// Show edits
		if (isset($actions['EDITS']) && !empty($actions['EDITS']))
		{
			$template->assign_var('S_EDITS', true);
			
			$actions['EDITS'] = $this->reverse_edits($actions['EDITS']);

			foreach ($actions['EDITS'] as $file => $finds)
			{
				if (!file_exists("$phpbb_root_path$file"))
				{
					$template->assign_block_vars('edit_files', array(
						'S_MISSING_FILE' => true,
						
						'FILENAME'	=> $file
					));
				}
				else
				{
					$template->assign_block_vars('edit_files', array(
						'FILENAME'	=> $file
					));
				
					$editor->open_file($file);

					$editor->fold_edits($file, $dependenices);
			
					foreach ($finds as $find => $action)
					{
						if (!$editor->check_find($file, $find))
						{
							$template->assign_block_vars('edit_files.finds', array(
								'S_MISSING_FIND'	=> true,

								'FIND_STRING'		=> htmlspecialchars($find)
							));
						}
						else
						{
							$template->assign_block_vars('edit_files.finds', array(
								'FIND_STRING'	=> htmlspecialchars($find)
							));

							// check if we have an inline find	
							if (isset($action['in-line-find']))
							{
								foreach ($action['in-line-find'] as $inline_find => $inline_action_ary)
								{
									$template->assign_block_vars('edit_files.finds.actions', array(
										'NAME'		=> 'In-Line Find', // LANG!
										'COMMAND'	=> (is_array($inline_find)) ? htmlspecialchars(implode('<br />', $inline_find)) : htmlspecialchars($inline_find),
									));

									if (!$editor->check_find($file, $inline_find))
									{
										$template->assign_block_vars('edit_files.finds', array(
											'S_MISSING_FIND'	=> true,

											'FIND_STRING'		=> htmlspecialchars($inline_find)
										));
									}
									else
									{
										foreach ($inline_action_ary as $inline_action => $inline_command)
										{
											$template->assign_block_vars('edit_files.finds.actions', array(
												'NAME'		=> $inline_action, // LANG!
												'COMMAND'	=> (is_array($inline_command)) ? htmlspecialchars(implode('<br />', $inline_command)) : htmlspecialchars($inline_command),
											));
										}
									}
								}
							}
							else
							{
							
								foreach ($action as $name => $command)
								{
									$template->assign_block_vars('edit_files.finds.actions', array(
										'NAME'		=> $name, // LANG!
										'COMMAND'	=> (is_array($command)) ? htmlspecialchars(implode('<br />', $command)) : htmlspecialchars($command),
									));
								}
							}
						}
					}
					
					$editor->unfold_edits($file, $dependenices);

					$editor->close_file($file);
				}


			/*	if (!file_exists("$phpbb_root_path$file"))
				{
					$template->assign_block_vars('edit_files', array(
						'S_MISSING_FILE' => true,
						
						'FILENAME'	=> $file,
					));
				}
				else
				{
					$template->assign_block_vars('edit_files', array(
						'FILENAME'	=> $file,
					));
				
					$editor->open_file($file);

					$editor->fold_edits($file, $dependenices);
	
					foreach ($finds as $find => $actions)
					{
						if (!$editor->check_find($file, $find))
						{
							$template->assign_block_vars('edit_files.finds', array(
								'S_MISSING_FIND'	=> true,

								'FIND_STRING'	=> htmlspecialchars($find),
							));
						}
						else
						{
							$template->assign_block_vars('edit_files.finds', array(
								'FIND_STRING'	=> htmlspecialchars($find)
							));
							
							foreach ($actions as $name => $command)
							{
								$template->assign_block_vars('edit_files.finds.actions', array(
									'NAME'		=> $name, // LANG!
//									'COMMAND'	=> htmlspecialchars($command),

								));
							}
						}
					}
					
					$editor->unfold_edits($file, $dependenices);

					$editor->close_file($file);
				}
*/
			}
		}
	}
		
	/**
	* Uninstall a mod
	*/
	function uninstall($mod_id)
	{
		global $phpbb_root_path, $phpEx, $db, $template;

		// mod_id blank?
		if (!$mod_id)
		{
			// ERROR
			return false;
		}

		include($phpbb_root_path . 'includes/editor.' . $phpEx);
		$editor = new editor($phpbb_root_path);

		$template->assign_vars(array(
			'S_UNINSTALL'	=> true,

			'MOD_ID'		=> $mod_id,

			'U_RETURN'		=> $this->u_action,
		));

		$actions = $this->mod_actions($mod_id);
		$details = $this->mod_details($mod_id);

		if (!empty($details['AUTHOR_NOTES']))
		{
			$template->assign_vars(array(
				'S_AUTHOR_NOTES' => true,
				
				'AUTHOR_NOTES' => $details['AUTHOR_NOTES'])
			);
		}

		// Show new files
		if (isset($actions['NEW_FILES']) && !empty($actions['NEW_FILES']))
		{
			$template->assign_var('S_REMOVING_FILES', true);

			foreach ($actions['NEW_FILES'] as $source => $target)
			{
				// @todo: this will not delete files that were placed by an * command... 
				// because the complete file list is not currently stored.  
				if (!file_exists("$phpbb_root_path$target") && strpos($source, '*.*') !== false)
				{
					$template->assign_block_vars('removing_files', array(
						'S_MISSING_FILE' => true,

						'FILENAME'		=> $target)
					);
				}
				else
				{
					$editor->remove("$phpbb_root_path$target");
					
					$template->assign_block_vars('removing_files', array(
						'FILENAME'		=> $target)
					);
				}
			}
		}
		
		// reverse SQL
		if (isset($actions['SQL']) && !empty($actions['SQL']))
		{
			$template->assign_var('S_SQL', true);
			
			$this->parse_sql($actions['SQL']);
		
			foreach ($actions['SQL'] as $query)
			{
				$reverse_query = $this->reverse_query($query);
				
				if ($reverse_query === false)
				{
					continue;
				}
				
				$db->sql_return_on_error(true);
				
				if (!$db->sql_query($reverse_query)) // more than this please
				{
					$template->assign_block_vars('sql_queries', array(
						'QUERY'		=> $reverse_query,
					));
				}
				else
				{
					$template->assign_block_vars('sql_queries', array(
						'S_SUCCESS'	=> true,

						'QUERY'		=> $reverse_query,
					));
				}
				
				$db->sql_return_on_error(false);
			}
		}
		
		// Delete from DB
		$sql = 'DELETE FROM ' . MODS_TABLE . '
			WHERE mod_id = ' . $mod_id;
		$db->sql_query($sql);
		
		// Add log
		add_log('admin', 'LOG_MOD_REMOVE', $details['MOD_NAME']);
	}

	/**
	* Returns array of available mod install files in dir (Recursive)
	*/
	function find_mods($dir)
	{
		static $mods = array();

		$dp = opendir($dir);
		while (($file = readdir($dp)) !== false)
		{
			if ($file{0} != '.')
			{
				// recurse - we don't want anything within the MODX "root" though
				if (is_dir("$dir/$file") && $file != 'root/')
				{
					$mods = array_merge($mods, $this->find_mods("$dir/$file"));
				}
				// this might be better as an str function, especially being in a loop
				else if (preg_match('#xml$#i', $file))
				{
					// if this is an "extra" MODX file, make a record of it as such
					// we are assuming the MOD follows MODX packaging standards here
					if (preg_match('#(contrib|templates|languages)#i', $dir, $match))
					{
						$mods[$match[1]][] = "$dir/$file";
					}
					else
					{
						$mods['main'][] = "$dir/$file";
					}
				}
			}
		}

		return $mods;
	}

	/**
	* Returns the needed sql query to reverse the actions taken by the given query
	* @todo: Add more
	*/
	function reverse_query($orig_query)
	{
		if (preg_match('#ALTER TABLE\s([a-z_]+)\sADD(COLUMN|)\s([a-z_]+)#i', $orig_query, $matches))
		{
			return "ALTER TABLE {$matches[1]} DROP COLUMN {$matches[3]};";
		}
		else if (preg_match('#CREATE TABLE\s([a-z_])+#i', $orig_query, $matches))
		{
			return "DROP TABLE {$matches[1]};";
		}

		return false;
	}
	
	/**
	* Returns the edits array, but now filled with edits to reverse the given array
	* @todo: Add more
	*/
	function reverse_edits($orig_edits)
	{
		$reverse_edits = array();

		foreach ($orig_edits as $file => $finds)
		{
			foreach ($finds as $find => $actions)
			{
				foreach ($actions as $name => $command) // LANG!
				{
					switch (strtoupper($name))
					{
						case 'AFTER, ADD':
						case 'BEFORE, ADD':
							$find = $command;
							// replace with an empty string
							$reverse_edits[$file][$find]['REPLACE WITH'] = '';
						break;
						
						case 'REPLACE WITH':
						case 'REPLACE, WITH':
							// replace $command (new code) with $find (original code)
							$reverse_edits[$file][$command]['REPLACE WITH'] = $find;
						break;

						case 'IN-LINE-EDIT':
							// build the reverse just like the normal action
							foreach ($command as $inline_find => $inline_action_ary)
							{
								foreach ($inline_action_ary as $inline_action => $inline_command)
								{
									$inline_command = $inline_command[0];

									switch (strtoupper($inline_action))
									{
										case 'IN-LINE-AFTER-ADD':
										case 'IN-LINE-BEFORE-ADD':
											// Replace with a blank string
											$reverse_edits[$file][$find]['in-line-edit'][$inline_command] = '';
										break;

										case 'IN-LINE-REPLACE':
											// replace with the inline find
											$reverse_edits[$file][$find]['in-line-edit'][$inline_find][$inline_action] = $inline_command;
										break;

										default:
											// For the moment, we do nothing.
										break;
									}
								}
							}
						break;

						default:
						break;
					}
				}
			}
		}

		return $reverse_edits;
	}
	
	/**
	 * Parse sql
	 *
	 * @param array $sql_query
	 */
	function parse_sql(&$sql_query)
	{
		global $dbms, $table_prefix;
		
		if (!function_exists('get_available_dbms'))
		{
			global $phpbb_root_path, $phpEx;
			
			include($phpbb_root_path . 'includes/functions_install.' . $phpEx);
		}

		static $available_dbms; 
		
		if (!isset($available_dbms))
		{
			$available_dbms = get_available_dbms($dbms);
		}

		$remove_remarks = $available_dbms[$dbms]['COMMENTS'];
		$delimiter = $available_dbms[$dbms]['DELIM'];

		$sql_query = implode(' ', $sql_query);
		$sql_query = preg_replace('#phpbb_#i', $table_prefix, $sql_query);
		$remove_remarks($sql_query);
		$sql_query = split_sql_file($sql_query, $delimiter);

		//return $sql_query;
	}

	function find_children($mod_path, $actions, $action)
	{
		global $db, $template, $parser;

		$children = $this->find_mods(dirname($mod_path));

		if (sizeof($children['contrib']))
		{
			// there are things like upgrades...we probably don't care. 
			// this assumption may need to be revisited, therefore this comment
		}

		if (sizeof($children['languages']))
		{
			// additional languages are available...find out which ones we may want to apply
			// we don't care about english because it is included in the main MODX file
			$sql = 'SELECT lang_id, lang_iso FROM ' . LANG_TABLE . "
				WHERE lang_iso <> 'en'";
			$result = $db->sql_query($sql);

			$installed_languages = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$installed_languages[$row['lang_id']] = $row['lang_iso'];
			}

			// We _must_ have language xml files that are named "nl.xml" or "en-US.xml" for this to work
			// it appears that the MODX packaging standards call for this anyway
			$available_languages = array_map('core_basename', $children['languages']);
			$process_languages = array_intersect($available_languages, $installed_languages);

			// $unknown_languages are installed on the board, but not provied for by the MOD
			$unknown_languages = array_diff($installed_languages, $available_languages);

			// these are langauges which are installed, but not provided for by the MOD
			if (sizeof($unknown_languages) && $action == 'pre_install')
			{
				$template->assign_var('UNKNOWN_LANGUAGES', true);

				// get full names from the DB
				$sql = 'SELECT lang_english, lang_local FROM ' . LANG_TABLE . '
					WHERE ' . $db->sql_in_set('lang_iso', $unknown_languages);
				$result = $db->sql_query($sql);

				// alert the user.
				while ($row = $db->sql_fetchrow($result))
				{
					$template->assign_block_vars('unknown_lang', array(
						'ENGLISH_NAME'	=> $row['lang_english'],
						'LOCAL_NAME'	=> $row['lang_local'],
					));
				}
			}

			if (sizeof($process_languages) && (!defined('DEBUG') || !isset($_GET['full_details']) || $action == 'install'))
			{
				// add the actions to our $actions array...somehow
				foreach ($process_languages as $key => $void)
				{
					$actions = array_merge_recursive($actions, $this->mod_actions($children['languages'][$key]));
				}
			}
		}

		if (sizeof($children['templates']))
		{
			// additional styles are available for this MOD

			// NOTE: Need to somehow exclude prosilver
			$sql = 'SELECT template_id, template_name FROM ' . STYLES_TEMPLATE_TABLE;
			$result = $db->sql_query($sql);

			$installed_templates = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$installed_templates[$row['template_id']] = $row['template_name']; 
			}

			// We _must_ have language xml files that are named like "subSilver2.xml" for this to work
			$available_templates = array_map('core_basename', $children['templates']);
			$process_templates = array_intersect($available_templates, $installed_templates);

			// $unknown_templates are installed on the board, but not provied for by the MOD
			$unknown_templates = array_diff($installed_templates, $available_templates);

			if (sizeof($unknown_templates) && $action == 'pre_install')
			{
				// prompt the user
				// consider Nuttzy-like behavior (attempt to apply another xml file to a template)
				$template->assign_var('S_UNKNOWN_TEMPLATES', true);

				foreach ($unknown_templates as $unknown_template)
				{
					$template->assign_block_vars('unknown_templates', array(
						'TEMPLATE_NAME'		=> $unknown_template,
					));
				}
			}

			if (sizeof($process_templates) && (!defined('DEBUG') || !isset($_GET['full_details']) || $action == 'install'))
			{
				// add the template actions to our $actions array...
				foreach ($process_templates as $key => $void)
				{
					$actions = array_merge_recursive($actions, $this->mod_actions($children['templates'][$key]));
				}
			}
		}

		return $actions;
	}
}

function core_basename($path)
{
	$path = basename($path);
	return substr($path, 0, strrpos($path, '.'));
}

?>