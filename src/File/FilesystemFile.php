<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\File;

use vxPHP\File\MimeTypeGetter;
use vxPHP\File\Exception\FilesystemFileException;
use vxPHP\Observer\PublisherInterface;

/**
 * mapper for filesystem files
 *
 * @author Gregor Kofler
 *
 * @version 0.6.0 2017-02-04
 */

class FilesystemFile implements PublisherInterface {

	protected static $instances = array();

	protected	$filename,
				$folder,
				$mimetype,

				/**
				 * @var \SplFileInfo
				 */
				$fileInfo;

	/**
	 * @param string $path
	 * @return FilesystemFile;
	 */
	public static function getInstance($path) {

		if(!isset(self::$instances[$path])) {
			self::$instances[$path] = new self($path);
		}
		return self::$instances[$path];

	}

	public static function unsetInstance($path) {

		if(isset(self::$instances[$path])) {
			unset(self::$instances[$path]);
		}

	}

	/**
	 * constructs mapper for filesystem files
	 * if folder is provided a bulk generation is assumed and certain checks are omitted
	 *
	 * @param string $path
	 * @param FilesystemFolder $folder
	 *
	 * @throws FilesystemFileException
	 */
	public function __construct($path, FilesystemFolder $folder = NULL) {

		if($folder) {
			$path = $folder->getPath() . $path;
		}
		else {
			$path = realpath($path);
		}

		if(!file_exists($path)) {
			throw new FilesystemFileException("File $path does not exist!", FilesystemFileException::FILE_DOES_NOT_EXIST);
		}

		$this->folder	= $folder ? $folder : FilesystemFolder::getInstance(pathinfo($path, PATHINFO_DIRNAME));
		$this->filename	= pathinfo($path, PATHINFO_BASENAME);
		$this->fileInfo	= new \SplFileInfo($path);
	}

	/**
	 * retrieve file information provided by SplFileInfo object
	 */
	public function getFileInfo() {

		return $this->fileInfo;

	}

	/**
	 * retrieve mime type
	 * requires MimeTypeGetter
	 *
	 * @param bool $force forces re-read of mime type
	 */
	public function getMimetype($force = false) {

		if(!isset($this->mimetype) || $force) {
			$this->mimetype = MimeTypeGetter::get($this->folder->getPath() . $this->filename);
		}
		return $this->mimetype;

	}

	/**
	 * check whether mime type indicates web image
	 * (i.e. image/jpeg, image/gif, image/png)
	 *
	 * @param bool $force forces re-read of mime type
	 */
	public function isWebImage($force = false) {

		if(!isset($this->mimetype) || $force) {
			$this->mimetype = MimeTypeGetter::get($this->folder->getPath() . $this->filename);
		}
		return preg_match('~^image/(p?jpeg|png|gif)$~', $this->mimetype);

	}

	/**
	 * retrieve filename
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * retrieves physical path of file
	 */
	public function getPath() {
		return $this->folder->getPath() . $this->filename;
	}

	/**
	 * returns path relative to assets path root
	 *
	 * @param boolean $force
	 * @return string
	 */
	public function getRelativePath($force = FALSE) {

		if(!is_null($this->folder->getRelativePath())) {
			return $this->folder->getRelativePath() . $this->filename;
		}

	}

	/**
	 * return filesystem folder of file
	 */
	public function getFolder() {

		return $this->folder;

	}

	/**
	 * rename file
	 *
	 * @param string $to new filename
	 * @return \vxPHP\File\FilesystemFile
	 * @throws FilesystemFileException
	 */
	public function rename($to) {

		$from		= $this->filename;

		// name is unchanged, nothing to do

		if($from !== $to) {

			$oldpath	= $this->folder->getPath() . $from;
			$newpath	= $this->folder->getPath() . $to;
	
	
			if(file_exists($newpath)) {
				throw new FilesystemFileException("Rename from '$oldpath' to '$newpath' failed. '$newpath' already exists.", FilesystemFileException::FILE_RENAME_FAILED);
			}
	
			if(@rename($oldpath, $newpath)) {

				$this->renameCacheEntries($to);

				// set new filename

				$this->filename = $to;

				// re-read fileinfo
				
				$this->fileInfo	= new \SplFileInfo($newpath);

				self::$instances[$newpath] = $this;
				unset(self::$instances[$oldpath]);
			}
	
			else {
				throw new FilesystemFileException(sprintf("Rename from '%s' to '%s' failed.", $oldpath, $newpath), FilesystemFileException::FILE_RENAME_FAILED);
			}

		}
		
		return $this;

	}

