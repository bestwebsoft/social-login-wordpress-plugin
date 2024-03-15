<?php
/**
 * Class for dispalay plugin settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'Scllgn_Settings_Tabs' ) ) {
	/**
	 * Displays the content on the plugin settings page
	 */
	class Scllgn_Settings_Tabs extends Bws_Settings_Tabs {
		private $forms;
		private $array_role;

		/**
		 * Constructor.
		 *
		 * @access public
		 *
		 * @see Bws_Settings_Tabs::__construct() for more information on default arguments.
		 *
		 * @param string $plugin_basename Plugin basename.
		 */
		public function __construct( $plugin_basename ) {
			global $scllgn_options, $scllgn_plugin_info;

			$tabs = array(
				'settings'      => array( 'label' => __( 'Settings', 'social-login-bws' ) ),
				'misc'          => array( 'label' => __( 'Misc', 'social-login-bws' ) ),
				'custom_code'   => array( 'label' => __( 'Custom Code', 'social-login-bws' ) ),
			);

			parent::__construct(
				array(
					'plugin_basename'       => $plugin_basename,
					'plugins_info'          => $scllgn_plugin_info,
					'prefix'                => 'scllgn',
					'default_options'       => scllgn_get_default_options(),
					'options'               => $scllgn_options,
					'tabs'                  => $tabs,
					'wp_slug'               => 'social-login-bws',
					'doc_link'              => 'https://bestwebsoft.com/documentation/social-login/social-login-user-guide/',
				)
			);

			$this->forms = array(
				'login_form'        => __( 'Login form', 'social-login-bws' ),
				'register_form'     => __( 'Registration form', 'social-login-bws' ),
				'comment_form'      => __( 'Comments form', 'social-login-bws' ),
			);

			$this->array_role = get_editable_roles();

			add_action( get_parent_class( $this ) . '_additional_misc_options_affected', array( $this, 'additional_misc_options_affected' ) );
		}

		/**
		 * Save options
		 */
		public function save_options() {
			global $scllgn_providers;

			$message = '';
			$notice  = '';
			$error   = '';
			if ( isset( $_POST['scllgn_setting_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scllgn_setting_field'] ) ), 'scllgn_setting_action' ) ) {
				foreach ( $scllgn_providers as $provider => $provider_name ) {
					if ( ! empty( $_REQUEST[ 'scllgn_' . $provider . '_is_enabled' ] ) ) {
						$this->options[ 'button_display_' . $provider ] = ( isset( $_REQUEST[ 'scllgn_' . $provider . '_display_button' ] ) && in_array( $_REQUEST[ 'scllgn_' . $provider . '_display_button' ], array( 'long', 'short', 'dark', 'light' ) ) ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'scllgn_' . $provider . '_display_button' ] ) ) : $this->default_options[ 'button_display_' . $provider ];
						$this->options[ $provider . '_button_name' ] = isset( $_REQUEST[ 'scllgn_' . $provider . '_button_text' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ 'scllgn_' . $provider . '_button_text' ] ) ) : '';
						$this->options[ $provider . '_is_enabled' ] = 1;

						if ( ! empty( $_REQUEST[ 'scllgn_' . $provider . '_client_id' ] ) ) {
							$this->options[ $provider . '_client_id' ] = trim( wp_unslash( $_REQUEST[ 'scllgn_' . $provider . '_client_id' ] ) );
						} else {
							$error .= sprintf( __( 'Please fill the Client ID for %s.', 'social-login-bws' ), $provider_name ) . '<br />';
						}

						if ( ! empty( $_REQUEST[ 'scllgn_' . $provider . '_client_secret' ] ) ) {
							$this->options[ $provider . '_client_secret' ] = trim( wp_unslash( $_REQUEST[ 'scllgn_' . $provider . '_client_secret' ] ) );
						} else {
							$error .= sprintf( __( 'Please fill the Client secret for %s.', 'social-login-bws' ), $provider_name ) . '<br />';
						}
					} else {
						$this->options[ $provider . '_is_enabled' ] = 0;
					}
				}

				foreach ( $this->forms as $form_slug => $form ) {
					$this->options[ $form_slug ] = isset( $_REQUEST[ "scllgn_{$form_slug}" ] ) ? 1 : 0;
				}
				$this->options['loginform_buttons_position'] = ( isset( $_REQUEST['scllgn_loginform_buttons_position'] ) && in_array( sanitize_text_field( wp_unslash( $_REQUEST['scllgn_loginform_buttons_position'] ) ), array( 'top', 'middle', 'bottom' ) ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['scllgn_loginform_buttons_position'] ) ) : $this->options['loginform_buttons_position'];
				$this->options['user_role'] = ( isset( $_REQUEST['scllgn_role'] ) && array_key_exists( sanitize_text_field( wp_unslash( $_REQUEST['scllgn_role'] ) ), $this->array_role ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['scllgn_role'] ) ) : $this->options['user_role'];
				$this->options['allow_registration'] = ( isset( $_REQUEST['scllgn_register_option'] ) && in_array( sanitize_text_field( wp_unslash( $_REQUEST['scllgn_register_option'] ) ), array( 'default', 'allow', 'deny' ) ) ) ? sanitize_text_field( wp_unslash( $_POST['scllgn_register_option'] ) ) : 'default';
				$this->options['delete_metadata'] = isset( $_POST['scllgn_delete_metadata'] ) ? 1 : 0;

				update_option( 'scllgn_options', $this->options );

				$message = __( 'Settings saved', 'social-login-bws' );
			}

			return compact( 'message', 'notice', 'error' );
		}

		/**
		 * Display tab "Settings"
		 */
		public function tab_settings() {
			global $scllgn_providers;

			$php_version_is_proper = ( version_compare( phpversion(), '5.3', '>=' ) ) ? true : false; ?>
			<h3><?php esc_html_e( 'Social Login Settings', 'social-login-bws' ); ?></h3>
			<?php $this->help_phrase(); ?>
			<hr>
			<div class="bws_tab_sub_label"><?php esc_html_e( 'General', 'social-login-bws' ); ?></div>
			<table class="form-table scllgn-form-table">
				<tr>
					<th><?php esc_html_e( 'Buttons', 'social-login-bws' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $scllgn_providers as $provider => $provider_name ) { ?>
								<label>
									<input type="checkbox" value="1" name="scllgn_<?php echo esc_attr( $provider ); ?>_is_enabled" <?php checked( $this->options[ $provider . '_is_enabled' ] ); ?> <?php disabled( ! $php_version_is_proper ); ?> class="bws_option_affect" data-affect-show=".scllgn_<?php echo esc_html( $provider ); ?>_client_data" />
									<?php echo esc_html( $provider_name ); ?>
								</label>
								<br />
							<?php } ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Enable Social Login for', 'social-login-bws' ); ?></th>
					<td>
						<p>
							<i><?php esc_html_e( 'WordPress default', 'social-login-bws' ); ?></i>
						</p>
						<br>
						<fieldset>
							<?php foreach ( $this->forms as $form_slug => $form ) { ?>
								<label>
									<input type="checkbox" value="1" name="<?php echo 'scllgn_' . esc_attr( $form_slug ); ?>"<?php checked( $this->options[ $form_slug ], 1 ); ?> class="<?php echo 'scllgn_' . esc_attr( $form_slug ) . '_checkbox'; ?>" />
									<?php echo $form; ?>
								</label>
								<br />
							<?php } ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th>
						<?php esc_html_e( 'Buttons Position', 'social-login-bws' ); ?>
					</th>
					<td>
						<select name="scllgn_loginform_buttons_position" >
							<option value="top" <?php selected( $this->options['loginform_buttons_position'], 'top' ); ?>>
								<?php esc_html_e( 'Top', 'social-login-bws' ); ?>
							</option>
							<option value="middle" <?php selected( $this->options['loginform_buttons_position'], 'middle' ); ?>>
								<?php esc_html_e( 'Before the submit button', 'social-login-bws' ); ?>
							</option>
							<option value="bottom" <?php selected( $this->options['loginform_buttons_position'], 'bottom' ); ?>>
								<?php esc_html_e( 'Bottom', 'social-login-bws' ); ?>
							</option>
						</select>
						<div class="bws_info">
							<?php esc_html_e( 'Choose the buttons position in the form. This option is available only for Login and Registration forms.', 'social-login-bws' ); ?>
						</div>
					</td>
				</tr>
				<tr>
					<th>
						<?php esc_html_e( 'User Registration', 'social-login-bws' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="scllgn_register_option" value="default" <?php checked( 'default' == $this->options['allow_registration'] ); ?> class="bws_option_affect" data-affect-hide="#scllgn_allow_user_registration_notice, #scllgn_deny_user_registration_notice" /> <?php esc_html_e( 'Default', 'social-login-bws' ); ?>
							</label>
							<br/>
							<label>
								<input type="radio" name="scllgn_register_option" value="allow" <?php checked( 'allow' == $this->options['allow_registration'] ); ?> class="bws_option_affect" data-affect-show="#scllgn_allow_user_registration_notice" data-affect-hide="#scllgn_deny_user_registration_notice" /> <?php esc_html_e( 'Allow', 'social-login-bws' ); ?>
							</label>
							<br/>
							<label>
								<input type="radio" name="scllgn_register_option" value="deny" <?php checked( 'deny' == $this->options['allow_registration'] ); ?> class="bws_option_affect" data-affect-show="#scllgn_deny_user_registration_notice" data-affect-hide="#scllgn_allow_user_registration_notice" /> <?php esc_html_e( 'Deny', 'social-login-bws' ); ?>
							</label>
						</fieldset>
						<div class="bws_info" style="display: inline;">
							<?php
							printf(
								esc_html__( 'Allow or deny user registration using social buttons regardless %s.', 'social-login-bws' ),
								'<a href="options-general.php" target="_blank" nohref="nohref">' . esc_html__( 'WordPress General settings', 'social-login-bws' ) . '</a>'
							);
							?>
						</div>
					</td>
				</tr>
				<tr>
					<th>
						<?php esc_html_e( 'New User Default Role', 'social-login-bws' ); ?>
					</th>
					<td>
						<fieldset>
							<?php
							if ( function_exists( 'get_editable_roles' ) ) {
								$default_role = get_option( 'default_role' );
								?>
								<select name="scllgn_role" >
									<?php
									foreach ( $this->array_role as $role => $fields ) {
										printf(
											'<option value="%1$s" %2$s >
											%3$s%4$s
											</option>',
											esc_html( $role ),
											esc_html( selected( $this->options['user_role'], $role ) ),
											esc_html( translate_user_role( $fields['name'] ) ),
											( $role == $default_role ) ? ' (' . esc_html__( 'Default', 'social-login-bws' ) . ')' : ''
										);
									}
									?>
								</select>
							<?php } ?>
						</fieldset>
						<div class="bws_info">
							<?php esc_html_e( 'Choose a default role for newly registered users.', 'social-login-bws' ); ?>
						</div>
					</td>
				</tr>
			</table>
				<?php /*GOOGLE*/ ?>
			<div class="bws_tab_sub_label scllgn_google_client_data">Google</div>
			<table class="form-table scllgn_google_client_data">
				<tr>
					<th><?php esc_html_e( 'Client ID', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_google_client_id" value="<?php echo $this->options['google_client_id']; ?>" size="20" />
						<div class="bws_info">
							<?php esc_html_e( 'You need to create your own credentials in order to use google API.', 'social-login-bws' ); ?> <a href="https://bestwebsoft.com/documentation/social-login/social-login-user-guide/#h.c6m4s1fydbx8" target="_blank" nohref="nohref"><?php esc_html_e( 'Learn More', 'social-login-bws' ); ?></a>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Client Secret', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_google_client_secret" value="<?php echo $this->options['google_client_secret']; ?>" size="20">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Button Style', 'social-login-bws' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="scllgn_google_display_button" value="dark" <?php checked( 'dark' == $this->options['button_display_google'] ); ?> />
								<?php esc_html_e( 'Dark', 'social-login-bws' ); ?>
							</label>
							<br/>
							<label>
								<input type="radio" name="scllgn_google_display_button" value="light" <?php checked( 'light' == $this->options['button_display_google'] ); ?> />
								<?php esc_html_e( 'Light', 'social-login-bws' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Button Label Text', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_google_button_text" value="<?php echo esc_html( $this->options['google_button_name'] ); ?>"/>
					</td>
				</tr>
			</table>
				<?php /*FACEBOOK*/ ?>
			<div class="bws_tab_sub_label scllgn_facebook_client_data">Facebook</div>
			<table class="form-table scllgn-form-table scllgn_facebook_client_data">
				<tr>
					<th><?php esc_html_e( 'App ID', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_facebook_client_id" value="<?php echo esc_html( $this->options['facebook_client_id'] ); ?>" size="20"/>
						<div class="bws_info">
							<?php esc_html_e( 'You need to create your own credentials in order to use Facebook API.', 'social-login-bws' ); ?> <a href="https://bestwebsoft.com/documentation/social-login/social-login-user-guide/#h.t838pgiu644m" target="_blank" nohref="nohref"><?php esc_html_e( 'Learn More', 'social-login-bws' ); ?></a>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'App Secret', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_facebook_client_secret" value="<?php echo $this->options['facebook_client_secret']; ?>" size="20" />
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Button Display', 'social-login-bws' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="scllgn_facebook_display_button" value="long" <?php checked( 'long' == $this->options['button_display_facebook'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_login_button_long scllgn_facebook_button" id="scllgn_facebook_button">
								<span class="dashicons dashicons-facebook"></span>
								<span class="scllgn_button_text"><input type="text" name="scllgn_facebook_button_text" value="<?php echo esc_html( $this->options['facebook_button_name'] ); ?>" /></span>
							</div>
							<span class="dashicons dashicons-welcome-write-blog"></span>

							<br/>
							<label>
								<input type="radio" name="scllgn_facebook_display_button" value="short" <?php checked( 'short' == $this->options['button_display_facebook'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_login_button_short scllgn_facebook_button scllgn_login_button_icon">
								<span class="scllgn_span_icon dashicons dashicons-facebook"></span>
							</div>
						</fieldset>
					</td>
				</tr>
			</table>
				<?php /*TWITTER*/ ?>
			<div class="bws_tab_sub_label scllgn_twitter_client_data">Twitter</div>
			<table class="form-table scllgn-form-table scllgn_twitter_client_data">
				<tr>
					<th><?php esc_html_e( 'Consumer Key (API Key)', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_twitter_client_id" value="<?php echo $this->options['twitter_client_id']; ?>" size="20" />
						<div class="bws_info">
							<?php esc_html_e( 'You need to create your own credentials in order to use twitter API.', 'social-login-bws' ); ?> <a href="https://bestwebsoft.com/documentation/social-login/social-login-user-guide/#h.1yecq5cyrs0u" target="_blank" nohref="nohref"><?php esc_html_e( 'Learn More', 'social-login-bws' ); ?></a>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Consumer Secret (API Secret)', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_twitter_client_secret" value="<?php echo $this->options['twitter_client_secret']; ?>" size="20">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Button Display', 'social-login-bws' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="scllgn_twitter_display_button" value="long" <?php checked( 'long' == $this->options['button_display_twitter'] ); ?> />
							</label>
								<div class="scllgn_login_button scllgn_login_button_long scllgn_twitter_button" id="scllgn_twitter_button">
								<span class="dashicons dashicons-twitter"></span>
								<span class="scllgn_button_text"><input type="text" name="scllgn_twitter_button_text" value="<?php echo esc_html( $this->options['twitter_button_name'] ); ?>" /></span>
							</div>
							<span class="dashicons dashicons-welcome-write-blog"></span>
							</div>
							<br/>
							<label>
								<input type="radio" name="scllgn_twitter_display_button" value="short" <?php checked( 'short' == $this->options['button_display_twitter'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_login_button_short scllgn_twitter_button scllgn_login_button_icon">
								<span class="scllgn_span_icon dashicons dashicons-twitter"></span>
							</div>
						</fieldset>
					</td>
				</tr>
			</table>
				<?php /*LINKEDIN*/ ?>
			<div class="bws_tab_sub_label scllgn_linkedin_client_data">LinkedIn</div>
			<table class="form-table scllgn-form-table scllgn_linkedin_client_data">
				<tr>
					<th><?php esc_html_e( 'Client ID', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_linkedin_client_id" value="<?php echo $this->options['linkedin_client_id']; ?>" size="20" />
						<div class="bws_info">
							<?php esc_html_e( 'You need to create your own credentials in order to use linkedin API.', 'social-login-bws' ); ?> <a href="https://bestwebsoft.com/documentation/social-login/social-login-user-guide/#h.kc920jaw22v2" target="_blank" nohref="nohref"><?php esc_html_e( 'Learn More', 'social-login-bws' ); ?></a>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Client Secret', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_linkedin_client_secret" value="<?php echo $this->options['linkedin_client_secret']; ?>" size="20">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Button Display', 'social-login-bws' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="scllgn_linkedin_display_button" value="long" <?php checked( 'long' == $this->options['button_display_linkedin'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_login_button_long scllgn_linkedin_button" id="scllgn_linkedin_button">
								<span class="dashicons bws-icons scllgn_linkedin_button_admin"></span>
								<span class="scllgn_button_text" ><input type="text" name="scllgn_linkedin_button_text" value="<?php echo esc_html( $this->options['linkedin_button_name'] ); ?>" /></span>
							</div>
							<span class="dashicons dashicons-welcome-write-blog"></span>
							</div>
							<br/>
							<label>
								<input type="radio" name="scllgn_linkedin_display_button" value="short" <?php checked( 'short' == $this->options['button_display_linkedin'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_linkedin_button scllgn_login_button_short scllgn_login_button_icon">
								<span class="scllgn_span_icon dashicons bws-icons scllgn_linkedin_button_admin"></span>
							</div>
						</fieldset>
					</td>
				</tr>
			</table>
				<?php /*Discord*/ ?>
			<div class="bws_tab_sub_label scllgn_discord_client_data">Discord</div>
			<table class="form-table scllgn-form-table scllgn_discord_client_data">
				<tr>
					<th><?php esc_html_e( 'Client ID', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_discord_client_id" value="<?php echo $this->options['discord_client_id']; ?>" size="20" />
						<div class="bws_info">
							<?php esc_html_e( 'You need to create your own credentials in order to use discord API.', 'social-login-bws' ); ?> <a href="https://bestwebsoft.com/documentation/social-login/social-login-user-guide/" target="_blank" nohref="nohref"><?php esc_html_e( 'Learn More', 'social-login-bws' ); ?></a>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Client Secret', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_discord_client_secret" value="<?php echo $this->options['discord_client_secret']; ?>" size="20">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Button Display', 'social-login-bws' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="scllgn_discord_display_button" value="long" <?php checked( 'long' == $this->options['button_display_discord'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_login_button_long scllgn_discord_button" id="scllgn_discord_button">
								<span class="dashicons bws-icons dashicons-discord scllgn_discord_button_admin"></span>
								<span class="scllgn_button_text" ><input type="text" name="scllgn_discord_button_text" value="<?php echo esc_html( $this->options['discord_button_name'] ); ?>" /></span>
							</div>
							<span class="dashicons dashicons-welcome-write-blog"></span>
							</div>
							<br/>
							<label>
								<input type="radio" name="scllgn_discord_display_button" value="short" <?php checked( 'short' == $this->options['button_display_discord'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_discord_button scllgn_login_button_short scllgn_login_button_icon">
								<img class="scllgn_discord_icon" src="<?php echo esc_url( plugins_url( '../images/icons-discord.png', __FILE__ ) ); ?>" alt="Discord Icon">
							</div>
						</fieldset>
					</td>
				</tr>
			</table>
				<?php /*Amazon*/ ?>
			<div class="bws_tab_sub_label scllgn_amazon_client_data">Amazon</div>
			<table class="form-table scllgn-form-table scllgn_amazon_client_data">
				<tr>
					<th><?php esc_html_e( 'Client ID', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_amazon_client_id" value="<?php echo $this->options['amazon_client_id']; ?>" size="20" />
						<div class="bws_info">
							<?php esc_html_e( 'You need to create your own credentials in order to use amazon API.', 'social-login-bws' ); ?> <a href="https://bestwebsoft.com/documentation/social-login/social-login-user-guide/" target="_blank" nohref="nohref"><?php esc_html_e( 'Learn More', 'social-login-bws' ); ?></a>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Client Secret', 'social-login-bws' ); ?></th>
					<td>
						<input type="text" name="scllgn_amazon_client_secret" value="<?php echo $this->options['amazon_client_secret']; ?>" size="20">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Button Display', 'social-login-bws' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="scllgn_amazon_display_button" value="long" <?php checked( 'long' == $this->options['button_display_amazon'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_login_button_long scllgn_amazon_button" id="scllgn_amazon_button">
								<span class="dashicons bws-icons dashicons-amazon scllgn_amazon_button_admin"></span>
								<span class="scllgn_button_text" ><input type="text" name="scllgn_amazon_button_text" value="<?php echo esc_html( $this->options['amazon_button_name'] ); ?>" /></span>
							</div>
							<span class="dashicons dashicons-welcome-write-blog"></span>
							</div>
							<br/>
							<label>
								<input type="radio" name="scllgn_amazon_display_button" value="short" <?php checked( 'short' == $this->options['button_display_amazon'] ); ?> />
							</label>
							<div class="scllgn_login_button scllgn_amazon_button scllgn_login_button_short scllgn_login_button_icon">
								<span class="scllgn_span_icon dashicons dashicons-amazon"></span>
							</div>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php
			wp_nonce_field( 'scllgn_setting_action', 'scllgn_setting_field' );
		}

		/**
		 * Add block for "Delete User Metadata"
		 */
		public function additional_misc_options_affected() {
			?>
			<tr>
				<th>
					<?php esc_html_e( 'Delete User Metadata', 'social-login-bws' ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" value="1" name="scllgn_delete_metadata"<?php checked( $this->options['delete_metadata'], 1 ); ?> class="scllgn_delete_metadata_checkbox" />
						<span class="bws_info">
							<?php esc_html_e( 'Enable to delete all user metadata when deleting the plugin.', 'social-login-bws' ); ?>
						</span>
					</label>
				</td>
			</tr>
		<?php }

	}
} ?>
