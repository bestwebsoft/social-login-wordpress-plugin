<?php
/*
Plugin Name: Social Login by BestWebSoft
Plugin URI: http://bestwebsoft.com/products/wordpress/plugins/social-login/
Description: Add social media login, registration, and commenting to your WordPress website.
Author: BestWebSoft
Text Domain: social-login-bws
Domain Path: /languages
Version: 0.1
Author URI: http://bestwebsoft.com/
License: GPLv2 or later
*/

/*  © Copyright 2017  BestWebSoft  ( http://support.bestwebsoft.com )

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Add BWS menu */
if ( ! function_exists( 'scllgn_add_pages' ) ) {
	function scllgn_add_pages() {
		bws_general_menu();
		$settings = add_submenu_page( 'bws_panel', __( 'Social Login Settings', 'social-login-bws' ), 'Social Login', 'manage_options', 'social-login.php', 'scllgn_settings_page' );
		add_action( 'load-' . $settings, 'scllgn_add_tabs' );
	}
}

if ( ! function_exists( 'scllgn_plugins_loaded' ) ) {
	function scllgn_plugins_loaded() {
		/* Internationalization, first(!) */
		load_plugin_textdomain( 'social-login-bws', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}

/* Initialization */
if ( ! function_exists( 'scllgn_init' ) ) {
	function scllgn_init() {
		global $scllgn_plugin_info, $scllgn_options;

		if ( empty( $scllgn_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$scllgn_plugin_info = get_plugin_data( __FILE__ );
		}

		/* add general functions */
		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );

		/* check compatible with current WP version */
		bws_wp_min_version_check( plugin_basename( __FILE__ ), $scllgn_plugin_info, '3.8' );

		$is_admin = is_admin() && ! defined( 'DOING_AJAX' );

		/* Get/Register and check settings for plugin */
		if ( ! $is_admin || ( isset( $_GET['page'] ) && 'social-login.php' == $_GET['page'] ) ) {
			session_id() or session_start();
			scllgn_settings();
		}
	}
}

/* Function for admin_init */
if ( ! function_exists( 'scllgn_admin_init' ) ) {
	function scllgn_admin_init() {
		/* Add variable for bws_menu */
		global $bws_plugin_info, $scllgn_plugin_info;

		/* Function for bws menu */
		if ( empty( $bws_plugin_info ) ) {
			$bws_plugin_info = array( 'id' => '640', 'version' => $scllgn_plugin_info['Version'] );
		}
	}
}

if ( ! function_exists( 'scllgn_login_init' ) ) {
	function scllgn_login_init() {
		global $scllgn_options;
		session_id() or session_start();

		if ( ! empty( $_REQUEST['code'] ) ) {
			/* Handling login with google account */
			global $error;
			if ( empty( $scllgn_options ) ) {
				$scllgn_options = get_option( 'scllgn_options' );
			}
			$error = "";
			try {
				$client = scllgn_google_client();
				if ( isset( $_REQUEST['code'] ) ) {
					$client->authenticate( $_REQUEST['code'] );
					$_SESSION['access_token'] = $client->getAccessToken();
					$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
					header( 'Location: ' . filter_var( $redirect, FILTER_SANITIZE_URL ) );
				}

				if ( ! empty( $_SESSION['access_token'] ) ) {
					$client->setAccessToken( $_SESSION['access_token'] );
				} else {
					$authUrl = $client->createAuthUrl();
				}

				if ( $client->getAccessToken() ) {
					$_SESSION['access_token'] = $client->getAccessToken();
					$ticket = $client->verifyIdToken();
					if ( $ticket ) {
						$atts = $ticket->getAttributes();
						/* checking existance of client_id in token */
						if ( $scllgn_options['google_client_id'] == $atts['payload']['aud'] ) {
							$oauth = new Google_Service_Oauth2( $client );
							/* retrieving userinfo from oauth service */
							$userinfo = $oauth->userinfo->get();
							if ( is_object( $userinfo ) &&
								property_exists( $userinfo, 'id' ) &&
								property_exists( $userinfo, 'email' ) &&
								property_exists( $userinfo, 'name' )
							) {
								$userdata = array(
									'user_login'		=> $userinfo->id,
									'user_email'		=> $userinfo->email,
									'nickname'			=> $userinfo->name,
									'first_name'		=> $userinfo->givenName,
									'display_name'		=> $userinfo->name,
									'user_nicename'		=> $userinfo->name,
								);
								$email_is_verified = $userinfo->verifiedEmail;
								/* checking if user already exists */
								$user = get_user_by( 'login', $userinfo->id );
								if ( ! $user && $email_is_verified ) {
									$user = get_user_by( 'email', $userinfo->email );
								}
								$anyone_can_register = get_option( 'users_can_register' );
								if ( ! $user ) {
									/* no such user */
									if ( ! empty( $anyone_can_register ) ) {
										/* registering is allowed */
										if ( $email_is_verified ) {
											/* email is verified, registering new user */
											$default_role = get_option( 'default_role' );
											$userdata['user_pass'] = wp_generate_password( $length = 12, $include_standard_special_chars = false );
											$userdata['role'] = $default_role;

											$user_id = wp_insert_user( $userdata ) ;
											if ( ! is_wp_error( $user_id ) ) {
												/* user successfully created. Logging in */
												scllgn_login_user( $user_id );
											} else {
												/* error while creating user */
												$error = "register_error";
											}
										} else {
											$error = "verify_email";
										}
									} else {
										if ( ! empty( $_SESSION['scllgn_redirect'] ) ) {
											/* saving userinfo if redirected from comments form and redirecting back */
											$_SESSION['scllgn_userdata'] = $userdata;
											$redirect = $_SESSION['scllgn_redirect'];
											unset( $_SESSION['scllgn_redirect'] );
											wp_safe_redirect( $redirect );
											exit();
										} else {
											/* new users registration is disabled */
											$error = "register_disabled";
										}
									}
								} elseif( $user instanceof WP_User ) {
									/* user already exists, logging in */
									scllgn_login_user( $user->ID );
								}
							} else {
								/* some user data that is needed for registration is missing */
								$error = "insufficient_user_data";
							}
						} else {
							/* token data is invalid */
							$error = "invalid_token_data";
						}
					} else {
						/* token is invalid */
						$error = "invalid_token";
					}
				} else {
					$error = "login_error";
				}
			} catch ( exception $e ) {
				$error = urlencode( filter_var( $e->getMessage(), FILTER_SANITIZE_STRING ) );
			}
			if ( ! empty( $error ) ) {
				/* redirecting to login page on error with error message */
				$login_redirect_url = filter_var( wp_login_url() . "?error=$error", FILTER_SANITIZE_URL );
				wp_redirect( $login_redirect_url );
				exit();
			}
		}
	}
}

if ( ! function_exists( 'scllgn_settings' ) ) {
	function scllgn_settings() {
		global $scllgn_options, $scllgn_providers, $scllgn_plugin_info;

		/* Install the option defaults */
		if ( ! get_option( 'scllgn_options' ) ) {
			$options_default = scllgn_get_default_options();
			add_option( 'scllgn_options', $options_default );
		}

		/* Get options from the database */
		$scllgn_options = get_option( 'scllgn_options' );

		if ( ! isset( $scllgn_options['plugin_option_version'] ) || $scllgn_options['plugin_option_version'] != $scllgn_plugin_info['Version'] ) {
			$options_default = scllgn_get_default_options();
			$scllgn_options = array_merge( $options_default, $scllgn_options );
			$scllgn_options['plugin_option_version'] = $scllgn_plugin_info['Version'];
			$update_option = true;
			scllgn_plugin_activate();
		}

		if ( isset( $update_option ) )
			update_option( 'scllgn_options', $scllgn_options );

		$scllgn_providers = array(
			'google' => 'Google'
		);
	}
}

if ( ! function_exists( 'scllgn_get_default_options' ) ) {
	function scllgn_get_default_options( $is_network_admin = false ) {
		global $scllgn_plugin_info;

		$default_options = array(
			'plugin_option_version'			=> $scllgn_plugin_info['Version'],
			'google_is_enabled'				=> 0,
			'google_client_id'				=> '',
			'google_client_secret'			=> '',
			'google_login_form'				=> 1,
			'google_register_form'			=> 1,
			'google_comment_form'			=> 1,
			'loginform_buttons_position'	=> 'middle', /* top | middle | bottom */
			'display_settings_notice'		=> 1,
			'first_install'					=> strtotime( "now" ),
			'suggest_feature_banner'		=> 1,
		);

		return $default_options;
	}
}

if ( ! function_exists( 'scllgn_plugin_activate' ) ) {
	function scllgn_plugin_activate() {
		if ( is_multisite() ) {
			switch_to_blog( 1 );
			register_uninstall_hook( __FILE__, 'scllgn_delete_options' );
			restore_current_blog();
		} else {
			register_uninstall_hook( __FILE__, 'scllgn_delete_options' );
		}
	}
}

/* Function formed content of the plugin's admin page. */
if ( ! function_exists( 'scllgn_settings_page' ) ) {
	function scllgn_settings_page() {
		global $scllgn_options, $scllgn_providers, $scllgn_plugin_info;
		$message = $error = "";
		$plugin_basename = plugin_basename( __FILE__ );

		$forms = array(
			'login_form'		=> __( 'WordPress Login form', 'social-login-bws' ),
			'register_form'		=> __( 'WordPress Registration form', 'social-login-bws' ),
			'comment_form'		=> __( 'WordPress Comments form', 'social-login-bws' )
		);

		$php_version_is_proper = ( version_compare( phpversion(), "5.3", ">=" ) ) ? true : false;

		if ( $php_version_is_proper && isset( $_REQUEST['scllgn_form_submit'] ) && check_admin_referer( $plugin_basename, 'scllgn_nonce_name' ) ) {
			/* Takes all the changed settings on the plugin's admin page and saves them in array 'scllgn_options'. */
			foreach ( $scllgn_providers as $provider => $provider_name ) {
				if ( ! empty( $_REQUEST["scllgn_{$provider}_is_enabled"] ) ) {
					$scllgn_options["{$provider}_is_enabled"] = 1;

					if ( ! empty( $_REQUEST["scllgn_{$provider}_client_id"] ) ) {
						$scllgn_options["{$provider}_client_id"] =  esc_html( $_REQUEST["scllgn_{$provider}_client_id"] );
					} else {
						$error .= sprintf( __( 'Please fill in Client ID for %s.', 'social-login-bws' ), $provider_name );
					}

					if ( ! empty( $_REQUEST["scllgn_{$provider}_client_secret"] ) ) {
						$scllgn_options["{$provider}_client_secret"] =  esc_html( $_REQUEST["scllgn_{$provider}_client_secret"] );
					} else {
						$error .= sprintf( __( 'Please fill in Client secret for %s.', 'social-login-bws' ), $provider_name );
					}

					foreach ( $forms as $form_slug => $form ) {
						$scllgn_options[ "{$provider}_{$form_slug}" ] = isset( $_REQUEST["scllgn_{$provider}_{$form_slug}"] ) ? 1 : 0;
					}

				} else {
					$scllgn_options["{$provider}_is_enabled"] = 0;
				}
			}
			$scllgn_options['loginform_buttons_position'] = ( isset( $_REQUEST['scllgn_loginform_buttons_position'] ) && in_array( $_REQUEST['scllgn_loginform_buttons_position'], array( 'top', 'middle', 'bottom' ) ) ) ? $_REQUEST['scllgn_loginform_buttons_position'] : $scllgn_options['loginform_buttons_position'];

			$message = __( 'Settings saved', 'social-login-bws' );
			update_option( 'scllgn_options', $scllgn_options );
		}

		/* add restore function */
		if ( isset( $_REQUEST['bws_restore_confirm'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
			$scllgn_options = scllgn_get_default_options();
			update_option( 'scllgn_options', $scllgn_options );
			$message = __( 'All plugin settings were restored.', 'social-login-bws' );
		}
		$display_position_settings = false;
		foreach ( $scllgn_providers as $provider => $provider_name ) {
			if (
				! empty( $scllgn_options["{$provider}_is_enabled"] ) &&
				( ! empty( $scllgn_options["{$provider}_login_form"] ) || ! empty( $scllgn_options["{$provider}_register_form"] ) )
			) {
				$display_position_settings = true;
			}
		} ?>
		<div class="wrap">
			<h1><?php _e( 'Social Login Settings', 'social-login-bws' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab<?php if ( ! isset( $_GET['action'] ) || ( isset( $_GET['action'] ) && ! in_array( $_GET['action'], array( 'custom_code' ) ) ) ) echo ' nav-tab-active'; ?>" href="admin.php?page=social-login.php"><?php _e( 'Settings', 'social-login-bws' ); ?></a>
				<a class="nav-tab<?php if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=social-login.php&amp;action=custom_code"><?php _e( 'Custom code', 'social-login-bws' ); ?></a>
			</h2>
			<div class="updated fade below-h2" <?php if ( empty( $message ) || "" != $error ) echo "style=\"display:none\""; ?>><p><strong><?php echo $message; ?></strong></p></div>
			<div class="error below-h2" <?php if ( $php_version_is_proper && "" == $error ) echo "style=\"display:none\""; ?>>
				<?php if ( "" != $error ) {
					echo "<p><strong>$error</strong></p>";
				}
				if ( ! $php_version_is_proper ) { ?>
					<p><strong>
						<?php printf(
							__( '%1$s requires at least PHP version %2$s. Please contact you hosting provider in order to upgrade PHP version.', 'social-login-bws' ),
							$scllgn_plugin_info['Name'],
							'5.3.0'
						); ?>
					</strong></p>
				<?php } ?>
			</div>
			<?php bws_show_settings_notice();
			if ( ! isset( $_GET['action'] ) || ( isset( $_GET['action'] ) && ! in_array( $_GET['action'], array( 'custom_code' ) ) ) ) {
				if ( isset( $_REQUEST['bws_restore_default'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
					bws_form_restore_default_confirm( $plugin_basename );
				} else { ?>
					<form method="post" action="" enctype="multipart/form-data" class="bws_form scllgn-settings-form">
						<table class="form-table scllgn-form-table scllgn-provider-table">
							<tbody>
								<tr scope="row" valign="top">
									<th><?php printf( __( '%1$s Sign In Button', 'social-login-bws' ), $scllgn_providers['google'] ); ?></th>
									<td>
										<input type="checkbox" value="1" name="scllgn_google_is_enabled"<?php checked( $scllgn_options['google_is_enabled'] && $php_version_is_proper ); disabled( ! $php_version_is_proper ); ?> class="scllgn_provider_checkbox" data-provider="google" />
										<span class="bws_info">
											<?php printf(
												__( 'Enable to add %1$s Sign In button to the necessary WordPress form.', 'social-login-bws' ),
												$scllgn_providers['google']
											); ?>
										</span>
									</td>
								</tr>
								<?php if ( $php_version_is_proper ) { ?>
									<tr scope="row" valign="top" class="scllgn_google_client_data">
										<th><?php _e( 'Client ID', 'social-login-bws' ); ?></th>
										<td>
											<input type="text" name="scllgn_google_client_id" value="<?php echo $scllgn_options['google_client_id']; ?>" size="65" />
											<div class="bws_info">
												<?php _e( 'You need to create your own credentials in order to use google API.', 'social-login-bws' ); ?> <a href="https://docs.google.com/document/d/1jS1pGbaIyhR9-6wsvWFueMqd8ZJYKRQAJGkOc8j5lWE" target="_blank" nohref="nohref"><?php _e( 'Learn More', 'social-login-bws' ); ?></a>
											</div>
										</td>
									</tr>
									<tr scope="row" valign="top" class="scllgn_google_client_data">
										<th><?php _e( 'Client Secret', 'social-login-bws' ); ?></th>
										<td>
											<input type="text" name="scllgn_google_client_secret" value="<?php echo $scllgn_options['google_client_secret']; ?>" size="25">
										</td>
									</tr>
									<tr scope="row" valign="top" class="scllgn_google_forms">
										<th><?php _e( 'Display Button in', 'social-login-bws' ); ?></th>
										<td>
											<fieldset>
												<?php foreach ( $forms as $form_slug => $form ) { ?>
													<label>
														<input type="checkbox" value="1" name="<?php echo "scllgn_google_{$form_slug}"; ?>"<?php checked( $scllgn_options['google_' . $form_slug ], 1 ); ?> class="<?php echo "scllgn_{$form_slug}_checkbox"; ?>"  data-provider="google" />
														<?php echo $form; ?>
													</label><br />
												<?php } ?>
											</fieldset>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table><!-- .scllgn-provider-table -->
						<?php if ( $php_version_is_proper ) { ?>
							<table class="form-table scllgn-form-table scllgn-position-table">
								<tbody>
									<tr scope="row" valign="top">
										<th>
											<?php _e( 'Buttons Position', 'social-login-bws' ); ?>
										</th>
										<td>
											<select name="scllgn_loginform_buttons_position" <?php disabled( ! $display_position_settings ) ?>>
												<option value="top" <?php selected( $scllgn_options['loginform_buttons_position'], 'top' ); ?>>
													<?php _e( 'Top', 'social-login-bws' ) ?>
												</option>
												<option value="middle" <?php selected( $scllgn_options['loginform_buttons_position'], 'middle' ); ?>>
													<?php _e( 'Before the submit button', 'social-login-bws' ) ?>
												</option>
												<option value="bottom" <?php selected( $scllgn_options['loginform_buttons_position'], 'bottom' ); ?>>
													<?php _e( 'Bottom', 'social-login-bws' ) ?>
												</option>
											</select>
											<div class="bws_info"><?php _e( 'Choose the buttons position in the form. This option is available only for Login and Registration forms.', 'social-login-bws' ); ?></div>
										</td>
									</tr>
								</tbody>
							</table>
						<?php } ?>
						<p class="submit">
							<input type="hidden" name="scllgn_form_submit" value="submit" />
							<input id="bws-submit-button" type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'social-login-bws' ); ?>" />
							<?php wp_nonce_field( $plugin_basename, 'scllgn_nonce_name' ); ?>
						</p>
					</form>
					<?php bws_form_restore_default_settings( $plugin_basename ); ?>
				<?php }
			} elseif ( 'custom_code' == $_GET['action'] ) {
				bws_custom_code_tab();
			}
			bws_plugin_reviews_block( $scllgn_plugin_info['Name'], 'social-login-bws' ); ?>
		</div>
	<?php }
}

if ( ! function_exists( 'scllgn_get_current_commenter' ) ) {
	function scllgn_get_current_commenter() {
		$userdata = $_SESSION['scllgn_userdata'];
		$comment_author			= $userdata['display_name'];
		$comment_author_email	= $userdata['user_email'];
		$comment_author_url		= '';
		return compact( 'comment_author', 'comment_author_email', 'comment_author_url' );
	}
}

if ( ! function_exists( 'scllgn_enqueue_scripts' ) ) {
	function scllgn_enqueue_scripts() {
		global $scllgn_options, $scllgn_providers, $scllgn_plugin_info;

		if ( isset( $_GET['page'] ) && 'social-login.php' == $_GET['page'] ) {
			/* Adding script to settings page */
			wp_enqueue_script( 'scllgn_script', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery' ), $scllgn_plugin_info['Version'] );
			if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] )
				bws_plugins_include_codemirror();
		}
		if ( ! is_admin() && comments_open() && ! is_user_logged_in() ) {
			/* Adding style to pages with comments */
			foreach ( $scllgn_providers as $provider => $provider_name ) {
				if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) && ! empty( $scllgn_options["{$provider}_comment_form"] ) ) {
					$enqueue_style = true;
				}
			}
			if ( ! empty( $_SESSION['scllgn_userdata'] ) ) {
				/* userdata is set, filling data into the comment form */
				add_filter( 'wp_get_current_commenter', 'scllgn_get_current_commenter' );
			}
			if ( ! empty( $enqueue_style ) )
				wp_enqueue_style( 'scllgn_style', plugins_url( 'css/style.css', __FILE__ ), array( 'dashicons' ), $scllgn_plugin_info['Version'] );
		}
	}
}

/* Login form scripts */
if ( ! function_exists( 'scllgn_login_enqueue_scripts' ) ) {
	function scllgn_login_enqueue_scripts() {
		global $scllgn_plugin_info, $scllgn_providers, $scllgn_options;

		foreach ( $scllgn_providers as $provider => $provider_name ) {
			if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) ) {
				$enqueue_script = true;
				if ( ! isset( $_REQUEST['action'] ) && ! empty( $scllgn_options["{$provider}_login_form"] ) ) {
					/* Adding styles to the login page */
					$enqueue_style = true;
				} elseif ( ! empty( $_REQUEST['action'] ) && 'register' == $_REQUEST['action'] && ! empty( $scllgn_options["{$provider}_register_form"] ) ) {
					/* Adding styles to the register page */
					$enqueue_style = true;
				}
			}
		}

		if ( ! empty( $enqueue_style ) )
			wp_enqueue_style( 'scllgn_login_style', plugins_url( 'css/style-login.css', __FILE__ ), array( 'dashicons' ), $scllgn_plugin_info['Version'] );

		if ( ! empty( $enqueue_script ) )
			wp_enqueue_script( 'scllgn_login_script', plugins_url( 'js/script-login.js', __FILE__ ), array( 'jquery' ), $scllgn_plugin_info['Version'] );
	}
}

/* creating new Google Client */
if ( ! function_exists( 'scllgn_google_client' ) ) {
	function scllgn_google_client() {
		global $scllgn_options, $scllgn_plugin_info;
		if ( ! empty( $scllgn_options['google_client_id'] ) && ! empty( $scllgn_options['google_client_secret'] ) ) {
			require_once( dirname( __FILE__ ) . '/google_api/autoload.php' );

			$redirect_uri = wp_login_url();
			if ( is_ssl() && strtolower( substr( $redirect_uri, 0, 7 ) ) == 'http://' ) {
				$redirect_uri = 'https://' . substr( $redirect_uri, 7 );
			}

			$client = new Google_Client();
			$client->setClientId( $scllgn_options['google_client_id'] );
			$client->setClientSecret( $scllgn_options['google_client_secret'] );
			$client->setApplicationName( $scllgn_plugin_info['Name'] );
			$client->setScopes( 'email profile' );
			$client->setRedirectUri( $redirect_uri );
			$client->setPrompt( 'select_account' );

			return $client;
		}
		return false;
	}
}

/* adding error message to the login form */
if ( ! function_exists( 'scllgn_login_error' ) ) {
	function scllgn_login_error( $message ) {
		global $error;
		if ( ! empty( $_REQUEST['error'] ) ) {
			$messages = array(
				'access_denied'				=> __( 'please allow the access to your profile information', 'social-login-bws' ),
				'register_error'			=> __( 'failed to register new user', 'social-login-bws' ),
				'register_disabled'			=> __( 'new users registration is disabled', 'social-login-bws' ),
				'verify_email'				=> __( 'you need to verify your account Email', 'social-login-bws' ),
				'insufficient_user_data'	=> __( 'user data is insufficient for registration', 'social-login-bws' ),
				'invalid_token_data'		=> __( 'provided token data is invalid', 'social-login-bws' ),
				'invalid_token'				=> __( 'provided token is invalid', 'social-login-bws' ),
				'login_error'				=> __( 'login failed', 'social-login-bws' )
			);

			$error = ( ! empty( $error ) ) ? $error . "\n" : "";
			$error .= sprintf(
				'<strong>%1$s</strong>: %2$s',
				__( 'Error', 'social-login-bws' ),
				! empty( $messages[ $_REQUEST['error'] ] ) ? $messages[ $_REQUEST['error'] ] : esc_html( esc_attr( $_REQUEST['error'] ) )
			);
		}
		return $message;
	}
}

/* Prepare and return login button for each provider */
if ( ! function_exists( 'scllgn_get_button' ) ) {
	function scllgn_get_button( $provider = 'google' ) {
		global $scllgn_options, $scllgn_providers, $pagenow;
		if ( 'wp-login.php' != $pagenow ) {
			$_SESSION['scllgn_redirect'] = home_url( add_query_arg( null, null ) );
		} else {
			if ( isset( $_SESSION['scllgn_redirect'] ) )
				unset( $_SESSION['scllgn_redirect'] );
		}
		$button = "";
		if ( 'google' == $provider ) {
			$client = scllgn_google_client();
			$authUrl = urldecode( $client->createAuthUrl() );
			$button .=	'<a href="' . $authUrl . '" class="scllgn_login_button scllgn_button_' . $scllgn_options['loginform_buttons_position'] . '" id="scllgn_google_button" data-position="' . $scllgn_options['loginform_buttons_position'] . '">' .
							'<span class="dashicons dashicons-googleplus"></span>' .
							'<span class="scllgn_button_text">' . apply_filters( 'scllgn_' . $provider . '_button_text', sprintf( __( 'Sign In with %1$s', 'social-login-bws' ), $scllgn_providers[ $provider ] ) ) . '</span>' .
						'</a>';
		}
		return $button;
	}
}

/* Adding Sign In buttons to the Login form page */
if ( ! function_exists( 'scllgn_login_form' ) ) {
	function scllgn_login_form() {
		global $scllgn_options, $scllgn_providers;

		foreach ( $scllgn_providers as $provider => $provider_name ) {
			if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) && ! empty( $scllgn_options["{$provider}_login_form"] ) )
				echo scllgn_get_button( $provider );
		}
	}
}

