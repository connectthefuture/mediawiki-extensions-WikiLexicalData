<?php

// Take credit for your work.
$wgExtensionCredits['other'][] = array(

	// The full path and filename of the file. This allows MediaWiki
	// to display the Subversion revision number on Special:Version.
	'path' => __FILE__,

	// The name of the extension, which will appear on Special:Version.
	'name' => 'OmegaWiki',

	// Alternatively, you can specify a message key for the description.
	'descriptionmsg' => 'apiow-desc',

	// The version of the extension, which will appear on Special:Version.
	// This can be a number or a string.
	'version' => '1.0',

	// Your name, which will appear on Special:Version.
	'author' => array( 'Hiong3-eng5', 'Kip' ),

	// The URL to a wiki page/web page with information about the extension,
	// which will appear on Special:Version.
	'url' => 'https://www.omegawiki.org/omegawiki_api',

);

// Map class name to filename for autoloading
	$wgAutoloadClasses['define'] = dirname( __FILE__ ) . '/owDefine.php';
	$wgAutoloadClasses['addSyntrans'] = dirname( __FILE__ ) . '/owAddSyntrans.php';

// Map module name to class name
	$wgAPIModules['ow_define'] = 'define';
	$wgAPIModules['ow_add_syntrans'] = 'addSyntrans';

// Load the internationalization file
	$wgExtensionMessagesFiles['myextension']
	= dirname( __FILE__ ) . '/OmegaWiki.i18n.php';

// Return true so that MediaWiki continues to load extensions.
	return true;