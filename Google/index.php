<?php

define( 'BACKUP_FOLDER', 'PHPBackups1' );
define( 'SHARE_WITH_GOOGLE_EMAIL', 'phuongph.911@gmail.com' );

define( 'CLIENT_ID',  '222021142112-opie9156kuce5fu5mad93mdif0u3ubeb.apps.googleusercontent.com' );
define( 'SERVICE_ACCOUNT_NAME', '222021142112-opie9156kuce5fu5mad93mdif0u3ubeb@developer.gserviceaccount.com' );
define( 'KEY_PATH', dirname(__FILE__).'/'.'f613d9ff1167.p12');

require_once 'Google/Google_Client.php';
require_once 'Google/contrib/Google_DriveService.php';

class DriveServiceHelper {

	protected $scope = array('https://www.googleapis.com/auth/drive');

	private $_service;

	public function __construct( $clientId, $serviceAccountName, $key ) {
		$client = new Google_Client();
		$client->setClientId( $clientId );

		$client->setAssertionCredentials( new Google_AssertionCredentials(
				$serviceAccountName,
				$this->scope,
				file_get_contents( $key ) )
		);

		$this->_service = new Google_DriveService($client);
	}

	public function __get( $name ) {
		return $this->_service->$name;
	}

	public function createFile( $name, $mime, $description, $content, Google_ParentReference $fileParent = null ) {
		$file = new Google_DriveFile();
		$file->setTitle( $name );
		$file->setDescription( $description );
		$file->setMimeType( $mime );

		if( $fileParent ) {
			$file->setParents( array( $fileParent ) );
		}

		$createdFile = $this->_service->files->insert($file, array(
				'data' => $content,
				'mimeType' => $mime,
		));

		return $createdFile['id'];
	}

	public function createFileFromPath( $path, $description, Google_ParentReference $fileParent = null ) {
		$fi = new finfo( FILEINFO_MIME );
		$mimeType = explode( ';', $fi->buffer(file_get_contents($path)));
		$fileName = preg_replace('/.*\//', '', $path );

		return $this->createFile( $fileName, $mimeType[0], $description, file_get_contents($path), $fileParent );
	}


	public function createFolder( $name ) {
		return $this->createFile( $name, 'application/vnd.google-apps.folder', null, null);
	}

	public function setPermissions( $fileId, $value, $role = 'writer', $type = 'user' ) {
		$perm = new Google_Permission();
		$perm->setValue( $value );
		$perm->setType( $type );
		$perm->setRole( $role );

		$this->_service->permissions->insert($fileId, $perm);
	}

	public function getFileIdByName( $name ) {
		$files = $this->_service->files->listFiles();
		foreach( $files['items'] as $item ) {
			if( $item['title'] == $name ) {
				return $item['id'];
			}
		}

		return false;
	}

}

if( $_SERVER['argc'] != 2 ) {
	echo "ERROR: no file selected\n";
	die();
}

$path = $_SERVER['argv'][1];

printf( "Uploading %s to Google Drive\n", $path );

$service = new DriveServiceHelper( CLIENT_ID, SERVICE_ACCOUNT_NAME, KEY_PATH );

$folderId = $service->getFileIdByName( BACKUP_FOLDER );

if( !$folderId ) {
	echo "Creating folder...\n";
	$folderId = $service->createFolder( BACKUP_FOLDER );
	$service->setPermissions( $folderId, SHARE_WITH_GOOGLE_EMAIL );
}

$fileParent = new Google_ParentReference();
$fileParent->setId( $folderId );

$fileId = $service->createFileFromPath( $path, $path, $fileParent );

printf( "File: %s created\n", $fileId );

$service->setPermissions( $fileId, SHARE_WITH_GOOGLE_EMAIL );