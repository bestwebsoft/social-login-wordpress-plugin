<?php
if ( ! isset( $_SESSION ) ) {
	session_start();
}
if ( isset( $_GET['code'] ) ) {
	$_SESSION['facebook_code'] = $_GET['code'];
}
header( 'Location:' . $_SESSION['home_url'] . '/wp-login.php' );