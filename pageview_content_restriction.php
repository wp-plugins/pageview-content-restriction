<?php
/*
Plugin Name: Pageview Content Restriction
Plugin URI: http://curlybracket.net/2014/04/15/pageview-content-restriction/
Description: Restrict access after a maximum number of pageviews to unauthenticated users, then redirect to login or selected page.
Version: 1.0
Author: Ulrike Uhlig
Author URI: http://curlybracket.net
License: GPL2
*/
/*
    Copyright 2014  Ulrike Uhlig (email : u@curlybracket.net)

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
?>
<?php
/*
 *  TODO
 *  - make plugin translateable
 *  - find a solution for Tor / TorBrowserBundle users
 */

$upload_dir = wp_upload_dir();
global $pageview_sessionfolder_path;
$pageview_sessionfolder_path = $upload_dir['basedir']."/unauthenticatedsessions";

function pageview_content_restriction_activate() {
    // create directory to store userdata if not exists yet
    global $pageview_sessionfolder_path;
    if(!is_dir($pageview_sessionfolder_path)) {
        mkdir($pageview_sessionfolder_path);
        chmod($pageview_sessionfolder_path, 0777);
    }
}
register_activation_hook( __FILE__, 'pageview_content_restriction_activate' );

/*
 * if user is not logged in, and has seen more than $pageview_max pages,
 * redirect the user to the login / registration page
 * @param $pageview_max type int
 * */
function pageview_content_restriction() {
    // homepage is always visible, even if the user has exceeded maxpageviews
    if( !is_user_logged_in() AND !is_front_page()) {
        // get options
        $pageview_content_restriction_options = get_option('pageview_content_restriction_option_name');
        $redirect = $pageview_contentrestriction_options['redirect'];
        $maxpageviews = $pageview_contentrestriction_options['maxpageviews'];

        // define default max pageviews
        if(!$maxpageviews OR !is_int($maxpageviews)) { $maxpageviews = 50;  }

        // get / increment current pageviews
        $current_pageviews = pageview_session_counter($maxpageviews);

        // this is just debugging information which could also be stored in the next if()
        echo "<!-- Pageviews : $current_pageviews -->";

            if($current_pageviews > $maxpageviews) {
                if(empty($redirect)) {
                    $redirect = get_bloginfo('url')."/wp-login.php";
                }
                $status = "302";
                wp_redirect( $redirect, $status );
                exit;
            }
        ob_flush();
    }
}
add_action('get_header', 'pageview_content_restriction');

/*
 * this is needed for the redirection
 */
function pageview_content_restriction_output_buffer() {
    ob_start();
}
add_action('init', 'pageview_content_restriction_output_buffer');

/*
 * Provide session handling
 * from http://wordpress.org/plugins/simple-session-support/
 */
add_action('init', 'pageview_session_start', 1);

/**
 * start the session, after this call the PHP $_SESSION super global is available
 */
function pageview_session_start() {
    if(!session_id()) {
        // how long does the session cookie last
        session_set_cookie_params(9900, "/"); // 3600 seconds = 1h
        session_start();
    }
	ob_start();
}

/**
 * destroy the session, this removes any data saved in the session over logout-login
 */
function pageview_session_destroy() {
    session_destroy ();
}

/**
 * get a value from the session array
 * @param type $key the key in the array
 * @param type $default the value to use if the key is not present. empty string if not present
 * @return type the value found or the default if not found
 */
function pageview_session_get($key, $default='') {
    if(isset($_SESSION[$key])) {
        return $_SESSION[$key];
    }
}

/**
 * set a value in the session array
 * @param type $key the key in the array
 * @param type $value the value to set
 */
function pageview_session_set($key, $value) {
    $_SESSION[$key] = $value;
}

/*
 * get unique browser ID
 */
