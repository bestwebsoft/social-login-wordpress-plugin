<?php
/**
 * Displays the content on the plugin settings page
 */

require_once( dirname( dirname( __FILE__ ) ) . '/bws_menu/class-bws-settings.php' );

if ( ! class_exists( 'Scllgn_Settings_Tabs' ) ) {
	class Scllgn_Settings_Tabs extends Bws_Settings_Tabs {	
		/**
		 * Constructor.
		 *
		 * @access public
		 *
		 * @see Bws_Settings_Tabs::__construct() for more information on default arguments.
		 *
		 * @param string $plugin_basename
		 */
		public function __construct( $plugin_basename ) {
			global $forms, $scllgn_options, $scllgn_plugin_info, $scllgn_providers;

			$tabs = array(
				'settings'		=> array( 'label' => __( 'Settings', 'social-login-bws' ) ),
				'misc'			=> array( 'label' => __( 'Misc', 'social-login-bws' ) ),
				'custom_code'	=> array( 'label' => __( 'Custom Code', 'social-login-bws' ) )
			);

			parent::__construct( array(
				'plugin_basename'		=> $plugin_basename,
				'plugins_info'			=> $scllgn_plugin_info,
				'prefix'				=> 'scllgn',
				'default_options'		=> scllgn_get_default_options(),
				'options'				=> $scllgn_options,
				'is_network_options'	=> is_network_admin(),
				'tabs'					=> $tabs,
				'wp_slug'				=> 'social-login',
				'link_pn'				=> '81'
			) );

			$forms = array(
				'login_form'		=> __( 'WordPress Login form', 'social-login-bws' ),
				'register_form'		=> __( 'WordPress Registration form', 'social-login-bws' ),
				'comment_form'		=> __( 'WordPress Comments form', 'social-login-bws' )
			);
			$scllgn_providers = array(
				'google' 	=> 'Google',
				'facebook' 	=> 'Facebook',
				'twitter'	=> 'Twitter',
				'linkedin'	=> 'LinkedIn',
			);
		}

		public function save_options() {
			global $scllgn_providers, $forms;
			foreach ( $scllgn_providers as $provider => $provider_name ) {
				if ( ! empty( $_REQUEST["scllgn_{$provider}_is_enabled"] ) ) {
					$this->options['button_display_' . $provider] = $_REQUEST['scllgn_' . $provider . '_display_button'];
					$this->options[ $provider . '_button_name'] = $_REQUEST['scllgn_' . $provider . '_button_text'];
					$this->options["{$provider}_is_enabled"] = 1;

					if ( ! empty( $_REQUEST["scllgn_{$provider}_client_id"] ) ) {
						$this->options["{$provider}_client_id"] = trim( stripslashes( esc_html( $_REQUEST["scllgn_{$provider}_client_id"] ) ) );
					} else {
						$error .= sprintf( __( 'Please fill the Client ID for %s.', 'social-login-bws' ), $provider_name );
					}

					if ( ! empty( $_REQUEST["scllgn_{$provider}_client_secret"] ) ) {
						$this->options["{$provider}_client_secret"] = trim( stripslashes( esc_html( $_REQUEST["scllgn_{$provider}_client_secret"] ) ) );
					} else {
						$error .= sprintf( __( 'Please fill the Client secret for %s.', 'social-login-bws' ), $provider_name );
					}
				} else {
					$this->options["{$provider}_is_enabled"] = 0;
				}
			}

			foreach ( $forms as $form_slug => $form ) {
				$this->options[ $form_slug ] = isset( $_REQUEST["scllgn_{$form_slug}"] ) ? 1 : 0;
			}
			$this->options['loginform_buttons_position'] = ( isset( $_REQUEST['scllgn_loginform_buttons_position'] ) && in_array( $_REQUEST['scllgn_loginform_buttons_position'], array( 'top', 'middle', 'bottom' ) ) ) ? $_REQUEST['scllgn_loginform_buttons_position'] : $this->options['loginform_buttons_position'];

			$this->options['user_role'] = isset( $_REQUEST['scllgn_role'] ) ? $_REQUEST['scllgn_role'] : $this->options['user_role'];

			$this->options['allow_registration'] = esc_attr( $_POST['scllgn_register_option'] );
			update_option( 'scllgn_options', $this->options );

			$message = __( 'Settings saved', 'social-login-bws' );

			return compact( 'message', 'notice', 'error' );
		}

		public function tab_settings() { 
			global $scllgn_providers, $forms;
			$php_version_is_proper = ( version_compare( phpversion(), '5.3', '>=' ) ) ? true : false; ?>	
			<table class="form-table scllgn-form-table">
				<tbody>
					<tr scope="row" valign="top" class="scllgn_social_forms">
						<th><?php _e( 'Enable Social Login for', 'social-login-bws' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $forms as $form_slug => $form ) { ?>
								<label>
									<input type="checkbox" value="1" name="<?php echo "scllgn_{$form_slug}"; ?>"<?php checked( $this->options[ $form_slug ], 1 ); ?> class="<?php echo "scllgn_{$form_slug}_checkbox"; ?>" />
									<?php echo $form; ?>
								</label><br />
								<?php } ?>
							</fieldset>
						</td>
					</tr>
					<tr scope="row" valign="top">
						<th>
							<?php _e( 'Buttons Position', 'social-login-bws' ); ?>
						</th>
						<td>
							<select name="scllgn_loginform_buttons_position" >
								<option value="top" <?php selected( $this->options['loginform_buttons_position'], 'top' ); ?>>
									<?php _e( 'Top', 'social-login-bws' ) ?>
								</option>
								<option value="middle" <?php selected( $this->options['loginform_buttons_position'], 'middle' ); ?>>
									<?php _e( 'Before the submit button', 'social-login-bws' ) ?>
								</option>
								<option value="bottom" <?php selected( $this->options['loginform_buttons_position'], 'bottom' ); ?>>
									<?php _e( 'Bottom', 'social-login-bws' ) ?>
								</option>
							</select>
							<div class="bws_info">
								<?php _e( 'Choose the buttons position in the form. This option is available only for Login and Registration forms.', 'social-login-bws' ); ?>
							</div>
						</td>
					</tr>
					<tr scope="row" valign="top">
						<th>
							<?php _e( 'User Registration', 'social-login-bws' ); ?>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="scllgn_register_option" class="scllgn_registration_default" value="default" <?php checked( 'default' == $this->options['allow_registration'] ); ?> /> <?php _e( 'Default', 'social-login-bws' ); ?>
								</label>
								<div class="bws_info" style="display: inline;">
									<?php _e( 'Select to allow or deny user registration using social buttons depending on', 'social-login-bws' ); ?>
									<a href="options-general.php" target="_blank" nohref="nohref">
										<?php _e( 'WordPress General settings.', 'social-login-bws' ); ?>
									</a>
								</div>
								<br/>
								<label>
									<input type="radio" name="scllgn_register_option" class="scllgn_allow_registration" value="allow" <?php checked( 'allow' == $this->options['allow_registration'] ); ?> /> <?php _e( 'Allow', 'social-login-bws' ); ?>
								</label>
								<div class="bws_info" style="display: inline;">
									<?php _e( 'Select to allow user registration using social buttons regardless', 'social-login-bws' ); ?>
									<a href="options-general.php" target="_blank" nohref="nohref">
										<?php _e( 'WordPress General settings.', 'social-login-bws' ); ?>
									</a>
								</div>
								<br/>
								<label>
									<input type="radio" name="scllgn_register_option" class="scllgn_deny_registration" value="deny" <?php checked( 'deny' == $this->options['allow_registration'] ); ?> /> <?php _e( 'Deny', 'social-login-bws' ); ?>
								</label>
								<div class="bws_info" style="display: inline;">
									<?php _e( 'Select to deny user registration using social buttons regardless', 'social-login-bws' ); ?>
									<a href="options-general.php" target="_blank" nohref="nohref">
										<?php _e( 'WordPress General settings.', 'social-login-bws' ); ?>
									</a>
								</div>
							</fieldset>
						</td>
					</tr>
					<tr scope="row" valign="top">
						<th>
							<?php _e( 'New User Default Role', 'social-login-bws' ); ?>
						</th>
						<td>
							<fieldset>
								<?php if ( function_exists( 'get_editable_roles' ) ) {
									$default_role = get_option( 'default_role' ); ?>
									<select name="scllgn_role" >
										<?php $array_role = get_editable_roles();
										foreach ( $array_role as $role => $fields ) {
											printf(
												'<option value="%1$s" %2$s >
												%3$s%4$s
												</option>',
												$role,
												selected( $this->options['user_role'], $role ),
												translate_user_role( $fields['name'] ),
												( $role == $default_role ) ? ' ( ' . __( 'Default', 'social-login-bws' ) . ' )' : ''
											);
										} ?>
									</select>
									<?php } ?>
								</fieldset>
								<div class="bws_info">
									<?php _e( 'Choose the role for newly registered users.', 'social-login-bws' ); ?>
								</div>
							</td>
						</tr>

						<tr scope="row" valign="top" style="border-top: 1px solid #ccc;">
							<th style="padding-top: 20px;"><?php printf( __( '%1$s Sign In Button', 'social-login-bws' ), $scllgn_providers['google'] ); ?></th>
							<td style="padding-top: 20px;">
								<input type="checkbox" value="1" name="scllgn_google_is_enabled"<?php checked( $this->options['google_is_enabled'] ); disabled( ! $php_version_is_proper ); ?> class="scllgn_provider_checkbox" data-scllgn-provider="google" />
								<span class="bws_info">
									<?php printf(
										__( 'Enable to add %1$s Sign In button to the necessary WordPress form.', 'social-login-bws' ),
										$scllgn_providers['google']
										); ?>
									</span>
								</td>
							</tr>
							<tr scope="row" valign="top" class="scllgn_google_client_data">
								<th><?php _e( 'Client ID', 'social-login-bws' ); ?></th>
								<td>
									<input type="text" name="scllgn_google_client_id" value="<?php echo $this->options['google_client_id']; ?>" size="20" />
									<div class="bws_info">
										<?php _e( 'You need to create your own credentials in order to use google API.', 'social-login-bws' ); ?> <a href="https://docs.google.com/document/d/1jS1pGbaIyhR9-6wsvWFueMqd8ZJYKRQAJGkOc8j5lWE/edit#heading=h.ly70c5c1dj07" target="_blank" nohref="nohref"><?php _e( 'Learn More', 'social-login-bws' ); ?></a>
										<br/>
										<?php _e( 'While creating Google API use this redirect url: ', 'social-login-bws' );?><code><? echo wp_login_url(); ?></code>
									</div>
								</td>
							</tr>
							<tr scope="row" valign="top" class="scllgn_google_client_data">
								<th><?php _e( 'Client Secret', 'social-login-bws' ); ?></th>
								<td>
									<input type="text" name="scllgn_google_client_secret" value="<?php echo $this->options['google_client_secret']; ?>" size="20">
								</td>
							</tr>
					<?php /*GOOGLE*/ ?>
					<tr scope="row" valign="top" class="scllgn_google_client_data">
						<th><?php _e( 'Button Display', 'social-login-bws' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="scllgn_google_display_button" class="scllgn_change" value="long" <?php checked( 'long' == $this->options['button_display_google'] ); ?> />
								</label>
								<div class="scllgn_login_button scllgn_login_button_long scllgn_google_button" id="scllgn_google_button">
									<span class="dashicons dashicons-googleplus"></span>
									<span class="scllgn_button_text"><input type="text" name="scllgn_google_button_text" value="<?php echo $this->options['google_button_name']; ?>"/></span>
								</div>
								<span class="dashicons dashicons-welcome-write-blog"></span>
								
								<br/>
								<label>
									<input type="radio" name="scllgn_google_display_button" class="scllgn_change" value="short" <?php checked( 'short' == $this->options['button_display_google'] ); ?> />
								</label>
								<div class="scllgn_login_button scllgn_login_button_short scllgn_google_button scllgn_login_button_icon">
									<span class="scllgn_span_icon dashicons dashicons-googleplus"></span>
								</div>
							</fieldset>
						</td>
					</tr>

					<?php /*FACEBOOK*/ ?>
					<tr scope="row" valign="top" style="border-top: 1px solid #ccc;">
						<th style="padding-top: 40px;"><?php printf( __( '%1$s Sign In Button', 'social-login-bws' ), $scllgn_providers['facebook'] ); ?></th>
						<td style="padding-top: 40px;">
							<input type="checkbox" value="1" name="scllgn_facebook_is_enabled"<?php checked( $this->options['facebook_is_enabled'] ); disabled( ! $php_version_is_proper ); ?> class="scllgn_provider_checkbox" data-scllgn-provider="facebook" />
							<span class="bws_info">
								<?php printf(
									__( 'Enable to add %1$s Sign In button to the necessary WordPress form.', 'social-login-bws' ),
									$scllgn_providers['facebook']
									); ?>
							</span>
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_facebook_client_data">
						<th><?php _e( 'App ID', 'social-login-bws' ); ?></th>
						<td>
							<input type="text" name="scllgn_facebook_client_id" value="<?php echo $this->options['facebook_client_id']; ?>" size="20"/>
							<div class="bws_info">
								<?php _e( 'You need to create your own credentials in order to use Facebook API.', 'social-login-bws' ); ?> <a href="https://docs.google.com/document/d/1jS1pGbaIyhR9-6wsvWFueMqd8ZJYKRQAJGkOc8j5lWE/edit#heading=h.5xcmcz2zjjtl" target="_blank" nohref="nohref"><?php _e( 'Learn More', 'social-login-bws' ); ?></a>
								<br/>
								<?php _e( 'While creating Facebook API use this redirect url: ', 'social-login-bws' );?><code><? echo plugins_url() . '/social-login-bws/facebook_callback.php'; ?></code>
							</div>
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_facebook_client_data">
						<th><?php _e( 'App Secret', 'social-login-bws' ); ?></th>
						<td>
							<input type="text" name="scllgn_facebook_client_secret" value="<?php echo $this->options['facebook_client_secret']; ?>" size="20" />
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_facebook_client_data">
						<th><?php _e( 'Button Display', 'social-login-bws' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="scllgn_facebook_display_button" class="scllgn_change" value="long" <?php checked( 'long' == $this->options['button_display_facebook'] ); ?> />
								</label>
								<div class="scllgn_login_button scllgn_login_button_long scllgn_facebook_button" id="scllgn_facebook_button">
									<span class="dashicons dashicons-facebook"></span>
									<span class="scllgn_button_text"><input type="text" name="scllgn_facebook_button_text" value="<?php echo $this->options['facebook_button_name']; ?>" /></span>
								</div>
								<span class="dashicons dashicons-welcome-write-blog"></span>

								<br/>
								<label>
									<input type="radio" name="scllgn_facebook_display_button" class="scllgn_change" value="short" <?php checked( 'short' == $this->options['button_display_facebook'] ); ?> />
								</label>
								<div class="scllgn_login_button scllgn_login_button_short scllgn_facebook_button scllgn_login_button_icon">
									<span class="scllgn_span_icon dashicons dashicons-facebook"></span>
								</div>
							</fieldset>
						</td>
					</tr>

					<?php /*TWITTER*/ ?>
					<tr scope="row" valign="top" style="border-top: 1px solid #ccc;">
						<th style="padding-top: 40px;"><?php printf( __( '%1$s Sign In Button', 'social-login-bws' ), $scllgn_providers['twitter'] ); ?></th>
						<td style="padding-top: 40px;">	
							<input type="checkbox" value="1" name="scllgn_twitter_is_enabled"<?php checked( $this->options['twitter_is_enabled'] ); disabled( ! $php_version_is_proper ); ?> class="scllgn_provider_checkbox" data-scllgn-provider="twitter" />
							<span class="bws_info">
								<?php printf(
									__( 'Enable to add %1$s Sign In button to the necessary WordPress form.', 'social-login-bws' ),
									$scllgn_providers['twitter']
									); ?>
							</span>
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_twitter_client_data">
						<th><?php _e( 'Consumer Key (API Key)', 'social-login-bws' ); ?></th>
						<td>
							<input type="text" name="scllgn_twitter_client_id" value="<?php echo $this->options['twitter_client_id']; ?>" size="20" />
							<div class="bws_info">
								<?php _e( 'You need to create your own credentials in order to use twitter API.', 'social-login-bws' ); ?> <a href="https://docs.google.com/document/d/1jS1pGbaIyhR9-6wsvWFueMqd8ZJYKRQAJGkOc8j5lWE/edit#heading=h.fnl0icuiiahq" target="_blank" nohref="nohref"><?php _e( 'Learn More', 'social-login-bws' ); ?></a>
								<br/>
								<?php _e( 'While creating Twitter API use this redirect url: ', 'social-login-bws' );?><code><? echo wp_login_url(); ?></code>
							</div>
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_twitter_client_data">
						<th><?php _e( 'Consumer Secret (API Secret)', 'social-login-bws' ); ?></th>
						<td>
							<input type="text" name="scllgn_twitter_client_secret" value="<?php echo $this->options['twitter_client_secret']; ?>" size="20">
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_twitter_client_data">
						<th><?php _e( 'Button Display', 'social-login-bws' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="scllgn_twitter_display_button" class="scllgn_change" value="long" <?php checked( 'long' == $this->options['button_display_twitter'] ); ?> />
								</label>
									<div class="scllgn_login_button scllgn_login_button_long scllgn_twitter_button" id="scllgn_twitter_button">
									<span class="dashicons dashicons-twitter"></span>
									<span class="scllgn_button_text"><input type="text" name="scllgn_twitter_button_text" value="<?php echo $this->options['twitter_button_name']; ?>" /></span>
								</div>
								<span class="dashicons dashicons-welcome-write-blog"></span>
								</div>
								<br/>
								<label>
									<input type="radio" name="scllgn_twitter_display_button" class="scllgn_change" value="short" <?php checked( 'short' == $this->options['button_display_twitter'] ); ?> />
								</label>
								<div class="scllgn_login_button scllgn_login_button_short scllgn_twitter_button scllgn_login_button_icon">
									<span class="scllgn_span_icon dashicons dashicons-twitter"></span>
								</div>
							</fieldset>
						</td>
					</tr>

					<?php /*LINKEDIN*/ ?>
					<tr scope="row" valign="top" style="border-top: 1px solid #ccc;">
						<th style="padding-top: 40px;"><?php printf( __( '%1$s Sign In Button', 'social-login-bws' ), $scllgn_providers['linkedin'] ); ?></th>
						<td style="padding-top: 40px;">
							<input type="checkbox" value="1" name="scllgn_linkedin_is_enabled"<?php checked( $this->options['linkedin_is_enabled'] ); disabled( ! $php_version_is_proper ); ?> class="scllgn_provider_checkbox" data-scllgn-provider="linkedin" />
							<span class="bws_info">
							<?php printf(
								__( 'Enable to add %1$s Sign In button to the necessary WordPress form.', 'social-login-bws' ),
								$scllgn_providers['linkedin']
								); ?>
							</span>
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_linkedin_client_data">
						<th><?php _e( 'Client ID', 'social-login-bws' ); ?></th>
						<td>
							<input type="text" name="scllgn_linkedin_client_id" value="<?php echo $this->options['linkedin_client_id']; ?>" size="20" />
							<div class="bws_info">
								<?php _e( 'You need to create your own credentials in order to use linkedin API.', 'social-login-bws' ); ?> <a href="https://docs.google.com/document/d/1jS1pGbaIyhR9-6wsvWFueMqd8ZJYKRQAJGkOc8j5lWE/edit#heading=h.vgel2zwdelzu" target="_blank" nohref="nohref"><?php _e( 'Learn More', 'social-login-bws' ); ?></a>
								<br/>
								<?php _e( 'While creating LinkedIn API use this redirect url: ', 'social-login-bws' );?><code><? echo plugins_url() . '/social-login-bws/linkedin_callback.php'; ?></code>
							</div>
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_linkedin_client_data">
						<th><?php _e( 'Client Secret', 'social-login-bws' ); ?></th>
						<td>
							<input type="text" name="scllgn_linkedin_client_secret" value="<?php echo $this->options['linkedin_client_secret']; ?>" size="20">
						</td>
					</tr>
					<tr scope="row" valign="top" class="scllgn_linkedin_client_data">
						<th><?php _e( 'Button Display', 'social-login-bws' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="scllgn_linkedin_display_button" class="scllgn_change" value="long" <?php checked( 'long' == $this->options['button_display_linkedin'] ); ?> />
								</label>
								<div class="scllgn_login_button scllgn_login_button_long scllgn_linkedin_button" id="scllgn_linkedin_button">
									<span class="dashicons bws-icons scllgn_linkedin_button_admin"></span>
									<span class="scllgn_button_text" ><input type="text" name="scllgn_linkedin_button_text" value="<?php echo $this->options['linkedin_button_name']; ?>" /></span>
								</div>
								<span class="dashicons dashicons-welcome-write-blog"></span>
								</div>
								<br/>
								<label>
									<input type="radio" name="scllgn_linkedin_display_button" class="scllgn_change" value="short" <?php checked( 'short' == $this->options['button_display_linkedin'] ); ?> />
								</label>
								<div class="scllgn_login_button scllgn_linkedin_button scllgn_login_button_short scllgn_login_button_icon">
									<span class="scllgn_span_icon dashicons bws-icons scllgn_linkedin_button_admin"></span>
								</div>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
	<?php }
	}
}
?>