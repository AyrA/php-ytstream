<?php
	function getSegFile($id){
		return CACHE . "/segments/$id.json";
	}

	function getMp3file($id){
		return CACHE . "/mp3/$id.mp3";
	}
	
	function getSegCache($id){
		$f=getSegFile($id);
		if(is_file($f) && time()-filemtime($f)<SB_CACHE_LIFETIME){
			return @file_get_contents($f);
		}
		return FALSE;
	}
	
	function setSegCache($id,$data){
		return @file_put_contents(getSegFile($id),json_encode($data));
	}
	
	function initCache(){
		//Create folders as needed
		@mkdir($cachemp3=CACHE . DIRECTORY_SEPARATOR . 'mp3');
		@mkdir($cacheseg=CACHE . DIRECTORY_SEPARATOR . 'segments');

		//Sanity checks
		is_dir($cachemp3) or http500("$cachemp3 does not exist and could not be created");
		is_dir($cacheseg) or http500("$cacheseg does not exist and could not be created");
	}