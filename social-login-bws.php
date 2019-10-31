<?php
/*
Plugin Name: Social Login by BestWebSoft
Plugin URI: https://bestwebsoft.com/products/wordpress/plugins/social-login/
Description: Add social media login, registration, and commenting to your WordPress website.
Author: BestWebSoft
Text Domain: social-login-bws
Domain Path: /languages
Version: 1.4
Author URI: https://bestwebsoft.com/
License: GPLv2 or later
*/

/*  © Copyright 2019  BestWebSoft  ( https://support.bestwebsoft.com )

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
if ( ! function_exists( 'add_scllgn_admin_menu' ) ) {
    function add_scllgn_admin_menu() {
        global $submenu, $wp_version, $scllgn_plugin_info;

        $settings = add_menu_page( __( 'Social Login Settings', 'social-login-bws' ), 'Social Login', 'manage_options', 'social-login.php', 'scllgn_settings_page' );

        add_submenu_page( 'social-login.php', __( 'Social Login Settings', 'social-login-bws' ), __( 'Settings', 'social-login-bws' ), 'manage_options', 'social-login.php', 'scllgn_settings_page' );

        add_submenu_page( 'social-login.php', 'BWS Panel', 'BWS Panel', 'manage_options', 'scllgn-bws-panel', 'bws_add_menu_render' );

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
        bws_wp_min_version_check( plugin_basename( __FILE__ ), $scllgn_plugin_info, '3.9' );

        $is_admin = is_admin() && ! defined( 'DOING_AJAX' );
        /* Get/Register and check settings for plugin */
        if (
            ! $is_admin ||
            ( isset( $_GET['page'] ) && 'social-login.php' == $_GET['page'] ) || /* plugin settings page */
            defined( 'IS_PROFILE_PAGE' ) || /* defined on profile.php(set to true) and user-edit.php(set to false) pages */
            ( defined( 'BWS_ENQUEUE_ALL_SCRIPTS' ) && BWS_ENQUEUE_ALL_SCRIPTS )
        ) {
            if ( ! isset( $_SESSION ) ) {
                session_start();
            }
            scllgn_settings();
        }

        /* Additional check for email existance in usermeta of registered users */
        add_filter( 'registration_errors', 'scllgn_registration_errors', 9, 3 );
        if ( is_multisite() ) {
            add_filter( 'wpmu_validate_user_signup', 'scllgn_validate_multisite_user_signup' );
            add_filter( 'wpmu_validate_blog_signup', 'scllgn_validate_multisite_user_signup' );
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

/* Check if the registration is enabled */
if ( ! function_exists( 'scllgn_registration_enabled' ) ) {
    function scllgn_registration_enabled() {
        global $scllgn_options;
        if ( 'default' == $scllgn_options['allow_registration'] ) {
            $anyone_can_register = get_option( 'users_can_register' );
            $is_registration_enabled = ! empty( $anyone_can_register );
        } elseif ( 'allow' == $scllgn_options['allow_registration'] ) {
            $is_registration_enabled = true;
        } elseif ( 'deny' == $scllgn_options['allow_registration'] ) {
            $is_registration_enabled = false;
        } else {
            $is_registration_enabled = false;
        }
        return $is_registration_enabled;
    }
}

/* Function for registration_errors */
if ( ! function_exists( 'scllgn_registration_errors' ) ) {
    function scllgn_registration_errors( $errors, $sanitized_user_login = '', $user_email = '' ) {
        $user = scllgn_get_user( $user_email );
        $error_codes = $errors->get_error_codes();
        /* Add error message only if such message still isn't added */
        if ( false !== $user && ! in_array( 'email_exists', $error_codes ) ) {
            $error_message = sprintf(
                '<strong>%1$s</strong>: %2$s',
                __( 'ERROR', 'social-login-bws' ),
                __( 'This email is already registered, please choose another one.', 'social-login-bws' )
            );
            $errors->add( 'scllgn_email_exists', $error_message );
        }
        return $errors;
    }
}

/* Function for validating_multisite_user_signup */
if ( ! function_exists ( 'scllgn_validate_multisite_user_signup' ) ) {
    function scllgn_validate_multisite_user_signup( $results ) {
        global $current_user;

        /**
         * Prevent email checkig if user is already logged in on the "register site" step for multisite
         */
        if ( ! empty( $current_user->data->ID ) )
            return $results;

        $user = scllgn_get_user( $results['user_email'] );
        $error_codes = $results['errors']->get_error_codes();
        /* Add error message only if such message still isn't added */
        if ( false !== $user && ! in_array( 'email_exists', $error_codes ) ) {
            $error_message = sprintf(
                '<strong>%1$s</strong>: %2$s',
                __( 'ERROR', 'social-login-bws' ),
                __( 'This email is already registered, please choose another one.', 'social-login-bws' )
            );
            $results['errors']->add( 'scllgn_email_exists', $error_message );
        }

        return $results;
    }
}

/**
 * Check if user with specified email is already exist and return user or false.
 * @param		string		$email				email
 * @param		string		$login				login
 * @param		string		$provider_slug		provider slug
 * @return		WP_User || false
 */
if ( ! function_exists( 'scllgn_get_user' ) ) {
    function scllgn_get_user( $email = '', $login = '', $provider_slug = '' ) {
        global $scllgn_providers;

        $user = false;

        if ( '' == $email && '' == $login )
            return $user;

        if ( '' != $email )
            $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            $meta_query_array = array( 'relation' => 'OR' );

            $providers = ( empty( $scllgn_providers ) ) ? array(
                'google' 	=> 'Google',
                'facebook' 	=> 'Facebook',
                'twitter'	=> 'Twitter',
                'linkedin'	=> 'LinkedIn',
            ) : $scllgn_providers;
            foreach ( $providers as $provider => $provider_name ) {
                $meta_value = array();

                if ( '' != $email ) {
                    $meta_value[] = $email;
                }

                if ( $provider == $provider_slug && '' != $login ) {
                    $meta_value[] = $login;
                }

                if ( ! empty( $meta_value ) ) {
                    $meta_query_array[] = array(
                        'key'		=> 'scllgn_' . $provider . '_login',
                        'value'		=> $meta_value,
                        'compare'	=> 'IN'
                    );
                }
            }

            if ( count( $meta_query_array ) > 1 ) {
                $users = get_users( array( 'meta_query' => $meta_query_array, 'number' => '1' ) );
            }

            if ( ! empty( $users ) ) {
                $user = $users[0];
            }
        }
        return apply_filters( 'scllgn_get_user', $user );
    }
}

