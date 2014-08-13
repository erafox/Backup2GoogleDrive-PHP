Backup2GoogleDrive-PHP
======================

First, please get CLIENT_ID, SERVICE_ACCOUNT_NAME and KEY_PATH on Google API.

Configuration

Open index.php and edit define value.

define( 'BACKUP_FOLDER', 'PHPBackups1' );
define( 'SHARE_WITH_GOOGLE_EMAIL', 'youremail@gmail.com' );

define( 'CLIENT_ID',  'yourclientid' );
define( 'SERVICE_ACCOUNT_NAME', 'yourserviceaccountname' );
define( 'KEY_PATH', dirname(__FILE__).'/'.'yourkey');
