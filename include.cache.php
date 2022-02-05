<?php
	//Gets the file name and path that holds SBlock data
	function getSegFile($id){
		return CACHE . "/segments/$id.json";
	}

	//Gets the file name and path that holds the MP3 contents
	function getMp3file($id){
		return CACHE . "/mp3/$id.mp3";
	}

	//Gets cached SBlock segment data for the given id
	function getSegCache($id){
		$f=getSegFile($id);
		if(USE_CACHE && is_file($f) && time()-filemtime($f)<SB_CACHE_LIFETIME){
			return @file_get_contents($f);
		}
		return FALSE;
	}

	//Sets cached SBlock segment data for the given id
	function setSegCache($id,$data){
		return USE_CACHE && @file_put_contents(getSegFile($id),json_encode($data));
	}

	//Verifies configured cache path and creates subdirectories as needed
	function initCache(){
		if(!USE_CACHE){return;}
		//Create folders as needed
		@mkdir($cachemp3=CACHE . DIRECTORY_SEPARATOR . 'mp3');
		@mkdir($cacheseg=CACHE . DIRECTORY_SEPARATOR . 'segments');

		//Sanity checks
		is_dir($cachemp3) or http500("$cachemp3 does not exist and could not be created");
		is_dir($cacheseg) or http500("$cacheseg does not exist and could not be created");
	}