/* Function to find the user by social email and return main user email */
if ( ! function_exists( 'scllgn_get_user_email' ) ) {
    function scllgn_get_user_email( $email = '' ) {
        $user = scllgn_get_user( $email );

        if ( $user instanceof WP_User ) {
            $email = $user->user_email;
            return $email;
        } else {
            return false;
        }
    }
}

/* Function for settings setup */
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

        if ( isset( $update_option ) ) {
            update_option( 'scllgn_options', $scllgn_options );
        }

        $scllgn_providers = array(
            'google' 	=> 'Google',
            'facebook' 	=> 'Facebook',
            'twitter'	=> 'Twitter',
            'linkedin'	=> 'LinkedIn',
        );
    }
}

/* Function for getting_default_options */
if ( ! function_exists( 'scllgn_get_default_options' ) ) {
    function scllgn_get_default_options( $is_network_admin = false ) {
        global $scllgn_plugin_info;

        $default_options = array(
            'plugin_option_version'					=> $scllgn_plugin_info['Version'],
            'google_is_enabled'						=> 0,
            'google_client_id'						=> '',
            'google_client_secret'					=> '',
            'facebook_is_enabled'					=> 0,
            'facebook_client_id'					=> '',
            'facebook_client_secret'				=> '',
            'twitter_is_enabled'					=> 0,
            'twitter_client_id'						=> '',
            'twitter_client_secret'					=> '',
            'linkedin_is_enabled'					=> 0,
            'linkedin_client_id'					=> '',
            'linkedin_client_secret'				=> '',
            'login_form'							=> 1,
            'register_form'							=> 1,
            'comment_form'							=> 1,
            'loginform_buttons_position'			=> 'middle', /* top | middle | bottom */
            'display_settings_notice'				=> 1,
            'first_install'							=> strtotime( 'now' ),
            'suggest_feature_banner'				=> 1,
            'user_role'								=> get_option( 'default_role' ),
            'button_display_google'					=> 'long',
            'button_display_facebook'				=> 'long',
            'button_display_twitter'				=> 'long',
            'button_display_linkedin'				=> 'long',
            'linkedin_button_name'					=> __( 'Sign in with LinkedIn', 'social-login-bws' ),
            'twitter_button_name'					=> __( 'Sign in with Twitter', 'social-login-bws' ),
            'facebook_button_name'					=> __( 'Sign in with Facebook', 'social-login-bws' ),
            'google_button_name'					=> __( 'Sign in with Google', 'social-login-bws' ),
            'allow_registration'					=> 'default',
            'delete_metadata'                       => 0,
        );

        return $default_options;
    }
}

/* Function for plugin_activate */
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

if ( ! function_exists( 'scllgn_settings_page' ) ) {
    function scllgn_settings_page() {
        global $scllgn_options;
        require_once( dirname( __FILE__ ) . '/includes/class-scllgn-settings.php' );
        $page = new Scllgn_Settings_Tabs( plugin_basename( __FILE__ ) ); ?>
        <div class="wrap">
            <h1><?php _e( 'Social Login Settings', 'social-login-bws' ); ?></h1>
            <noscript>
                <div class="error below-h2">
                    <p><strong><?php _e( 'WARNING', 'social-login-bws' ); ?>
                            :</strong> <?php _e( 'The plugin works correctly only if JavaScript is enabled.', 'social-login-bws' ); ?>
                    </p>
                </div>
            </noscript>
            <?php $page->display_content(); ?>
        </div>
    <?php }
}

if ( ! function_exists( 'scllgn_show_user_registration_setting_notice' ) ) {
    function scllgn_show_user_registration_setting_notice() {
        $ms_class = is_multisite() ? 'notice notice-error' : 'updated';
        $error_class = is_multisite() ? 'WARNING' : 'Notice'; ?>
        <div id="scllgn_allow_user_registration_notice" class="below-h2 <?php echo $ms_class; ?>" style="display:none">
            <p>
                <strong><?php _e( $error_class, 'social-login-bws' ); ?></strong>: <?php _e( "You're going to allow user registration via social buttons regardless WordPress default settings. Make sure that you understand the consequences. Check the following", 'social-login-bws' ); ?>
                <a target="_blank" href="https://support.bestwebsoft.com/hc/en-us/articles/360000371546"><?php _e( 'article', 'social-login-bws' ); ?></a>
            </p>
        </div>
        <div id="scllgn_deny_user_registration_notice" class="below-h2 <?php echo $ms_class; ?>" style="display:none">
            <p>
                <strong><?php _e( $error_class, 'social-login-bws' ); ?></strong>: <?php _e( "You're going to deny user registration via social buttons regardless WordPress default settings. Make sure that you understand the consequences. Check the following", 'social-login-bws' ); ?>
                <a target="_blank" href="https://support.bestwebsoft.com/hc/en-us/articles/360000371546"><?php _e( 'article', 'social-login-bws' ); ?></a>
            </p>
        </div>
    <?php }
}

/* Function for getting_current_commenter */
if ( ! function_exists( 'scllgn_get_current_commenter' ) ) {
    function scllgn_get_current_commenter() {
        $userdata = $_SESSION['scllgn_userdata'];
        $comment_author			= $userdata['display_name'];
        $comment_author_email	= $userdata['user_email'];
        $comment_author_url		= '';
        return compact( 'comment_author', 'comment_author_email', 'comment_author_url' );
    }
}

