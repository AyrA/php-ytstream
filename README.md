# PHP Youtube Stream Library

php-ytstream: A PHP application that transcodes the audio of youtube videos for webradio devices.
Requires only very minimal setup

## Setup

1. Put all PHP files into a directory on your webserver
2. Rename `config.example.php` into `config.php`
3. Edit `config.php` to match your setup
4. Access `index.php?id=dQw4w9WgXcQ` to test your installation

## Dependencies

- [youtube-dl](https://youtube-dl.org/)
- [ffmpeg](https://ffmpeg.org/)

Please install these dependencies and properly configure their paths in your configuration file.

## SponsorBlock

This application (provided you enable it) makes use of SponsorBlock (SBlock)
to cut non-music sections from music videos.

SBlock operates on a custom license that reauires attribution.
See here for details:

https://github.com/ajayyy/SponsorBlock/wiki/Database-and-API-License

### License notes

The license no logner apply to you if you:

- set `SB_ENABLE` to `FALSE`
- set `SB_API` to a server with a different license
- Do not publish php-ytstream

Note that php-ytstream uses the AGPL which considers SaaS/hosting a form of publishing

## Limitations

Because this is written in PHP, it has a few limitations.
Most notably, transcoding aborts if a client closes the connection.
This means that you will end up with a partial MP3 file in the cache.

You can disable the cache if you have unreliable clients connecting.
This has the implication that it takes a few seconds for the stream to start
because it takes time to obtain the stream URL.

## Quality

The MP3 streaming quality is 192 kbps using 44.1 kHz Joint Stereo.
There's not really any reason to increase this since youtube audio is already compressed.

## Configuration

The following `config.php` configuration values are known:

*Unless otherwise stated, ythey're mandatory. Use the example file to get started.*

## FFMPEG

The Path and file name to the ffmpeg executable

- Type: string
- Default: No default

## YTDL

The Path and file name to the youtube-dl executable

- Type: string
- Default: No default

## USE_CACHE

Enable or disable the MP3 and SBlock cache

- Type: bool
- Default: TRUE

## CACHE

Base cache directory

- Type: string
- Default: "cache" in the directory where index.php is.

## SB_ENABLE

Enable or disable the use of SBlock to remove non-music sections

- Type: bool
- Default: TRUE

## SB_API

Set the SBlock API server address

- Type: string
- Default: sponsor.ajay.app

## SB_CACHE_LIFETIME

How long to keep successful SBlock answers in the cache.
Note: The API will always be queried if no ranges were found.

- Type: number
- Default: 604800 (7 days)

## DEBUG

Enables debug logging to `debug.log` (same directory where index.php is located in)

- Type: bool
- Default: FALSE
