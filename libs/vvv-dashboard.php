<?php

/**
 *
 * PHP version 5
 *
 * Created: 12/2/15, 10:33 AM
 *
 * LICENSE:
 *
 * @author         Jeff Behnke <code@validwebs.com>
 * @copyright  (c) 2015 ValidWebs.com
 *
 * dashboard
 * vvv-dashboard.php
 */

/**
 * Class vvv_dashboard
 *
 * @author         Jeff Behnke <code@validwebs.com>
 * @copyright  (c) 2009-15 ValidWebs.com
 *
 */
class vvv_dashboard {

	private $_cache;

	private $_pages = array();

	public function __construct() {
		$this->_cache = new vvv_dash_cache();

		$this->_set_pages();
	}

	/**
	 * Setup the dynamic pages from URI query
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/16/15, 5:44 PM
	 *
	 */
	private function _set_pages() {
		$this->_pages = array(
			'dashboard',
			'plugins',
			'themes',
			'backups',
			'about',
			'commands',
		);
	}

	/**
	 * Check the request and return if available.
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/16/15, 5:45 PM
	 *
	 * @return bool|string
	 */
	public function get_page() {

		if ( isset( $_REQUEST['page'] ) && ! empty( $_REQUEST['page'] ) ) {

			if ( in_array( $_REQUEST['page'], $this->_pages ) ) {
				return $_REQUEST['page'];
			} else {
				return 'dashboard';
			}

		} else {
			return false;
		}

	}

	/**
	 * Returns the host data
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:46 AM
	 *
	 * @param $path
	 *
	 * @return array|bool|string
	 */
	public function get_hosts( $path ) {
		if ( ( $hosts = $this->_cache->get( 'host-sites', VVV_DASH_HOSTS_TTL ) ) == false ) {

			$hosts  = $this->get_hosts_data( $path );
			$status = $this->_cache->set( 'host-sites', serialize( $hosts ) );
		}

		return $hosts;
	}

	/**
	 * Returns the host path
	 *
	 * @ToDO           needs to be updated with the path methods
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:46 AM
	 *
	 * @param $host
	 *
	 * @return string
	 */
	public function get_host_path( $host ) {

		$host_info = vvv_dashboard::get_host_info( $host );
		$is_env    = ( isset( $host_info['is_env'] ) ) ? $host_info['is_env'] : false;

		// WP Starter
		if ( $is_env ) {
			$host_path = '/public/wp';
		} else {
			// Normal WP
			$host_path = $host_info['path'];
		}

		return $host_path;
	}