/* Function for enqueue_scripts */
if ( ! function_exists( 'scllgn_enqueue_scripts' ) ) {
    function scllgn_enqueue_scripts() {
        global $scllgn_options, $scllgn_providers, $scllgn_plugin_info;
        bws_enqueue_settings_scripts();
        if( is_admin() ) {
            /*Adding styles for dashicons*/
            wp_enqueue_style( 'scllgn_admin_page_stylesheet', plugins_url( 'css/admin_page.css', __FILE__ ) );
        }
        if ( isset( $_GET['page'] ) && 'social-login.php' == $_GET['page'] ) {
            /*Adding styles for buttons*/
            wp_enqueue_style( 'scllgn_login_style', plugins_url( 'css/style-login.css', __FILE__ ) );
            /* Adding script to settings page */
            wp_enqueue_script( 'scllgn_script', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery' ), $scllgn_plugin_info['Version'] );

            if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] ) {
                bws_plugins_include_codemirror();
            }
        } elseif ( scllgn_is_login_page() || scllgn_is_signup_page() || ! is_admin() && is_singular() && comments_open() && ! is_user_logged_in() && ! empty( $scllgn_options["comment_form"] ) ) {
            /* Adding style to pages with comments and custom login pages */
            foreach ( $scllgn_providers as $provider => $provider_name ) {
                if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) ) {
                    $enqueue_style = true;
                }
            }
            if ( ! empty( $enqueue_style ) ) {
                scllgn_login_enqueue_scripts( true );
                wp_enqueue_style( 'scllgn_style', plugins_url( 'css/style.css', __FILE__ ), array( 'dashicons' ), $scllgn_plugin_info['Version'] );
            }
            if ( ! empty( $_SESSION['scllgn_userdata'] ) ) {
                /* userdata is set, filling data into the comment form */
                add_filter( 'wp_get_current_commenter', 'scllgn_get_current_commenter' );
            }
        } elseif ( defined( 'BWS_ENQUEUE_ALL_SCRIPTS' ) ) {
            wp_enqueue_style( 'scllgn_style', plugins_url( 'css/style.css', __FILE__ ), array( 'dashicons' ), $scllgn_plugin_info['Version'] );
            scllgn_login_enqueue_scripts( true );
        }
    }
}

/* Login form scripts */
if ( ! function_exists( 'scllgn_login_enqueue_scripts' ) ) {
    function scllgn_login_enqueue_scripts( $is_custom_login = false ) {
        global $scllgn_plugin_info, $scllgn_providers, $scllgn_options;

        foreach ( $scllgn_providers as $provider => $provider_name ) {
            if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) ) {
                $enqueue_script = true;
                if ( ! $is_custom_login &&
                    (
                        /* Adding styles to the login page */
                        ( ! isset( $_REQUEST['action'] ) && ! empty( $scllgn_options['login_form'] ) ) ||
                        /* Adding styles to the register page */
                        ( ! empty( $_REQUEST['action'] ) && 'register' == $_REQUEST['action'] && ! empty( $scllgn_options['register_form'] ) )
                    )
                ) {
                    $enqueue_style = true;
                }
            }
        }

        if ( ! empty( $enqueue_style ) ) {
            wp_enqueue_style( 'scllgn_login_style', plugins_url( 'css/style-login.css', __FILE__ ), array( 'dashicons', 'bws-admin-css' ), $scllgn_plugin_info['Version'] );
        }

        if ( ! empty( $enqueue_script ) ) {
            wp_enqueue_style( 'bws-admin-css', bws_menu_url( 'css/general_style.css' ) );
            wp_enqueue_script( 'scllgn_login_script', plugins_url( 'js/script-login.js', __FILE__ ), array( 'jquery' ), $scllgn_plugin_info['Version'] );
            wp_localize_script( 'scllgn_login_script', 'scllgn_ajax',
                array(
                    'ajaxurl'		=> admin_url( '/admin-ajax.php' ),
                    'scllgn_nonce' 	=> wp_create_nonce( plugin_basename( __FILE__ ), 'scllgn_nonce' ),
                    'is_login_page'	=> scllgn_is_login_page()
                )
            );
        }
    }
}

/* Check if specified page is login page. Uses current page URL if $url is empty */
if ( ! function_exists( 'scllgn_is_login_page' ) ) {
    function scllgn_is_login_page( $url = '' ) {
        $login_pages_array = apply_filters( 'scllgn_login_urls', array( wp_login_url() ) );
        if ( empty( $url ) ) {
            $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        } else {
            $url = preg_replace( '~^((.)*://)~U', '', $url );
        }
        foreach ( $login_pages_array as $login_page ) {
            $pattern = preg_replace( '~^((.)*://)~U', '', $login_page );
            $pattern = '~^' . preg_quote( $pattern ) . '~U';
            if ( preg_match( $pattern, $url ) ) {
                return true;
            }
        }
        return false;
    }
}

/* Check if specified page is wp-signup.php page */
if ( ! function_exists( 'scllgn_is_signup_page' ) ) {
    function scllgn_is_signup_page() {
        if ( ( strpos( $_SERVER['PHP_SELF'], 'wp-signup.php' ) ) ) {
            return true;
        } else {
            return false;
        }
    }
}

/* New user social registration, register or authenticate users */
if ( ! function_exists( 'scllgn_social_regiser' ) ) {
    function scllgn_social_regiser( $userinfo, $provider_name = '' ) {
        global $error, $scllgn_options;
        $error = '';
        $userdata = array(
            'user_login'		=> $userinfo->id,
            'user_email'		=> $userinfo->email,
            'nickname'			=> $userinfo->name,
            'first_name'		=> $userinfo->name,
            'display_name'		=> $userinfo->name,
            'user_nicename'		=> $userinfo->name,
        );
        $email_is_verified = $userinfo->email;
        $user = get_user_by( 'login', $userinfo->id );
        if ( ! $user && $email_is_verified ) {
            $user = scllgn_get_user( $userinfo->email, $userinfo->id, $provider_name );
        }

        $scllgn_func_per = scllgn_registration_enabled();

        if ( ! $user ) {
            if ( ! empty( $scllgn_func_per ) ) {
                if ( $email_is_verified ) {
                    $default_role = get_option( 'default_role' );
                    if ( $scllgn_options['allow_registration'] == 'allow' ) {
                        $userdata['role'] = $scllgn_options['user_role'];
                    }
                    if ( $scllgn_options['allow_registration'] == 'default' ) {
                        $userdata['role'] = $default_role;
                    }
                    $userdata['user_pass'] = wp_generate_password( $length = 12, $include_standard_special_chars = false );
                    $user_id = wp_insert_user( $userdata ) ;
                    if ( ! is_wp_error( $user_id ) ) {
                        scllgn_login_user( $user_id );
                    }
                }
            } else {
                if ( ! empty( $_SESSION['scllgn_redirect'] ) ) {
                    $_SESSION['scllgn_userdata'] = $userdata;
                    $redirect = $_SESSION['scllgn_redirect'];
                    unset( $_SESSION['scllgn_redirect'] );
                    wp_safe_redirect( $redirect );
                    exit();
                } else {
                    /* new users registration is disabled */
                    $error = 'register_disabled';
                }
                if ( ! empty( $error ) ) {
                    /* redirecting to login page on error with error message */
                    $login_redirect_url = filter_var( wp_login_url() . "?error=$error", FILTER_SANITIZE_URL );
                    wp_redirect( $login_redirect_url );
                    exit();
                }
            }
        } elseif ( $user instanceof WP_User ) {
            scllgn_login_user( $user->ID );
        }
    }
}