function get_unique_browser_identifier() {
    if(strpos($_SERVER['HTTP_USER_AGENT'], "Googlebot") !== FALSE || strpos($_SERVER['HTTP_USER_AGENT'], "Yahoo! Slurp") !== FALSE || strpos($_SERVER['HTTP_USER_AGENT'], "msnbot") !== FALSE) {
        return "donotblock";
    } else {
        // credit for this idea to identify visitors goes to Emmanuel Revah / manurevah.com
		return sha1($_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT'].$_SERVER['HTTP_ACCEPT_LANGUAGE'].$_SERVER['HTTP_ACCEPT_CHARSET'].$_SERVER['HTTP_ACCEPT_ENCODING']);
    }
}

function pageview_session_counter($maxpageviews) {
    global $pageview_sessionfolder_path;
    if (get_unique_browser_identifier() == "donotblock") {
        // major bots are not blocked.
        return 0;
    } else {
        /*
        * in any case
        * increment the pageview counting session cookie
        * or set the session cookie if it does not exist yet
        */
        $pageview_counter = pageview_session_get('pageview_counter');
        if(!$pageview_counter) { $pageview_counter = 0; }
        $pageview_counter++;

        /*
        * verify also if there is a session file for the unique browser id
        * if so, verify if this file has already a pageview count and increment it, too
        * if not, use the session cookie's value and write this to the file
        */
        if(is_dir($pageview_sessionfolder_path)) {
            $file = $pageview_sessionfolder_path.'/'.get_unique_browser_identifier();
            if(file_exists($file)) {
                $stored_pageviews = file_get_contents($file);
                // verify how many pages this person has already seen
                if($stored_pageviews < $maxpageviews) {
                    $stored_pageviews++;
                    file_put_contents($file, $stored_pageviews);
                } else {
                    // maximum already reached
                    // don't modify the file, that is unnecessary
                }
            } else {
                // 1st time write file using the session cookie pagecount
                // sanitize the session cookie, so no strange data is written to the file
                file_put_contents($file, esc_attr(intval($pageview_counter)));
                chmod($file, 0777);
            }
        }

        /*
        * in any case, write pageview count to session
        * use the value of the file if it's higher than that of the cookie.
        */
        if($stored_pageviews > $pageview_counter) $pageview_counter = $stored_pageviews;
        pageview_session_set("pageview_counter", $pageview_counter);
        return $pageview_counter;
    }
}

/* if somebody logs in at some point, we need to shall his/her unique identifier file */
function delete_pageview_session_counter() {
    // unset the session cookie
    pageview_session_destroy();
    global $pageview_sessionfolder_path;

    $file = $pageview_sessionfolder_path.'/'.get_unique_browser_identifier();
    if(file_exists($file)) {
        unlink($file);
    }
}
add_action('wp_login', 'delete_pageview_session_counter');

class pageviewContentRestrictionPage {
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin',
            'Pageview Content Restriction',
            'manage_options',
            'pageview-content-restriction-admin',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'pageview_content_restriction_option_name' );
        ?>
        <div class="wrap">
            <h2>Pageview Content Restriction</h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'pageview_content_restriction_option_group' );
                do_settings_sections( 'pageview-content-restriction-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'pageview_content_restriction_option_group', // Option group
            'pageview_content_restriction_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'pageview_content_restriction_section_general', // ID
            'General Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'pageview-content-restriction-admin' // Page
        );

        add_settings_field(
            'redirect',
            'Redirection URL',
            array( $this, 'redirect_callback' ),
            'pageview-content-restriction-admin',
            'pageview_content_restriction_section_general'
        );

        add_settings_field(
            'maxpageviews',
            'Maxiumum pageviews before redirection',
            array( $this, 'maxpageviews_callback' ),
            'pageview-content-restriction-admin',
            'pageview_content_restriction_section_general'
        );


        add_settings_section(
            'pageview_content_restriction_section_clear', // ID
            'Clear data', // Title
            array( $this, 'print_section_delete_info' ), // Callback
            'pageview-content-restriction-admin' // Page
        );

        add_settings_field(
            'clear_data',
            'Clear pageview data',
            array( $this, 'clear_data_callback' ),
            'pageview-content-restriction-admin',
            'pageview_content_restriction_section_clear'
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {

        if( !empty( $input['redirect'] ) )
            $input['redirect'] = esc_url( $input['redirect'] );

        if( !empty( $input['maxpageviews'] ) )
            $input['maxpageviews'] = sanitize_text_field(intval( $input['maxpageviews'] ));

        if( isset( $input['clear_data'] ) ) {
            $input['clear_data'] = sanitize_text_field($input['clear_data'] );
            if($input['clear_data'] == "clear") {
                global $pageview_sessionfolder_path;
                array_map('unlink', glob("$pageview_sessionfolder_path/*"));
            }
        }

        return $input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Please fill in how many pages an unauthenticated user is allowed to see and where the user should be redirected to when the maximum is reached. This data is stored in a session cookie and an anonymized unique identifier file.';
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function redirect_callback()
    {
        printf(
            '<input type="text" id="redirect" name="pageview_content_restriction_option_name[redirect]" value="%s" class="regular-text ltr" />',
            esc_attr( $this->options['redirect'])
        );
        print '<p>Allowed protocols: http, https, ftp, ftps, mailto, news, irc, gopher, nntp, feed, telnet. If empty, redirection goes to wp-login.</p>';
    }

    public function maxpageviews_callback()
    {
        printf(
			'<input type="text" id="maxpageviews" name="pageview_content_restriction_option_name[maxpageviews]" class="regular-text ltr" value="%s" />',
			esc_attr( $this->options['maxpageviews'])
        );
        print '<p>Default is 50.</p>';
    }

    public function print_section_delete_info()
    {
        print 'In case you modified the maximum pageview, and you want to reset the counters to zero.';
    }
    public function clear_data_callback()
    {
        print '<input type="text" id="clear_data" name="pageview_content_restriction_option_name[clear_data]" class="regular-text ltr" value="" />';
        print '<p>If you want to clear existing data, and reset all counters to zero, please write "clear" in this textfield and hit "Save".</p>';
    }
}

if( is_admin() )
    $my_settings_page = new pageviewContentRestrictionPage();
