<?php
/*
Script Name: WordPress Manipulator
Description: Logs into a WordPress using credentials and then allows you to do... something. this demo just hits wp-admin for every blog
Version: 0.1
Author: BrianLayman
Author URI: http://webdevstudios.com/team/brian-layman/
Script URI: http://webdevstudios.com/wordpress/vip-services-support/

Notes: 
	This script works for single site WordPress or the main site for the active network in a multisite install. It does not iterate sites or networks.
	This script can be called as a web page or from the CLI. Progress is logged to the screen and the error_log (with the prefix purgeRiskyComments for easy greping)
	From the CLI, if an argument is passed, it will function as the offset for starting the comment search. Non-integers are ignored.
	From the web if you pass the parameter, the same feature can be achieved with the ?offset= parameter

Use: Place this script in the root of your WordPress install and execute from CLI or web navigation

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( file_exists( 'manip_config.php' ) ) include( 'manip_config.php' );

if ( !defined('TARGET_URL' ) ) define( 'TARGET_URL', 'http://example.com' );
if ( !defined('LOGIN_PATH' ) ) define( 'LOGIN_PATH', '/wp-login.php' );
if ( !defined('WP_USER_NAME' ) ) define( 'WP_USER_NAME', 'admin' );
if ( !defined('WP_USER_PASSWORD' ) ) define( 'WP_USER_PASSWORD', 'changeme' );
if ( !defined('BLOCK_SIZE' ) ) define( 'BLOCK_SIZE', '5' ); // The number of blogs I request in this example
if ( !defined('ONGOING_TIME_OUT' ) ) define( 'ONGOING_TIME_OUT', '30' ); // The time in seconds allowed to get through BLOCK_SIZE actions

// Integrate with the WordPress environment
// Not needed if you aren't calling any WordPress functions
require( dirname( __FILE__ ) . '/wp-load.php' );

class wpManipApi {
	// Hold an instance of the class
	private static $m_pInstance;
	private static $curl;
	private static $ckfile;

	
	// A private constructor; prevents direct creation of object
	private function __construct() {
		$this->ckfile = tempnam( "/tmp", "cookie_wpManip_" );
		$this->curl = curl_init();	   
	}

	public static function getInstance() { 
		if ( !self::$m_pInstance ) { 
			self::$m_pInstance = new wpManipApi(); 
		} 

		return self::$m_pInstance; 
	} 
	

	function _authorize( $login, $password) {
		$url = TARGET_URL . LOGIN_PATH;
		curl_setopt( $this->curl, CURLOPT_URL, $url );
		curl_setopt( $this->curl, CURLOPT_COOKIEJAR, $this->ckfile );
		curl_setopt( $this->curl, CURLOPT_POST, true );
		curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->curl, CURLOPT_FOLLOWLOCATION, true ); 
		curl_setopt( $this->curl, CURLOPT_POSTFIELDS, "wp-submit=Log In&log=" . urlencode( $login ) . "&pwd=" . urlencode( $password ) . "&rememberme=forever&redirect_to=/wp-admin/&testcookie=1" );
		curl_setopt( $this->curl, CURLOPT_USERAGENT, "botd Mozilla/4.0 (Compatible; wpManip API)" );
		$result = curl_exec( $this->curl );
		return $result;
	}

	function hit_url($url) {
		curl_setopt ( $this->curl, CURLOPT_URL, $url );
		curl_setopt ( $this->curl, CURLOPT_COOKIEJAR, $this->ckfile );
		curl_setopt( $this->curl, CURLOPT_POST, false );
		curl_setopt( $this->curl, CURLOPT_POSTFIELDS, "" );
 		curl_setopt( $this->curl, CURLOPT_TIMEOUT, 50 );
		curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->curl, CURLOPT_FOLLOWLOCATION, true );
		$result = curl_exec( $this->curl );
		return $result;
	}
	
}

$pwpManipApi = wpManipApi::getinstance();
$pageInfo = $pwpManipApi->_authorize(WP_USER_NAME, WP_USER_PASSWORD );

// If page times out, set this initial value for $n from a $_GET value and instead of the while loop call a wp_redirect
// For not this serves the purpose of the example.
$n = 1;

while ( true ) {
	// At the start of the loop set a time out in case we slam the server accidentally, but restart the timer each loop so that we can complete the action
	set_time_limit(ONGOING_TIME_OUT);
	
	// This example assumes WordPress Multisite.  Scrap this whole loop for a single site application.
	$blogs = $wpdb->get_results( "SELECT * FROM {$wpdb->blogs} WHERE spam = '0' AND deleted = '0' AND archived = '0' ORDER BY registered DESC LIMIT {$n}, " . BLOCK_SIZE, ARRAY_A );
	if ( empty( $blogs ) ) {
	  break;
	}
	foreach ( (array) $blogs as $details ) {
		$siteurl = get_blog_option( $details['blog_id'], 'siteurl' );
		$pInfo = $pwpManipApi->hit_url( $siteurl . '/wp-admin' );
		// You might want to display something here. You could echo out pInfo for debugging purposes
		// echo $siteurl . '/wp-admin' . '<br />' ;
	}
	$n += BLOCK_SIZE;
	// Add a die() here to halt the run short for testing purposes
	// die();
}