/* Get page contents using CURl or file_get_contents */
if ( ! function_exists( 'scllgn_get_url_contents' ) ) {
    function scllgn_get_url_contents( $url = '' ) {
        if ( empty( $url ) ) {
            return false;
        }

        if ( ! function_exists( 'curl_init' ) ) {
            $response = file_get_contents( $url );
        } else {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
            $response = curl_exec( $ch );
            curl_close( $ch );
        }
        return $response;
    }
}
/*Function include library hybridauth*/
if( ! function_exists( 'scllgn_social_client' ) ) {
    function scllgn_social_client( $provider_for_auth ) {
        global $scllgn_options;
        require_once( "hybrid/hybridauth/Hybrid/Auth.php" );
        if ( ! isset( $_SESSION ) ) {
            session_start();
        }
        if ( empty( $scllgn_options ) ) {
            $scllgn_options = get_option( 'scllgn_options' );
        }
        $baseurl = home_url() . "/wp-content/plugins/social-login-bws/hybrid/hybridauth/";
        $config = array(
            //Location where to redirect users once they authenticate with a provider
            'base_url' => $baseurl,
            //Providers specifics
            'providers' => array(
                'Twitter' => array(
                    'enabled' => true,     //Optional: indicates whether to enable or disable Twitter adapter. Defaults to false
                    'keys' => array(
                        'key'    => $scllgn_options['twitter_client_id'], //Required: your Twitter consumer key
                        'secret' => $scllgn_options['twitter_client_secret']  //Required: your Twitter consumer secret
                    ),
                ),
                "LinkedIn"  => array(
                    "enabled" => true,
                    "keys"    => array(
                        "id" => $scllgn_options['linkedin_client_id'],
                        "secret" => $scllgn_options['linkedin_client_secret']
                    ),
                    "scope"   => array( "r_basicprofile", "r_emailaddress", "w_share" ), // optional
                    "fields"  => array( "id", "email-address", "first-name", "last-name" ), // optional
                ),
                "Google" => array(
                    'enabled' => true,     //Optional: indicates whether to enable or disable Twitter adapter. Defaults to false
                    'keys' => array(
                        'id'     => $scllgn_options['google_client_id'],
                        'secret' => $scllgn_options['google_client_secret']
                    ),
                    "access_type"     => "offline",   // optional
                    "approval_prompt" => "force",     // optional
                    "hd"              => "gmail.com" // optional
                ),
                "Facebook" => array(
                    "enabled" => true,
                    "keys"    => array(
                        "id" => $scllgn_options['facebook_client_id'],
                        "secret" => $scllgn_options['facebook_client_secret'],
                    ),
                    "scope"   => array( 'email', 'user_birthday', 'user_hometown' ),
                ),
            ),
        );
        if ( isset( $_REQUEST['hauth_start'] ) || isset( $_REQUEST['hauth_done'] ) ) {
            Hybrid_Endpoint::process();
        } else {
            try {
                $hybridauth = new Hybrid_Auth( $config );
                /**/
                $adapter = $hybridauth -> authenticate( $provider_for_auth );
                $redirect = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
                header( 'Location: ' . filter_var( $redirect, FILTER_SANITIZE_URL ) );

                $user_profile = $adapter->getUserProfile();

                $userdata = array(
                    'user_login'		=> $user_profile->identifier,
                    'user_email'		=> $user_profile->email,
                    'nickname'			=> $user_profile->displayName,
                    'first_name'		=> $user_profile->firstName,
                    'display_name'		=> $user_profile->displayName,
                );
                $email_is_verified = $user_profile->emailVerified;
                if( $provider_for_auth == 'Twitter' ){
                    $email_is_verified = true;
                }
                /* checking if user already exists */
                $user = get_user_by( 'login', $user_profile->identifier );
                if ( ! $user && $email_is_verified ) {
                    $user = scllgn_get_user( $user_profile->email, $user_profile->identifier, 'twitter' );
                }
                $hybridauth_per_for_function = scllgn_registration_enabled();
                $anyone_can_register = get_option( 'users_can_register' );
                if ( ! $user ) {
                    if ( ! empty( $hybridauth_per_for_function ) ) {
                        if ( $email_is_verified ) {
                            $default_role = get_option( 'default_role' );
                            if ( $scllgn_options['allow_registration'] == 'allow' ) {
                                $userdata['role'] = $scllgn_options['user_role'];
                            } elseif ( $scllgn_options['allow_registration'] == 'default' ) {
                                $userdata['role'] = $default_role;
                            }
                            $userdata['user_pass'] = wp_generate_password( $length = 12, $include_standard_special_chars = false );
                            $user_id = wp_insert_user( $userdata ) ;
                            if ( ! is_wp_error( $user_id ) ) {
                                scllgn_login_user( $user_id );
                            }
                        }
                    } else {
                        if ( ! empty( $_SESSION['scllgn_redirect'] ) ) {
                            $_SESSION['scllgn_userdata'] = $userdata;
                            $redirect = $_SESSION['scllgn_redirect'];
                            unset( $_SESSION['scllgn_redirect'] );
                            wp_safe_redirect( $redirect );
                            exit();
                        } else {
                            /* new users registration is disabled */
                            $error = 'register_disabled';
                        }
                        if ( ! empty( $error ) ) {
                            /* redirecting to login page on error with error message */
                            $login_redirect_url = filter_var( wp_login_url() . "?error=$error", FILTER_SANITIZE_URL );
                            wp_redirect( $login_redirect_url );
                            exit();
                        }
                    }
                }elseif ( $user instanceof WP_User ) {
                    scllgn_login_user( $user->ID );
                }
            } catch ( Exception $e ) {
                echo "Ooophs, we got an error: " . $e->getMessage();
            }
        }
        return false;
    }
}

/*function for adding quotes. Using for twitter auth */
if ( ! function_exists( 'scllgn_add_quotes' ) ) {
    function scllgn_add_quotes( $str )
    {
        return '"' . $str . '"';
    }
}

