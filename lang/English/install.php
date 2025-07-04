<?php

// Language definitions used in install.php
$lang_install = array(

// Install Form
'Install PunBB'				=>	'Install ShadowBoard %s',
'Choose language'			=>	'Change installer language',
'Choose language help'		=>	'You can change the language of this install script if you find it easier to follow the instructions in your own language. Just choose your language from the list of installed ones below.',
'Installer language'		=>	'Installer language:',
'Choose language legend'	=>	'Installer language',
'Install intro'				=>	'In order to install ShadowBoard you must complete the form set out below. Please read the instructions provided before completing the form. If you encounter any difficulties with the installation, please refer to the documentation supplied as part of ShadowBoard\'s download package.',
'Part1'						=>	'Part 1 — Forum Administrator setup',
'Part1 legend'				=>	'Administrator\'s details',
'Part1 intro'				=>	'Please enter the requested information in order to setup an administrator account for your ShadowBoard installation. You can create more administrators and moderators later.',
'Admin username'			=>	'Admin\'s username',
'Admin password'			=>	'Admin\'s password',
'Admin confirm password'	=>	'Confirm password',
'Username help'				=>	'Between 2 and 25 characters.',
'Password help'				=>	'Minimum 4 characters. cAsE sEnsiTive.',
'Confirm password help'		=>	'Re-enter exactly as before.',
'Part2'						=>	'Part 2 — Forum setup',
'Part2 legend'				=>	'Forum information',
'Part2 intro'				=>	'Please enter the requested information. Pay particular attention to entering your base URL and read the instructions set out below carefully.',
'Base URL'					=>	'Base URL',
'Base URL info'				=>	'Please pay particular attention when entering your Base URL. You must set the correct Base URL or your forum will not work properly. The Base URL is the URL (without trailing slash) of your ShadowBoard forum (example: <em>http://forum.example.com</em> or <em>http://example.com/~myuser</em>). Please note that the preset value below is just an educated guess by ShadowBoard.',
'Base URL help'				=>	'The URL (without trailing slash) of your ShadowBoard installation.',
'Pun repository'			=>	'Pun repository',
'Pun repository help'		=>	'Install pun_repository extension (one-click extension downloader) after forum installation.',
'Start install'				=>	'Start install', // Label for submit button
'Required'					=>	'(Required)',
'Required warn'				=>	'All fields with bold label must be completed before this form is submitted.',

// Install errors
'No database support'		=>	'This PHP environment does not have support for any of the databases that PunBB supports. PHP needs to have support for either MySQL, PostgreSQL or SQLite in order for PunBB to be installed.',
'Missing database name'		=>	'You must enter a database name. Please go back and correct.',
'Username too long'			=>	'Usernames must be no more than 25 characters long. Please go back and correct.',
'Username too short'		=>	'Usernames must be at least 2 characters long. Please go back and correct.',
'Pass too short'			=>	'Passwords must be at least 4 characters long. Please go back and correct.',
'Username guest'			=>	'The username guest is reserved. Please go back and correct.',
'Username BBCode'			=>	'Usernames may not contain any of the text formatting tags (BBCode) that the forum uses. Please go back and correct.',
'Username reserved chars'	=>	'Usernames may not contain all the characters \', " and [ or ] at once. Please go back and correct.',
'Username IP'				=>	'Usernames may not be in the form of an IP address. Please go back and correct.',
'Invalid email'				=>	'The administrator email address you entered is invalid. Please go back and correct.',
'Missing base url'			=>	'You must enter a base URL. Please go back and correct.',
'No such database type'		=>	'\'%s\' is not a valid database type.',
'Invalid MySQL version'		=>	'You are running MySQL version %1$s. PunBB requires at least MySQL %2$s to run properly. You must upgrade your MySQL installation before you can continue.',
'Invalid table prefix'		=>	'The table prefix \'%s\' contains illegal characters or is too long. The prefix may contain the letters a to z, any numbers and the underscore character. They must however not start with a number. The maximum length is 40 characters. Please choose a different prefix.',
'SQLite prefix collision'	=>	'The table prefix \'sqlite_\' is reserved for use by the SQLite engine. Please choose a different prefix.',
'ShadowBoard already installed'	=>	'The file called "users.txt" is already present. This could mean that ShadowBoard is already installed or that another piece of software is installed',
'Invalid language'			=>	'The language pack you have chosen doesn\'t seem to exist or is corrupt. Please recheck and try again.',
'InnoDB Not Supported'		=> 'You are running MySQL version without InnoDB support.',

// Used in the install
'Default language'			=>	'Default language',
'Default language help'		=>	'(If you remove a language pack you must update this setting)',
'Default announce heading'	=>	'Sample announcement',
'Default announce message'	=>	'<p>Enter your announcement here.</p>',
'Default rules'				=>	'Enter your rules here.',
'Default category name'		=>	'Test category',
'Default forum name'		=>	'Test forum',
'Default forum descrip'		=>	'This is just a test forum',
'Default topic subject'		=>	'Test post',
'Default post contents'		=>	'If you are looking at this (which I guess you are), the install of PunBB appears to have worked! Now log in and head over to the administration control panel to configure your forum.',
'Default rank 1'			=>	'New member',
'Default rank 2'			=>	'Member',


// Installation completed form
'Success description'		=>	'Congratulations! PunBB %s has been successfully installed.',
'Final instructions'		=>	'Final instructions',
'No write info 1'			=>	'Important! To finalize the installation, you need to click on the button below to download a file called config.php. You then need to upload this file to the root directory of your PunBB installation.',
'No write info 2'			=>	'Once you have uploaded config.php, PunBB will be fully installed! You may then %s once config.php has been uploaded.',
'Go to index'				=>	'go to the forum index',
'Warning'					=>	'Warning!',
'No cache write'			=>	'<strong>The cache directory is currently not writable!</strong> In order for PunBB to function properly, the directory named <em>cache</em> must be writable by PHP. Use chmod to set the appropriate directory permissions. If in doubt, chmod to 0777.',
'No avatar write'			=>	'<strong>The avatar directory is currently not writable!</strong> If you want users to be able to upload their own avatar images you must see to it that the directory named <em>img/avatars</em> is writable by PHP. You can later choose to save avatar images in a different directory (see Administration/Settings/Features). Use chmod to set the appropriate directory permissions. If in doubt, chmod to 0777.',
'File upload alert'			=>	'<strong>File uploads appear to be disallowed on this server!</strong> If you want users to be able to upload their own avatar images you must enable the file_uploads configuration setting in PHP. Once file uploads have been enabled, avatar uploads can be enabled in Administration/Settings/Features.',
'Download config'			=>	'Download config.php file', // Label for submit button
'Write info'				=>	'PunBB has been fully installed! You may now %s.',
);