	/**
	 * move file into new folder,
	 * orphaned cache entries are deleted, new cache entries are not generated
	 *
	 * @param FilesystemFolder $destination
	 * @return \vxPHP\File\FilesystemFile
	 * @throws FilesystemFileException
	 */
	public function move(FilesystemFolder $destination) {

		// already in destination folder, nothing to do

		if($destination !== $this->folder) {

			$oldpath	= $this->folder->getPath() . $this->filename;
			$newpath	= $destination->getPath() . $this->filename;
	
			if(@rename($oldpath, $newpath)) {

				$this->clearCacheEntries();
				
				// set new folder reference

				$this->folder = $destination;
	
				// re-read fileinfo

				$this->fileInfo	= new \SplFileInfo($newpath);

				self::$instances[$newpath] = $this;
				unset(self::$instances[$oldpath]);

				// @todo: check necessity of chmod
	
				@chmod($newpath, 0666 & ~umask());
				
			}
	
			else {
				throw new FilesystemFileException("Moving from '$oldpath' to '$newpath' failed.", FilesystemFileException::FILE_RENAME_FAILED);
			}

		}

		return $this;
	}

	/**
	 * updates names of cache entries
	 *
 	 * @param string $to new filename
	 */
	protected function renameCacheEntries($to) {

		if(($cachePath = $this->folder->getCachePath(TRUE))) {

			$di	= new \DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {

				$filename = $fileinfo->getFilename();

				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($filename, $this->filename) !== 0
				) {
					continue;
				}

				$renamed = substr_replace($filename, $to, 0, strlen($this->filename));
				rename($fileinfo->getRealPath(), $fileinfo->getPath() . DIRECTORY_SEPARATOR . $renamed);
			}
		}
	}

	/**
	 * deletes file and removes instance from lookup array
	 * @throws FilesystemFileException
	 */
	public function delete() {

		if(@unlink($this->getPath())) {
			$this->deleteCacheEntries();
			self::unsetInstance($this->getPath());
		}
		else {
			throw new FilesystemFileException("Delete of file '{$this->getPath()}' failed.", FilesystemFileException::FILE_DELETE_FAILED);
		}

	}

	/**
	 * cleans up cache entries associated with
	 * "original" file
	 */
	protected function deleteCacheEntries() {

		if(($cachePath = $this->folder->getCachePath(TRUE))) {

			$di	= new \DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {
				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($fileinfo->getFilename(), $this->filename) !== 0
				) {
					continue;
				}

				unlink($fileinfo->getRealPath());
			}
		}
	}

	/**
	 * remove all cache entries of file
	 */
	public function clearCacheEntries() {

		$this->deleteCacheEntries();

	}

	/**
	 * retrieve information about cached files
	 * @return array information
	 */
	public function getCacheInfo() {

		if(($cachePath = $this->folder->getCachePath(TRUE))) {
			$size	= 0;
			$count	= 0;

			$di	= new \DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {
				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($fileinfo->getFilename(), $this->filename) !== 0
				) {
					continue;
				}
				++$count;
				$size += $fileinfo->getSize();
			}
			return ['count' => $count, 'totalSize' => $size];
		}
		return FALSE;
	}

	/**
	 * return all filesystem files instances within a certain folder
	 *
	 * @param FilesystemFolder $folder
	 * @return Array filesystem files
	 */
	public static function getFilesystemFilesInFolder(FilesystemFolder $folder) {

		$files = [];

		$glob = glob($folder->getPath() . '*', GLOB_NOSORT);

		if($glob !== FALSE) {

			foreach($glob as $f) {
				if(!is_dir($f)) {

					if(!isset(self::$instances[$f])) {
						self::$instances[$f] = new self(basename($f), $folder);
					}

					$files[] = self::$instances[$f];
				}
			}
		}

		return $files;
	}

	/**
	 * clean up $filename and avoid duplicate filenames within folder $dir
	 * the cleanup is simple and does not take reserved filenames into consideration
	 * (e.g. PRN or CON on Windows systems)
	 * @see https://msdn.microsoft.com/en-us/library/aa365247
	 *
	 * @param string $filename
	 * @param FilesystemFolder $dir
	 * @param integer $starting_index used in renamed file
	 * @return string
	 */
	public static function sanitizeFilename($filename, FilesystemFolder $dir, $ndx = 2) {

		// remove any characters which are not allowed in any file system

		$filename = preg_replace('~[<>:"/\\|?*\\x00-\\x1F]~', '_', $filename);

		if(!file_exists($dir->getPath() . $filename)) {
			return $filename;
		}

		$pathinfo = pathinfo($filename);

		$pathinfo['extension'] = !empty($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

		while(file_exists($dir->getPath() . sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']))) {
			++$ndx;
		}

		return sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']);

	}
}