/* adding error message to the login form */
if ( ! function_exists( 'scllgn_login_error' ) ) {
    function scllgn_login_error( $message = '' ) {
        global $error;
        if ( ! empty( $_REQUEST['error'] ) ) {
            $messages = array(
                'access_denied'				=> __( 'please allow the access to your profile information.', 'social-login-bws' ),
                'register_error'			=> __( 'failed to register new user.', 'social-login-bws' ),
                'register_disabled'			=> __( 'new users registration is disabled.', 'social-login-bws' ),
                'verify_email'				=> __( 'you need to verify your Account Email.', 'social-login-bws' ),
                'insufficient_user_data'	=> __( 'user data is insufficient for registration.', 'social-login-bws' ),
                'invalid_token_data'		=> __( 'provided token data is invalid.', 'social-login-bws' ),
                'invalid_token'				=> __( 'provided token is invalid.', 'social-login-bws' ),
                'login_error'				=> __( 'login failed.', 'social-login-bws' )
            );

            $error_message = isset( $messages[ $_REQUEST['error'] ] ) ? $messages[ $_REQUEST['error'] ] : esc_html( esc_attr( $_REQUEST['error'] ) );

            $error = ( ! empty( $error ) ) ? $error . "\n" : "";
            $error .= sprintf(
                '<strong>%1$s</strong>: %2$s',
                __( 'Error', 'social-login-bws' ),
                $error_message
            );
        }
        return $message;
    }
}

/* Prepare and return login button for specified provider */
if ( ! function_exists( 'scllgn_get_button' ) ) {
    function scllgn_get_button( $provider = '', $echo = false ) {
        global $scllgn_options;
        require_once( "hybrid/hybridauth/Hybrid/Auth.php" );
        $button = '';
        if ( 'google' == $provider ) {
            $authUrl = wp_login_url() . "?provider=google";
            $dashicon_for_button = 	'dashicons-googleplus';
            $button_html = $scllgn_options['button_display_google'];
            $button_text = $scllgn_options['google_button_name'];
            if ( isset( $_GET['provider'] ) && 'google' == $_GET['provider'] )
            {
                scllgn_social_client( 'Google' );
            }
        }
        if ( 'facebook' == $provider ) {
            $authUrl = wp_login_url() . "?provider=facebook";
            $dashicon_for_button = 	'dashicons-facebook';
            $button_html = $scllgn_options['button_display_facebook'];
            $button_text = $scllgn_options['facebook_button_name'];
            if ( isset( $_GET['provider'] ) && 'facebook' == $_GET['provider'] )
            {
                scllgn_social_client( 'Facebook' );
            }
        }
        if ( 'twitter' == $provider ) {
            $authUrl = wp_login_url() . "?provider=twitter";
            $dashicon_for_button = 	'dashicons-twitter';
            $button_html = $scllgn_options['button_display_twitter'];
            $button_text = $scllgn_options['twitter_button_name'];
            if ( isset( $_GET['provider'] ) && 'twitter' == $_GET['provider'] )
            {
                scllgn_social_client( 'Twitter' );
            }
        }
        if ( 'linkedin' == $provider ) {
            $authUrl = wp_login_url() . "?provider=linkedin";
            $dashicon_for_button = 	'bws-icons';
            $button_html = $scllgn_options['button_display_linkedin'];
            $button_text = $scllgn_options['linkedin_button_name'];
            if ( isset( $_GET['provider'] ) && 'linkedin' == $_GET['provider'] )
            {
                scllgn_social_client( 'LinkedIn' );
            }
        }
        if( 'long' == $button_html ) {
            $button .=	sprintf(
                '<a href="%1$s" class="scllgn_login_button scllgn_button_%2$s scllgn_login_button_long scllgn_%5$s_button" id="scllgn_%5$s_button" data-scllgn-position="%2$s" data-scllgn-provider="%5$s">' .
                '<span class="dashicons %3$s""></span>' .
                '<span class="scllgn_button_text">%4$s</span>' .
                '</a>',
                $authUrl,
                $scllgn_options['loginform_buttons_position'],
                $dashicon_for_button,
                $button_text,
                $provider
            );
        } elseif ( 'short' == $button_html ) {
            $button .=	sprintf(
                '<a href="%1$s" class="scllgn_login_button scllgn_login_button_icon scllgn_%5$s_button_admin scllgn_login_button_short scllgn_button_%2$s scllgn_%5$s_button" data-scllgn-position="%2$s" data-scllgn-provider="%5$s" id="scllgn_%5$s_button">' .
                '<span class="dashicons %3$s scllgn_span_icon"></span>' .
                '</a>',
                $authUrl,
                $scllgn_options['loginform_buttons_position'],
                $dashicon_for_button,
                $button_text,
                $provider
            );
        }
        $button_text = apply_filters( 'scllgn_button_text', $button_text );
        $button = apply_filters( 'scllgn_' . $provider . '_button', $button );
        $button = apply_filters( 'scllgn_button', $button );

        if ( $echo ) {
            echo $button;
        }
        return $button;
    }
}

/* Adding Sign In buttons to the Login form page */
if ( ! function_exists( 'scllgn_login_form' ) ) {
    function scllgn_login_form()
    {
        global $scllgn_options, $scllgn_providers;
        if ( ! is_user_logged_in() ) {
            scllgn_display_all_buttons( 'login_form' );
            $buttons_short = $buttons_long = array();
            foreach ( $scllgn_providers as $provider => $provider_name ) {
                if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) ) {
                    if ( 'long' == $scllgn_options["button_display_{$provider}"] ) {
                        $buttons_long[ $provider ] = scllgn_get_button( $provider );
                    } else {
                        $buttons_short[ $provider ] = scllgn_get_button( $provider );
                    }
                }
            }
            if ( ! empty( $scllgn_options["login_form"] ) ){
                if ( ! empty( $buttons_short ) ) {
                    $buttons_short = implode( '', $buttons_short );
                    printf(
                        '<div class="scllgn_buttons_block">%s</div>',
                        $buttons_short
                    );
                }
                if ( ! empty( $buttons_long ) ) {
                    $buttons_long = implode( '', $buttons_long );
                    printf(
                        '<div class="scllgn_buttons_block">%s</div>',
                        $buttons_long
                    );
                }
            }
        }
    }
}

