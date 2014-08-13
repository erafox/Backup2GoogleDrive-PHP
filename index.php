<?php

define( 'BACKUP_FOLDER', 'PHPBackups1' );
define( 'SHARE_WITH_GOOGLE_EMAIL', 'youremail@gmail.com' );

define( 'CLIENT_ID',  '' );
define( 'SERVICE_ACCOUNT_NAME', '' );
define( 'KEY_PATH', dirname(__FILE__).'/'.'');

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

	public function createFile( $name, $mime, $description, $path, Google_ParentReference $fileParent = null ) {
		$chunkSizeBytes = 512 * 1024 * 1024;

		$file = new Google_DriveFile();
		$file->setTitle( $name );
		$file->setDescription( $description );
		$file->setMimeType( $mime );

		if( $fileParent ) {
			$file->setParents( array( $fileParent ) );
		}
		//here i dont using file_get_contents because i had problem when upload large file.
		$media = new Google_MediaFileUpload($mime, null, true, $chunkSizeBytes);
		$media->setFileSize(filesize($path));
		
		$createdFile = $this->_service->files->insert($file, array('mediaUpload' => $media));
		
		$status = false;
	  	$handle = fopen($path, "rb");
	  	while (!$status && !feof($handle)) {
	    	$chunk = fread($handle, $chunkSizeBytes);
	    	$uploadStatus = $media->nextChunk($createdFile, $chunk);
	  	}
		//return $createdFile['id']; #error Google_HttpRequest is here, i'm trying fix it
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