	/**
	 * Create an array of the hosts from all of the VVV host files
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2014 ValidWebs.com
	 *
	 * Created:    5/23/14, 12:57 PM
	 *
	 * @param $path
	 *
	 * @return array
	 */
	public function get_hosts_data( $path ) {

		$array = array();
		$debug = array();
		$hosts = array();
		$wp    = array();
		$depth = VVV_DASH_SCAN_DEPTH;
		$site  = new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new RecursiveIteratorIterator( $site );
		if ( ! is_object( $files ) ) {
			return null;
		}
		$files->setMaxDepth( $depth );

		// Loop through the file list and find what we want
		foreach ( $files as $name => $object ) {

			if ( strstr( $name, 'vvv-hosts' ) && ! is_dir( 'vvv-hosts' ) ) {

				$lines = file( $name );
				$name  = str_replace( array( '../../', '/vvv-hosts' ), array(), $name );

				// read through the lines in our host files
				foreach ( $lines as $num => $line ) {

					// skip comment lines
					if ( ! strstr( $line, '#' ) && 'vvv.dev' != trim( $line ) ) {
						if ( 'vvv-hosts' == $name ) {
							switch ( trim( $line ) ) {
								case 'local.wordpress.dev' :
									$hosts['wordpress-default'] = array( 'host' => trim( $line ) );
									break;
								case 'local.wordpress-trunk.dev' :
									$hosts['wordpress-trunk'] = array( 'host' => trim( $line ) );
									break;
								case 'src.wordpress-develop.dev' :
									$hosts['wordpress-develop/src'] = array( 'host' => trim( $line ) );
									break;
								case 'build.wordpress-develop.dev' :
									$hosts['wordpress-develop/build'] = array( 'host' => trim( $line ) );
									break;
							}
						}
						if ( 'vvv-hosts' != $name ) {
							$hosts[ $name ] = array( 'host' => trim( $line ) );
						}
					}
				}
			}

			if ( strstr( $name, 'wp-config.php' ) ) {

				$config_lines = file( $name );
				$name         = str_replace( array( '../../', '/wp-config.php', '/htdocs' ), array(), $name );

				// read through the lines in our host files
				foreach ( $config_lines as $num => $line ) {

					// skip comment lines
					if ( strstr( $line, "define('WP_DEBUG', true);" )
					     || strstr( $line, 'define("WP_DEBUG", true);' )
					     || strstr( $line, 'define( "WP_DEBUG", true );' )
					     || strstr( $line, "define( 'WP_DEBUG', true );" )
					) {
						$debug[ $name ] = array(
							'path'  => $name,
							'debug' => 'true',
						);
					}
				}

				$wp[ $name ] = 'true';
			}
		}

		foreach ( $hosts as $key => $val ) {

			if ( array_key_exists( $key, $debug ) ) {
				if ( array_key_exists( $key, $wp ) ) {
					$array[ $key ] = $val + array( 'debug' => 'true', 'is_wp' => 'true' );
				} else {
					$array[ $key ] = $val + array( 'debug' => 'true', 'is_wp' => 'false' );
				}
			} else {
				if ( array_key_exists( $key, $wp ) ) {
					$array[ $key ] = $val + array( 'debug' => 'false', 'is_wp' => 'true' );
				} else {
					$array[ $key ] = $val + array( 'debug' => 'false', 'is_wp' => 'false' );
				}
			}

			$host_info = vvv_dashboard::get_host_info( $val["host"] );
			$is_env    = ( isset( $host_info['is_env'] ) ) ? $host_info['is_env'] : false;

			// wp core version --path=<path>
			if ( $is_env ) {
				$host_path = $host_info['path'] . '/wp';
			} else {
				// Normal WP
				$host_path = $host_info['path'];
			}

			$wp_version               = shell_exec( 'wp core version --path=' . $host_path );
			$array[ $key ]['version'] = $wp_version;

			// Causes load issues do to each API call SO this can not be in a loop
			// @ToDo find a better way
			//$update_check             = shell_exec( 'wp core check-update --path=' . $host_path );
			//$array[ $key ]['update']  = $update_check;
		}

		$array['site_count'] = count( $hosts );

		return $array;
	}

	/**
	 * Gets an array containing the needed host info
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:45 AM
	 *
	 * @param $host
	 *
	 * @return array
	 */
	public static function get_host_info( $host ) {

		$host_info = array();
		$hosts     = new vvv_dash_hosts();
		$host_info = $hosts->get_paths( $host );

		list( $host_info['env_path'], $host_info['is_env'] ) = $hosts->check_env_file( $host_info );

		return $host_info;
	}


	/**
	 * Get the hosts list of themes and save to cache
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    11/19/15, 2:56 PM
	 *
	 * @param        $host
	 * @param string $path
	 *
	 * @return bool|string
	 */
	public function get_themes_data( $host, $path = '' ) {

		if ( ( $themes = $this->_cache->get( $host . '-themes', VVV_DASH_THEMES_TTL ) ) == false ) {

			$themes = shell_exec( 'wp theme list --path=' . $path . ' --format=csv' );

			// Don't save unless we have data
			if ( $themes ) {
				$status = $this->_cache->set( $host . '-themes', $themes );
			}
		}

		return $themes;
	}

	/**
	 * Returns the theme list for the requested host
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:44 AM
	 *
	 * @param $get
	 *
	 * @return bool|string
	 */
	public function get_themes( $get ) {
		if ( isset( $get['host'] ) && isset( $get['themes'] ) ) {
			$host_path = $this->get_host_path( $get['host'] );
			$host_info = vvv_dashboard::get_host_info( $get['host'] );
			$themes    = $this->get_themes_data( $host_info['host'], $host_path );

			return $themes;
		} else {
			return false;
		}
	}