/* Adding Sign In buttons to the Register form page */
if ( ! function_exists( 'scllgn_register_form' ) ) {
    function scllgn_register_form() {
        global $scllgn_options, $scllgn_providers;


        if ( ! is_user_logged_in()  ) {
            $buttons_short = $buttons_long = array();

            foreach ( $scllgn_providers as $provider => $provider_name ) {
                if ( ! empty( $scllgn_options["{$provider}_is_enabled"] ) ) {
                    if ( 'long' == $scllgn_options["button_display_{$provider}"] ) {
                        $buttons_long[ $provider ] = scllgn_get_button( $provider );
                    } else {
                        $buttons_short[ $provider ] = scllgn_get_button( $provider );
                    }
                }
            }
            if( ! empty( $scllgn_options["register_form"] ) ) {
                if (!empty( $buttons_short ) ) {
                    $buttons_short = implode('', $buttons_short );
                    printf(
                        '<div class="scllgn_buttons_block">%s</div>',
                        $buttons_short
                    );
                }
                if ( !empty( $buttons_long ) ) {
                    $buttons_long = implode('', $buttons_long );
                    printf(
                        '<div class="scllgn_buttons_block">%s</div>',
                        $buttons_long
                    );
                }
            }
        }
    }
}

/* Adding Sign In buttons to the comment form */
if ( ! function_exists( 'scllgn_comment_form' ) ) {
    function scllgn_comment_form() {
        global $scllgn_options, $scllgn_providers;
        if ( comments_open() && ! is_user_logged_in() ) {
            scllgn_display_all_buttons( 'comment_form' );
            $buttons_short = $buttons_long = array();
            if ( ! empty( $_SESSION['scllgn_userdata'] ) ) {
                unset( $_SESSION['scllgn_userdata'] );
            }
            foreach ( $scllgn_providers as $provider => $provider_name ) {
                if ( !empty( $scllgn_options["{$provider}_is_enabled"] ) ) {
                    if ( 'long' == $scllgn_options["button_display_{$provider}"] || !empty($scllgn_options["comment_form"] ) ) {
                        $buttons_long[$provider] = scllgn_get_button( $provider );
                    } else {
                        $buttons_short[$provider] = scllgn_get_button( $provider );
                    }
                }
            }
            if( ! empty ( $scllgn_options["comment_form"] ) ) {
                if ( !empty( $buttons_short ) ) {
                    $buttons_short = implode('', $buttons_short );
                    printf(
                        '<div class="scllgn_buttons_block">%s</div>',
                        $buttons_short
                    );
                }
                if ( !empty( $buttons_long ) ) {
                    $buttons_long = implode('', $buttons_long );
                    printf(
                        '<div class="scllgn_buttons_block">%s</div>',
                        $buttons_long
                    );
                }
            }
        }
    }
}

/* Display all available buttons */
if ( ! function_exists( 'scllgn_display_all_buttons' ) ) {
    function scllgn_display_all_buttons( $form = '' ) {
        global $scllgn_options, $scllgn_providers;

        if ( ! is_user_logged_in() ) {
            $buttons_short = $buttons_long = array();
                foreach ( $scllgn_providers as $provider => $provider_name ) {
                    if ( !empty( $scllgn_options["{$provider}_is_enabled"] ) ) {
                        if ( 'long' == $scllgn_options["button_display_{$provider}"] ) {
                            $buttons_long[$provider] = scllgn_get_button( $provider );
                        } else {
                            $buttons_short[$provider] = scllgn_get_button( $provider );
                        }
                    }
                }
            if ( 'comment_form' == $form && ! empty( $scllgn_options["comment_form"] ) ) {
                $buttons_long = apply_filters( 'scllgn_sort_comment_buttons', $buttons_long );
                $buttons_short = apply_filters( 'scllgn_sort_comment_buttons', $buttons_short );
            }

            if ( 'login_form' == $form && ! empty( $scllgn_options["login_form"] ) ) {
                $buttons_long = apply_filters( 'scllgn_sort_login_buttons', $buttons_long );
                $buttons_short = apply_filters( 'scllgn_sort_login_buttons', $buttons_short );
            }

            if ( 'register_form' == $form ) {
                $buttons_long = apply_filters( 'scllgn_sort_register_buttons', $buttons_long );
                $buttons_short = apply_filters( 'scllgn_sort_register_buttons', $buttons_short );
            }
        }
    }
}

/* Logging user in */
if ( ! function_exists( 'scllgn_login_user' ) ) {
    function scllgn_login_user( $id ) {
        $remember = ( isset( $_REQUEST['scllgn_remember'] ) ) ? true : false;
        wp_clear_auth_cookie();
        wp_set_current_user( $id );
        wp_set_auth_cookie( $id, $remember );
        $redirect = admin_url();
        if ( ! empty( $_SESSION['scllgn_redirect'] ) ) {
            /* redirecting to the referrer page */
            $redirect = $_SESSION['scllgn_redirect'];
            unset( $_SESSION['scllgn_redirect'] );
        }
        if ( wp_login_url() == $redirect ) {
            $redirect = apply_filters( 'scllgn_redirect_url', admin_url() );
        }
        wp_redirect( $redirect );
        exit();
    }
}

/* adding social to allowed domains array */
if ( ! function_exists( 'scllgn_allow_redirect' ) ) {
    function scllgn_allow_redirect( $allowed ) {
        $allowed[] = 'www.google.com';
        $allowed[] = 'www.facebook.com';
        $allowed[] = 'www.twitter.com';
        $allowed[] = 'www.linkedin.com';
        return $allowed;
    }
}

/* Functions creates other links on plugins page. */
if ( ! function_exists( 'scllgn_action_links' ) ) {
    function scllgn_action_links( $links, $file ) {
        if ( ! is_network_admin() ) {
            /* Static so we don't call plugin_basename on every plugin row. */
            static $this_plugin;
            if ( ! $this_plugin ) {
                $this_plugin = plugin_basename( __FILE__ );
            }
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
            $links[]	=	'<a href="https://support.bestwebsoft.com">' . __( 'Support', 'social-login-bws' ) . '</a>';
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
            if ( ! is_network_admin() ) {
                bws_plugin_banner_to_settings( $scllgn_plugin_info, 'scllgn_options', 'social-login-bws', 'admin.php?page=social-login.php' );
            }
        }
        if ( isset( $_REQUEST['page'] ) && 'social-login.php' == $_REQUEST['page'] ) {
            bws_plugin_suggest_feature_banner( $scllgn_plugin_info, 'scllgn_options', 'social-login-bws' );
        }
    }
}

