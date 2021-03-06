<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2014, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license.
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2014 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\Backoffice\Controller;

use Rubedo\Services\Manager;
use WebTales\MongoFilters\Filter;
use Zend\Debug\Debug;
use Zend\Json\Json;
use Zend\View\Model\JsonModel;


/**
 * Controller providing zipped dump file from rubedo collections
 *
 * @author dfanchon
 * @category Rubedo
 * @package Rubedo
 *
 */
class DumpController extends DataAccessController
{

	private $_collections = [
		'Blocks',
		'ContentTypes',
		'Contents',
		'CustomThemes',
		'Dam',
		'DamTypes',
		'Directories',
		'Files',
		'Groups',
		'Languages',
		'Masks',
		'Pages',
		'Queries',
		'ReusableElements',
		'Shippers',
		'Sites',
		'Taxes',
		'Taxonomy',
		'TaxonomyTerms',
		'Themes',
		'UserTypes',
		'Users',
		'Workspaces'
	];
	
	private $_files = array();
	
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * The default read Action
     *
     * Return the content of the collection, get filters from the request
     * params, get sort from request params
     */
    public function indexAction()
    {
        set_time_limit(0);

        $fs=Manager::getService("FSManager")->getFS();
    	
    	$collections = $this->params()->fromQuery('collection',['all']);

    	if ($collections==['all']) {
    		$collections = $this->_collections;
    	}

    	foreach($collections as $collection) {
    		
	    	$this->_dataService = Manager::getService('MongoDataAccess');
	    	$this->_dataService->init($collection);
	    	$response = array();
	    	$response[$collection] = $this->_dataService->read();
	    	
	    	$fileName = $collection.'.json';
	    	$filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
	    	$fp = fopen($filePath, 'w');
	    	fwrite($fp, json_encode($response));
	    	fclose($fp);
	    	$this->_files[] = $filePath;
	    	
	    	if ($collection=='Dam') { // Export binary files related to Dam
	    		foreach ($response[$collection]['data'] as $dam) {
                    if($fs->has($dam['originalFileId'])){
                        $damFileName = $dam['originalFileId'];
                        $stream = $fs->readStream($dam['originalFileId']);
                        $damPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $damFileName;
                        $fp = fopen($damPath, 'w+');
                        while (!feof($stream)) {
                            fwrite($fp, fread($stream, 8192));
                        }
                        $this->_files[] = $damPath;
                        fclose($fp);
                    }

	    		}
	    	}
	    	
	    	if ($collection=='Users') { // Export photo files related to Users
	    		foreach ($response[$collection]['data'] as $user) {
	    			if (isset($user['photo']) && $user['photo']!='') {
                        if($fs->has($user['photo'])){
                            $damFileName = $user['photo'];
                            $stream = $fs->readStream($user['photo']);
                            $damPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $damFileName;
                            $fp = fopen($damPath, 'w+');
                            while (!feof($stream)) {
                                fwrite($fp, fread($stream, 8192));
                            }
                            $this->_files[] = $damPath;
                            fclose($fp);
                        }

	    			}
	    		}	    		
	    	}
    	}

    	$zip = new \ZipArchive();
    	$zipFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "rubedo.zip";
    	unlink($zipFileName);
    	$archive = $zip->open($zipFileName,\ZipArchive::CREATE);

    	if ($archive === TRUE) {

	    	foreach ($this->_files as $file) {
	    		$zip->addFile($file, basename($file));
	    	}
	    	$zip->close();

            $zipFileSize = filesize($zipFileName);

            $zipFileStream = fopen($zipFileName, "rb");

            header('Content-Type:application/zip');
            header('Content-Disposition:attachment; filename="rubedo.zip"');
            header('Content-Length:'.$zipFileSize);
            header('Cache-Control:private');

            while(!feof($zipFileStream)) {
                echo fread($zipFileStream, 1*(1024*1024));
                ob_flush();
                flush();
            }
    	}

        return $this->getResponse();
    }



}