	/**
	 * Returns the plugin list for the requested host
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:44 AM
	 *
	 * @param $get
	 *
	 * @return bool|string
	 */
	public function get_plugins( $get ) {
		if ( isset( $get['host'] ) && isset( $get['plugins'] ) ) {
			$host_path = $this->get_host_path( $get['host'] );
			$host_info = vvv_dashboard::get_host_info( $get['host'] );
			$plugins   = $this->get_plugins_data( $host_info['host'], $host_path );

			return $plugins;
		} else {
			return false;
		}
	}

	/**
	 * Get the hosts list of plugins and save to cache
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    11/19/15, 2:55 PM
	 *
	 * @param        $host
	 * @param string $path
	 *
	 * @return bool|string
	 */
	public function get_plugins_data( $host, $path = '' ) {

		if ( ( $plugins = $this->_cache->get( $host . '-plugins', VVV_DASH_PLUGINS_TTL ) ) == false ) {

			$plugins = shell_exec( 'wp plugin list --path=' . $path . ' --format=csv --debug ' );

			// Don't save unless we have data
			if ( $plugins ) {
				$status = $this->_cache->set( $host . '-plugins', $plugins );
			}
		}

		return $plugins;
	}


	/**
	 * Creates a plugin with included test files
	 * Also options to create post types and taxonomies.
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/16/15, 3:21 PM
	 *
	 * @param array $post
	 *
	 * @return bool|string
	 */
	public function create_plugin( $post ) {
		$host_info = vvv_dashboard::get_host_info( $post['host'] );
		$path      = $host_info['path'];
		$install   = array();

		// wp scaffold plugin my_test_plugin --activate
		if ( isset( $post['plugin_slug'] ) && ! empty( $post['plugin_slug'] ) ) {
			$status    = shell_exec( 'wp scaffold  plugin ' . $post['plugin_slug'] . ' --activate --path=' . $path . ' --debug' );
			$install[] = str_replace( "\n", '<br />', $status );

		} else {
			// We can do anything with this without plugin info
			return false;
		}

		// wp scaffold post-type my_post_type --theme=another_s --plugin=my_test_plugin
		if ( isset( $post['post_types'] ) && isset( $post['plugin_slug'] ) ) {

			foreach ( $post['post_types'] as $pt_key => $pt_slug ) {
				foreach ( $pt_slug as $post_type ) {
					if ( ! empty( $post_type ) ) {
						$install[] = shell_exec( 'wp scaffold  post-type ' . $post_type . ' --plugin=' . $pt_key . ' --path=' . $path . ' --debug' );
					}
				} // end foreach
				unset( $pt );
			} // end foreach

		}

		// wp scaffold taxonomy venue --post_types=my_post_type --theme=another_s
		if ( isset( $post['taxonomies'] ) ) {

			foreach ( $post['taxonomies'] as $t_key => $tax_slug ) {
				foreach ( $tax_slug as $taxonomy ) {
					if ( ! empty( $taxonomy ) ) {
						$install[] = shell_exec( 'wp scaffold  taxonomy ' . $taxonomy . ' --post_types=' . $t_key . ' --plugin=' . $post['plugin_slug'] . ' --path=' . $path . ' --debug' );
					}
				} // end foreach
				unset( $taxonomy );
			} // end foreach

		}

		if ( sizeof( $install ) ) {

			$install[] = shell_exec( 'wp rewrite flush  --path=' . $path );
			$install[] = '<br />NOTE: You will still need to add includes to your plugin for the post types and taxonomies.';

			return implode( '<br />', $install );
		} else {
			return false;
		}
	}

	/**
	 * Install selected favorite plugins or themes from list
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/16/15, 11:16 AM
	 *
	 * @param $post
	 *
	 * @param $type
	 *
	 * @return bool|string
	 */
	public function install_fav_items( $post, $type ) {
		$host_info = vvv_dashboard::get_host_info( $post['host'] );
		$path      = $host_info['path'];
		$items     = ( isset( $post['checkboxes'] ) ) ? $post['checkboxes'] : false;
		$install   = array();

		if ( $items && is_array( $items ) ) {
			foreach ( $items as $key => $item ) {
				$install[] = shell_exec( 'wp ' . $type . ' install ' . $item . ' --activate --path=' . $path . ' --debug' );
			} // end foreach

			return implode( '<br /><br />', $install );
		} else {
			return false;
		}
	}