/* Adding "Social Login" block to the user profile page */
if ( ! function_exists( 'scllgn_user_profile' ) ) {
    function scllgn_user_profile() {
        global $scllgn_options, $scllgn_providers;
        $user_id = isset( $_REQUEST['user_id'] ) ? intval( $_REQUEST['user_id'] ) : get_current_user_id();

        $description_string = __( 'Enter %s to enable sign in with Social Login button.', 'social-login-bws' );

        $fields = array(
            'google'		=> array(
                'description'	=> sprintf(
                    $description_string,
                    __( 'existing Gmail address', 'social-login-bws' )
                ),
                'field_type'	=> 'email'
            ),
            'facebook'		=> array(
                'description'	=> sprintf(
                    $description_string,
                    __( 'existing email address of Facebook account', 'social-login-bws' )
                ),
                'field_type'	=> 'email'
            ),
            'twitter'		=> array(
                'description'	=> sprintf(
                    $description_string,
                    __( 'existing email address of Twitter account', 'social-login-bws' )
                ),
                'field_type'	=> 'email'
            ),
            'linkedin'		=> array(
                'description'	=> sprintf(
                    $description_string,
                    __( 'existing email address of LinkedIn account', 'social-login-bws' )
                ),
                'field_type'	=> 'email'
            )
        );
        if ( empty( $scllgn_options ) ) {
            scllgn_settings();
        }
        if ( 0 != $scllgn_options['google_is_enabled'] || 0 != $scllgn_options['facebook_is_enabled'] || 0 != $scllgn_options['twitter_is_enabled'] || 0 != $scllgn_options['linkedin_is_enabled'] ) { ?>
            <h2><?php _e( 'Social Login Accounts', 'social-login-bws' ); ?></h2>
            <table class="form-table scllgn-form-table">
                <?php foreach ( $scllgn_providers as $provider => $provider_name ) {
                    $provider_login = get_user_meta( $user_id, 'scllgn_' . $provider . '_login', true );
                    if ( $scllgn_options[ $provider . '_is_enabled'] ) { ?>
                        <tr class="scllgn_<?php echo $provider; ?>_email_field">
                            <th>
                                <?php echo $provider_name; ?>
                            </th>
                            <td>
                                <input type="<?php echo $fields[ $provider ]['field_type']; ?>" class="scllgn_login_field" name="<?php echo 'scllgn_' . $provider . '_login'; ?>" id="<?php echo 'scllgn_' . $provider . '_login'; ?>" value="<?php echo $provider_login; ?>">
                                <p class="description">
                                    <?php echo $fields[ $provider ]['description']; ?>
                                </p>
                            </td>
                        </tr>
                    <?php }
                }?>
            </table>
        <?php }
    }
}

/* updating user information */
if ( ! function_exists( 'scllgn_user_profile_update' ) ) {
    function scllgn_user_profile_update() {
        global $scllgn_options, $scllgn_providers;
        $user_id = isset( $_REQUEST['user_id'] ) ? intval( $_REQUEST['user_id'] ) : get_current_user_id();

        if ( empty( $scllgn_options ) ) {
            scllgn_settings();
        }

        foreach ( $scllgn_providers as $provider => $provider_name ) {
            if ( isset( $_POST['scllgn_' . $provider . '_login'] ) ) {
                $provider_login = trim( stripslashes( esc_html( $_POST['scllgn_' . $provider . '_login'] ) ) );
                if ( ! empty( $provider_login ) ) {
                    if ( is_email( $provider_login ) ) { /* preg_match is used for PHP versions older than 5.3 */
                        $user = scllgn_get_user( $provider_login, '', $provider );

                        if ( false === $user || $user_id === $user->ID ) {
                            update_user_meta( $user_id, 'scllgn_' . $provider . '_login', $provider_login );
                        }
                    }
                } else {
                    delete_user_meta( $user_id, 'scllgn_' . $provider . '_login' );
                }
            }
        }
    }
}

/* Adding errors on profile update */
if ( ! function_exists( 'scllgn_user_profile_update_errors' ) ) {
    function scllgn_user_profile_update_errors( $errors, $update = null, $user = null ) {
        global $scllgn_options, $scllgn_providers;

        $user_id = isset( $_REQUEST['user_id'] ) ? intval( $_REQUEST['user_id'] ) : get_current_user_id();

        if ( empty( $scllgn_options ) ) {
            scllgn_settings();
        }

        $providers_data = array(
            'general'		=> array(
                'messages'		=> array(
                    'in_use'		=> __( 'This email is already registered, please choose another one.', 'social-login-bws' )
                )
            ),
            'google'		=> array(
                'type'			=> 'email',
                'messages'		=> array(
                    'in_use'		=> sprintf(
                        __( 'The %1$s you specified for %2$s Account is already used by another user.', 'social-login-bws' ),
                        __( 'email address', 'social-login-bws' ),
                        'Google'
                    ),
                    'invalid'		=> sprintf(
                        __( 'Please enter valid %1$s Account %2$s', 'social-login-bws' ),
                        'Google',
                        __( 'email', 'social-login-bws' )
                    )
                )
            ),
            'facebook'		=> array(
                'type'			=> 'email',
                'messages'		=> array(
                    'in_use'		=> sprintf(
                        __( 'The %1$s you specified for %2$s Account is already used by another user.', 'social-login-bws' ),
                        __( 'email address', 'social-login-bws' ),
                        'Facebook'
                    ),
                    'invalid'		=> sprintf(
                        __( 'Please enter valid %1$s Account %2$s', 'social-login-bws' ),
                        'Facebook',
                        __( 'email', 'social-login-bws' )
                    )
                )
            ),
            'twitter'		=> array(
                'type'			=> 'email',
                'messages'		=> array(
                    'in_use'		=> sprintf(
                        __( 'The %1$s you specified for %2$s Account is already used by another user.', 'social-login-bws' ),
                        __( 'email address', 'social-login-bws' ),
                        'Twitter'
                    ),
                    'invalid'		=> sprintf(
                        __( 'Please enter valid %1$s Account %2$s', 'social-login-bws' ),
                        'Twitter',
                        __( 'email', 'social-login-bws' )
                    )
                )
            ),
            'linkedin'		=> array(
                'type'			=> 'email',
                'messages'		=> array(
                    'in_use'		=> sprintf(
                        __( 'The %1$s you specified for %2$s Account is already used by another user.', 'social-login-bws' ),
                        __( 'email address', 'social-login-bws' ),
                        'LinkedIn'
                    ),
                    'invalid'		=> sprintf(
                        __( 'Please enter valid %1$s Account %2$s', 'social-login-bws' ),
                        'LinkedIn',
                        __( 'email', 'social-login-bws' )
                    )
                )
            )
        );

        if ( isset( $_POST['email'] ) ) {
            $error_codes = $errors->get_error_codes();
            if ( ! in_array( 'email_exists', $error_codes ) ) {
                $user_email = sanitize_text_field( wp_unslash( $_POST['email'] ) );
                $user = scllgn_get_user( $user_email );
                if ( false !== $user && $user_id != $user->ID ) {
                    $error_message = sprintf(
                        '<strong>%1$s</strong>: %2$s',
                        __( 'ERROR', 'social-login-bws' ),
                        $providers_data['general']['messages']['in_use']
                    );
                    $errors->add( 'scllgn_email_exists', $error_message );
                }
            }
        }

        foreach ( $scllgn_providers as $provider => $provider_name ) {
            if ( isset( $_POST['scllgn_' . $provider . '_login'] ) ) {
                $provider_login = sanitize_text_field( wp_unslash( $_POST['scllgn_'. $provider . '_login'] ) );
                if ( ! empty( $provider_login ) && 'email' == $providers_data[ $provider ][ 'type' ] && ! is_email( $provider_login ) ) {
                    $error_message = sprintf(
                        '<strong>%1$s</strong>: %2$s',
                        __( 'ERROR', 'social-login-bws' ),
                        $providers_data[ $provider ]['messages']['invalid']
                    );
                    $errors->add( 'scllgn_' . $provider . '_login_validation_error', $error_message );
                }

                if ( ! empty( $provider_login ) ) {
                    $user = scllgn_get_user( $provider_login );
                    if ( false !== $user && $user_id != $user->ID ) {
                        $error_message = sprintf(
                            '<strong>%1$s</strong>: %2$s',
                            __( 'ERROR', 'social-login-bws' ),
                            $providers_data[ $provider ]['messages']['in_use']
                        );
                        $errors->add( 'scllgn_' . $provider . '_login_unavailable', $error_message );
                    }
                }
            }
        }
    }
}