/* Adding Sign In buttons to the Register form page */
if ( ! function_exists( 'scllgn_register_form' ) ) {
	function scllgn_register_form() {
		global $scllgn_options, $scllgn_providers;

		foreach ( $scllgn_providers as $provider => $provider_name ) {
			if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) && ! empty( $scllgn_options["{$provider}_register_form"] ) )
				echo scllgn_get_button( $provider );
		}
	}
}

/* Adding Sign In buttons to the comment form */
if ( ! function_exists( 'scllgn_comment_form' ) ) {
	function scllgn_comment_form() {
		global $scllgn_options, $scllgn_providers;

		if ( comments_open() && ! is_user_logged_in() ) {
			foreach ( $scllgn_providers as $provider => $provider_name ) {
				if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) && ! empty( $scllgn_options["{$provider}_comment_form"] ) ) {
					echo scllgn_get_button( $provider );
				}
			}
			if ( ! empty( $_SESSION['scllgn_userdata'] ) ) {
				unset( $_SESSION['scllgn_userdata'] );
			}
		}
	}
}

/* Logging user in */
if ( ! function_exists( 'scllgn_login_user' ) ) {
	function scllgn_login_user( $id ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $id );
		wp_set_auth_cookie( $id );
		$redirect = user_admin_url();
		if ( ! empty( $_SESSION['scllgn_redirect'] ) ) {
			/* redirecting to the referrer page */
			$redirect = $_SESSION['scllgn_redirect'];
			unset( $_SESSION['scllgn_redirect'] );
		}
		wp_safe_redirect( $redirect );
		exit();
	}
}

