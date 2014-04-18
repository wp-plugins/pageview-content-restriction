<?php
if( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

// delete directory with userdata
$upload_dir = wp_upload_dir();
$pageview_sessionfolder_path = $upload_dir['basedir']."/unauthenticatedsessions";
if(is_dir($pageview_sessionfolder_path)) {
	array_map('unlink', glob("$pageview_sessionfolder_path/*"));
	rmdir($pageview_sessionfolder_path);
}