/* Function for delete options */
if ( ! function_exists( 'scllgn_delete_options' ) ) {
    function scllgn_delete_options() {
        global $scllgn_providers, $scllgn_options;
        scllgn_settings();
        if ( function_exists( 'is_multisite' ) && is_multisite() ) {
            global $wpdb;
            $old_blog = $wpdb->blogid;
            /* Get all blog ids */
            $blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
            foreach ( $blogids as $blog_id ) {
                switch_to_blog( $blog_id );
                if ( $scllgn_options['delete_metadata'] ) {
                    foreach ( $scllgn_providers as $provider => $provider_name ) {
                        delete_metadata( 'user', 1, 'scllgn_' . $provider . '_login', false, true );
                    }
                }
                delete_option( 'scllgn_options' );
            }
            switch_to_blog( $old_blog );
        } else {
            if ( $scllgn_options['delete_metadata'] ) {
                foreach ( $scllgn_providers as $provider => $provider_name ) {
                    delete_metadata( 'user', 1, 'scllgn_' . $provider . '_login', false, true );
                }
            }
            delete_option( 'scllgn_options' );
        }

        require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
        bws_include_init( plugin_basename( __FILE__ ) );
        bws_delete_plugin( plugin_basename( __FILE__ ) );
    }
}

/* The function receives data from AJAX */
function scllgn_ajax_data() {
    check_ajax_referer( plugin_basename( __FILE__ ), 'scllgn_nonce' );

    /* Get redirect url to session variable */
    if ( ! empty( $_POST['scllgn_url'] ) ) {
        $_SESSION['scllgn_redirect'] = strval( $_POST['scllgn_url'] );
    }

    wp_die();
}

register_activation_hook( __FILE__, 'scllgn_plugin_activate' );

/* Calling a function add administrative menu. */
add_action( 'admin_menu', 'add_scllgn_admin_menu' );
add_action( 'plugins_loaded', 'scllgn_plugins_loaded' );
add_action( 'init', 'scllgn_init' );
add_action( 'admin_init', 'scllgn_admin_init' );

if ( version_compare( phpversion(), '5.3', '>=' ) ) {
    add_action( 'login_form', 'scllgn_login_form' );
    add_filter( 'login_message', 'scllgn_login_error' );
    add_filter( 'scllgn_get_user_filter', 'scllgn_get_user', 10, 3 );
    add_filter( 'scllgn_get_user_email', 'scllgn_get_user_email', 10, 1 );
    add_filter( 'sbscrbr_get_user_email', 'scllgn_get_user_email', 10, 1 );
    add_action( 'register_form', 'scllgn_register_form' );
    /* Adding to 'signup_extra_fields' hook form signup.php */
    add_action( 'signup_extra_fields', 'scllgn_register_form' );
    add_action( 'comment_form_top', 'scllgn_comment_form' );
    add_action( 'scllgn_login_form', 'scllgn_login_form' );
    add_action( 'scllgn_register_form', 'scllgn_register_form' );
    add_action( 'scllgn_comment_form', 'scllgn_comment_form' );
    add_action( 'scllgn_display_all_buttons', 'scllgn_display_all_buttons' );

    /* Adding stylesheets */
    add_action( 'wp_enqueue_scripts', 'scllgn_enqueue_scripts' );
    add_action( 'login_enqueue_scripts', 'scllgn_login_enqueue_scripts' );
    add_filter( 'allowed_redirect_hosts','scllgn_allow_redirect' );
    /* Adding to 'signup_extra_fields' hook form signup.php */
    add_action( 'signup_extra_fields', 'scllgn_enqueue_scripts' );
    add_action( 'signup_extra_fields', 'scllgn_login_enqueue_scripts' );
}

/* Adding stylesheets */
add_action( 'admin_enqueue_scripts', 'scllgn_enqueue_scripts' );

/* Additional links on the plugin page */
add_filter( 'plugin_action_links', 'scllgn_action_links', 10, 2 );
add_filter( 'plugin_row_meta', 'scllgn_links', 10, 2 );

/* Adding banner */
add_action( 'admin_notices', 'scllgn_plugin_banner' );

/* adding custom fields to the user profile page*/
add_action( 'show_user_profile', 'scllgn_user_profile' );
add_action( 'edit_user_profile', 'scllgn_user_profile' );
/* update user profile information */
add_action( 'edit_user_profile_update', 'scllgn_user_profile_update' );
add_action( 'personal_options_update', 'scllgn_user_profile_update' );
add_action( 'user_profile_update_errors', 'scllgn_user_profile_update_errors' );

/* Adding AJAX*/
add_action( 'wp_ajax_scllgn_remember', 'scllgn_ajax_data' );
add_action( 'wp_ajax_nopriv_scllgn_remember', 'scllgn_ajax_data' );