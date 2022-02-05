<?php
	//This is an example configuration file
	//=====================================
	//Please configure all values appropriately
	//and save it as "config.php"

	//This is the path and file name of ffmpeg or a compatible implementation
	define('FFMPEG','C:/Path/To/ffmpeg.exe');

	//This is the path and file name of youtube-dl or a compatible implementation
	define('YTDL','C:/Path/To/youtube-dl.exe');

	//You can set this to boolean FALSE to not use a cache at all.
	//This is not recommended but makes the system fully readonly.
	//Note: MP3 files are cached forever. SponsorBlock lifetime can be configured further below.
	//This has the advantage of keeping files available if the original video is deleted
	define('USE_CACHE',TRUE);
	//This is the cache directory. MP3 files and SponsorBlock ranges will be stored in subdirectories.
	//The directory must be writable.
	//Since the file names are predictable you may want to deny access to web users
	//or have the directory outside of the web server directory.
	//You do not need to change this if USE_CACHE is set to FALSE
	define('CACHE',__DIR__ . DIRECTORY_SEPARATOR . 'cache');

	//Set to FALSE if you don't want to use SponsorBlock
	//SponsorBlock is used to cut non-music sections from video files.
	define('SB_ENABLE',TRUE);

	//The API base of the sponsorblock API.
	//Do not change unless you run your own system
	define('SB_API','sponsor.ajay.app');

	//How many seconds SponsorBlock ranges should be kept in the cache.
	//They do not tend to change often, so leave this as a large value
	define('SB_CACHE_LIFETIME',86400 * 7);

	//If enabled some messages will be written to debug.log
	define('DEBUG',TRUE);