	public function get_fav_list( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return '';
		}

		$content    = file_get_contents( $file_path );
		$content    = explode( "\n", $content );
		$content    = array_filter( $content );
		$checkboxes = array();

		foreach ( $content as $item ) {
			$checkboxes[] = '<p><input type="checkbox" name="checkboxes[]" value="' . $item . '"/> &nbsp; <label> ' . $item . '</label></p>';
		} // end foreach
		unset( $item );

		return implode( '', $checkboxes );
	}

	/**
	 * Creates a database dump
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:43 AM
	 *
	 * @param        $host
	 *
	 * @param string $file_name
	 *
	 * @return bool|string
	 */
	public function create_db_backup( $host, $file_name = '' ) {
		$backup_status = false;
		$host_info     = vvv_dashboard::get_host_info( $host );
		$is_env        = ( isset( $host_info['is_env'] ) ) ? $host_info['is_env'] : false;

		// Backups for WP Starter
		if ( $is_env ) {
			$dash_hosts        = new vvv_dash_hosts();
			$env_configs       = $dash_hosts->get_wp_starter_configs( $host_info );
			$configs           = ( isset( $env_configs[ $host_info['host'] ] ) ) ? $env_configs[ $host_info['host'] ] : false;
			$db['db_name']     = $configs['DB_NAME'];
			$db['db_user']     = $configs['DB_USER'];
			$db['db_password'] = $configs['DB_PASSWORD'];
			$backup_status     = vvv_dash_wp_starter_backup( $host_info, $db, $file_name );

		} else {
			// All other backups
			$backup_status = vvv_dash_wp_backup( $host, $file_name );
		}


		return $backup_status;
	}

	/**
	 * Roll back the database with any saved backups in the system
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:43 AM
	 *
	 * @param $host
	 * @param $file
	 *
	 * @return string
	 */
	public function db_roll_back( $host, $file ) {

		$host_info = vvv_dashboard::get_host_info( $host );
		$is_env    = ( isset( $host_info['is_env'] ) ) ? $host_info['is_env'] : false;

		// Backups for WP Starter
		if ( $is_env ) {
			// @ToDo fix this path issue
			$path   = $host_info['path'] . '/wp/';
			$status = shell_exec( 'wp db import --path=' . $path . ' ' . urldecode( $file ) );

		} else {

			$path   = $host_info['path'];
			$status = shell_exec( 'wp db import --path=' . $path . ' ' . urldecode( $file ) );

		}


		return $status;
	}

	/**
	 * Gets the WP debug.log content
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/5/15, 2:44 AM
	 *
	 * @param $get
	 *
	 * @return bool
	 */
	public function get_wp_debug_log( $get ) {
		if ( isset( $get['host'] ) && isset( $get['debug_log'] ) ) {
			$log  = false;
			$host_info = vvv_dashboard::get_host_info( $get['host'] );

			if ( isset( $host_info['path'] ) ) {
				$debug_log['path'] = $host_info['path'] . '/wp-content/debug.log';
			} else {
				$host              = strstr( $get['host'], '.', true );
				$debug_log['path'] = VVV_WEB_ROOT . '/' . $host . '/htdocs/wp-content/debug.log';
			}

			if ( isset( $debug_log['path'] ) && file_exists( $debug_log['path'] ) ) {
				$log = get_php_errors( 21, 140, $debug_log['path'] );
			}

			if ( is_array( $log ) ) {
				$debug_log['lines'] = format_php_errors( $log );
			}

			return $debug_log;
		}

		return false;
	}

	/**
	 * Process $_POST supper globals used in the dashboard
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/8/15, 4:01 PM
	 *
	 * @return bool|string
	 */
	public function process_post() {

		$status = false;

		if ( isset( $_POST ) ) {

			if ( isset( $_POST['install_dev_plugins'] ) && isset( $_POST['host'] ) ) {
				$status = $this->install_dev_plugins( $_POST );

			}


			if ( isset( $_POST['backup'] ) && isset( $_POST['host'] ) ) {
				$status = $this->create_db_backup( $_POST['host'] );
			}

			if ( isset( $_POST['roll_back'] ) && $_POST['roll_back'] == 'Roll Back' ) {
				$status = $this->db_roll_back( $_POST['host'], $_POST['file_path'] );

				if ( $status ) {
					$status = vvv_dash_notice( $status );
				}
			}

			if ( isset( $_POST['purge_hosts'] ) ) {
				$purge_status = $this->_cache->purge( 'host-sites' );
				$status       = vvv_dash_notice( $purge_status . ' files were purged from cache!' );
			}

			if ( isset( $_POST['purge_themes'] ) ) {
				$purge_status = $this->_cache->purge( '-themes' );
				$status       = vvv_dash_notice( $purge_status . ' files were purged from cache!' );
			}

			if ( isset( $_POST['purge_plugins'] ) ) {
				$purge_status = $this->_cache->purge( '-plugins' );
				$status       = vvv_dash_notice( $purge_status . ' files were purged from cache!' );
			}

			if ( isset( $_POST['update_item'] ) && isset( $_POST['host'] ) ) {

				$host_info = vvv_dashboard::get_host_info( $_POST['host'] );

				if ( isset( $host_info['path'] ) ) {

					if ( ! empty( $_POST['type'] ) && 'plugins' == $_POST['type'] ) {
						$update_status = shell_exec( 'wp plugin update ' . $_POST['item'] . ' --path=' . $host_info['path'] );
						$purge_status  = $_POST['item'] . ' was updated!<br />';
						$purge_status .= $this->_cache->purge( '-plugins' );
						$status = vvv_dash_notice( $purge_status . ' files were purged from cache!' );
					}

					if ( ! empty( $_POST['type'] ) && 'themes' == $_POST['type'] ) {
						$status       = shell_exec( 'wp theme update ' . $_POST['item'] . ' --path=' . $host_info['path'] );
						$purge_status = $_POST['item'] . ' was updated!<br />';
						$purge_status .= $this->_cache->purge( '-themes' );
						$status = vvv_dash_notice( $purge_status . ' files were purged from cache!' );
					}

				} else {
					$is_env    = ( isset( $host_info['is_env'] ) ) ? $host_info['is_env'] : false;
					$host      = $host_info['host'];

					// WP Starter
					if ( $is_env ) {
						$host_path = $host_info['path'] . '/wp';
					} else {
						// Normal WP
						$host_path = $host_info['path'];
					}

					if ( ! empty( $_POST['type'] ) && 'plugins' == $_POST['type'] ) {
						$update_status = shell_exec( 'wp plugin update ' . $_POST['item'] . ' --path=' . $host_path );
						$purge_status  = $_POST['item'] . ' was updated!<br />';
						$purge_status .= $this->_cache->purge( '-plugins' );
						$status = vvv_dash_notice( $purge_status . ' files were purged from cache!' );
					}

					if ( ! empty( $_POST['type'] ) && 'themes' == $_POST['type'] ) {
						$status       = shell_exec( 'wp theme update ' . $_POST['item'] . ' --path=' . $host_path );
						$purge_status = $_POST['item'] . ' was updated!<br />';
						$purge_status .= $this->_cache->purge( '-themes' );
						$status = vvv_dash_notice( $purge_status . ' files were purged from cache!' );
					}
				}
			}
		}

		return $status;
	}

	public function __destruct() {
		// TODO: Implement __destruct() method.
	}

	/**
	 *
	 *
	 * @author         Jeff Behnke <code@validwebs.com>
	 * @copyright  (c) 2009-15 ValidWebs.com
	 *
	 * Created:    12/8/15, 4:00 PM
	 *
	 * @param $vvv_dash
	 */
	public function process_get( $vvv_dash ) {

	}


}
// End vvv-dashboard.php