/* on logout from wordpress redirect to logout from google account too */
if ( ! function_exists( 'scllgn_logout_redirect' ) ) {
	function scllgn_logout_redirect( $redirect_url ) {
		if ( ! empty( $_SESSION['access_token'] ) ) {
			/* Logging out from google account */
			unset( $_SESSION['access_token'] );
			$redirect_url = 'https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout?continue=http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		}
		return $redirect_url;
	}
}

/* adding google.com to allowed domains array */
if ( ! function_exists( 'scllgn_allow_redirect' ) ) {
	function scllgn_allow_redirect( $allowed ) {
		$allowed[] = 'www.google.com';
		return $allowed;
	}
}

/* Functions creates other links on plugins page. */
if ( ! function_exists( 'scllgn_action_links' ) ) {
	function scllgn_action_links( $links, $file ) {
		if ( ! is_network_admin() ) {
			/* Static so we don't call plugin_basename on every plugin row. */
			static $this_plugin;
			if ( ! $this_plugin )
				$this_plugin = plugin_basename( __FILE__ );
			if ( $file == $this_plugin ) {
				$settings_link = '<a href="admin.php?page=social-login.php">' . __( 'Settings', 'social-login-bws' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
		}
		return $links;
	}
}

if ( ! function_exists( 'scllgn_links' ) ) {
	function scllgn_links( $links, $file ) {
		$base = plugin_basename( __FILE__ );
		if ( $file == $base ) {
			if ( ! is_network_admin() )
				$links[]	=	'<a href="admin.php?page=social-login.php">' . __( 'Settings', 'social-login-bws' ) . '</a>';
			$links[]	=	'<a href="http://wordpress.org/plugins/social-login-bws/faq/" target="_blank">' . __( 'FAQ', 'social-login-bws' ) . '</a>';
			$links[]	=	'<a href="http://support.bestwebsoft.com">' . __( 'Support', 'social-login-bws' ) . '</a>';
		}
		return $links;
	}
}

/* add help tab  */
if ( ! function_exists( 'scllgn_add_tabs' ) ) {
	function scllgn_add_tabs() {
		$screen = get_current_screen();
		$args = array(
			'id' 			=> 'scllgn',
			'section' 		=> ''
		);
		bws_help_tab( $screen, $args );
	}
}

if ( ! function_exists( 'scllgn_plugin_banner' ) ) {
	function scllgn_plugin_banner() {
		global $hook_suffix, $scllgn_plugin_info;
		if ( 'plugins.php' == $hook_suffix ) {
			if ( ! is_network_admin() )
				bws_plugin_banner_to_settings( $scllgn_plugin_info, 'scllgn_options', 'social-login-bws', 'admin.php?page=social-login.php' );
		}
		if ( isset( $_REQUEST['page'] ) && 'social-login.php' == $_REQUEST['page'] ) {
			bws_plugin_suggest_feature_banner( $scllgn_plugin_info, 'scllgn_options', 'social-login-bws' );
		}
	}
}

/* Function for delete options */
if ( ! function_exists( 'scllgn_delete_options' ) ) {
	function scllgn_delete_options() {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			global $wpdb;
			$old_blog = $wpdb->blogid;
			/* Get all blog ids */
			$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
			foreach ( $blogids as $blog_id ) {
				switch_to_blog( $blog_id );
				delete_option( 'scllgn_options' );
			}
			switch_to_blog( $old_blog );
		} else {
			delete_option( 'scllgn_options' );
		}

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );
		bws_delete_plugin( plugin_basename( __FILE__ ) );
	}
}

register_activation_hook( __FILE__, 'scllgn_plugin_activate' );

/* Calling a function add administrative menu. */
add_action( 'admin_menu', 'scllgn_add_pages' );
add_action( 'plugins_loaded', 'scllgn_plugins_loaded' );
add_action( 'init', 'scllgn_init' );
add_action( 'admin_init', 'scllgn_admin_init' );

if ( version_compare( phpversion(), "5.3", ">=" ) ) {
	add_action( 'login_init', 'scllgn_login_init' );
	add_action( 'login_form', 'scllgn_login_form' );
	add_filter( 'login_message', 'scllgn_login_error' );
	add_action( 'register_form', 'scllgn_register_form' );
	add_action( 'comment_form_top', 'scllgn_comment_form' );

	/* Adding stylesheets */
	add_action( 'wp_enqueue_scripts', 'scllgn_enqueue_scripts' );
	add_action( 'login_enqueue_scripts', 'scllgn_login_enqueue_scripts' );
	add_filter( 'logout_redirect', 'scllgn_logout_redirect' );
	add_filter( 'allowed_redirect_hosts','scllgn_allow_redirect' );
}

/* Adding stylesheets */
add_action( 'admin_enqueue_scripts', 'scllgn_enqueue_scripts' );

/* Additional links on the plugin page */
add_filter( 'plugin_action_links', 'scllgn_action_links', 10, 2 );
add_filter( 'plugin_row_meta', 'scllgn_links', 10, 2 );

/* Adding banner */
add_action( 'admin_notices', 'scllgn_plugin_banner' );