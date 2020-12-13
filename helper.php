<?php

/**
 * Retrieves the CSS indentation class to use for a comment card
 * @staticvar array $commentCatalog Internal catalog of generated comment indent classes
 * @param Comment $comment The comment whose indent class will be retrieved
 * @return string Returns the name of the CSS indent class
 */
function getCommentIndentClass(Comment $comment)
{
    static $commentCatalog = array();
    
    // Determine which indent class to provide
    if ($comment->parentId == 0) {
        $indentClass = '';
    } else {
        $parentIndentClass = $commentCatalog[$comment->parentId];
        if ($parentIndentClass == '') {
            $indentClass = 'commentIndent';
        } else if ($parentIndentClass == 'commentIndent') {
            $indentClass = 'commentIndentDouble';
        } else {
            $indentClass = 'commentIndentTriple';
        }
    }
        
    // Update comment catalog and return indent class
    $commentCatalog[$comment->commentId] = $indentClass;
    return $indentClass;
}

/**
 * Builds full URL to a user's profile
 * @param User $user User whose profile URL will be generated for
 * @return string Returns the URL to user's profile 
 */
function getUserProfileLink(User $user)
{
    return BASE_URL . '/members/' . $user->username;
}

/**
 * Retrieves full URL to an image to be used as the given playlist's thumbnail
 * @param Playlist $playlist The playlist to retrieve thumbnail image for
 * @return string Returns URL to the thumbnail for a playlist's card 
 */
function getPlaylistThumbnail(Playlist $playlist)
{
    $config = Registry::get('config');
    $videoMapper = new VideoMapper();
    $video = $videoMapper->getVideoById($playlist->entries[0]->videoId);
    return getVideoThumbUrl($video);
}

/**
 * Retrieves full URL to an image to be used as the given video thumbnail
 * @param Video $video The video to retrieve thumbnail image for
 * @return string Returns URL to the thumbnail for a video
 */
function getVideoThumbUrl(Video $video)
{
    $config = Registry::get('config');
    $extension = (isAudio($video)) ? ".png" : ".jpg";
    $url = $config->thumbUrl . '/' . $video->filename . $extension;

    if( class_exists('CustomThumbs') ){
        // Handle a possibly attached thumb
        $url = CustomThumbs::thumb_url($video->videoId);
        if (isAudio($video)) {
            $url = str_replace('.jpg','.png',$url);
        }
    }
    else {
	// Use the default ffmpeg generated thumb
        if (class_exists('Wowza')) {
            $thumb_dir = Wowza::get_url_by_video_id($video->videoId, 'thumbs');
            $url = $thumb_dir . $video->filename . $extension;
        }
    }

    return $url;
}

/**
 * Get url for the specified user's avatar.
 * @return null if no avatar was found for this user.
 */
function getAvatar($user)
{
	if (class_exists('Wowza')) {
		if (!empty($user->avatar)) {
			$url = Wowza::get_url_by_user_id($user->userId, 'avatars');	
			$avatar = $url . $user->avatar; 
		} else {
			$avatar = null; 
		} 
	}
	else {
    $userService = new UserService();
		$avatar = $userService->getAvatarUrl($user);
	}
	return $avatar;
}

/**
 * Check for JWPlayer support
 * @return bool True if jwplayer is set in plugin settings
 */
function useJWPlayer()
{
    if (class_exists('AdminPlus')) {
        $enabled = Settings::get('adminplus_jwplayer_enabled');
        if ($enabled) {
            return true;
        }
    }
    return false;
}

/**
 * Add history/plays to the default pageview counts
 * 
 */
function countViewsAndPlays($video)
{
    $plays = 0;
    if (class_exists('Stats')) {
      $total = Stats::countPlays($video);
    }
    else{
      $total = (int) $video->views;
    }
    return $total;
}

/**
 * Return default flag for AdminPlus configuration setting. 
 * @return bool True if videos are set to this by default in plugin settings.
 */
function isSetByDefault($field)
{
   // If the form has been posted, preserve the selected state 
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST[$field])) {
            return false;
        }
        if (isset($_POST[$field])) {
            return true;
        }
    }


    $setting = 'adminplus_' . $field . '_default';
    if (class_exists('AdminPlus')) {
        $set = Settings::get($setting);
        if ($set) {
            return true;
        }
    }
    return false; 
}

/**
 * Generates SMIL source elements
 * @param Video $video The video object to retrieve the video URL for
 * @param bool $json toggle for return of json or html element format 
 * @return string HTML elements for theSMIL urls.
 */
function getSmilSources(Video $video, $json=false)
{
    if( class_exists( 'Wowza' ) )
    {
        $url = Wowza::getSmilUrl($video);
        $mpd = $url . "manifest.mpd";
        $hls = $url . "playlist.m3u8";
        if ($json) {
            $source = '{file: "' . $mpd . '"},';
            $source .= '{file: "' . $hls . '"}';
        } else {
            $source = '<source src="' . $mpd . '" type="application/dash+xml">';
            $source .= '<source src="' . $hls . '" type="application/x-mpegURL">';
        }
    }
    else {
        $url = getMediaUrl($video);
        if ($json) {
            $source = '{file: "' . $url . '"}'; 
        } else {
            $source = '<source src="' . $url . '" type="video/mp4">';
        }
    }
    return $source;
}

/**
 * Generates caption track elements/json for loading into player
 * @param Video $video The video object to retrieve the video URL for
 * @param bool $json toggle for return of json or html element format 
 * @return string HTML elements or JSON for caption tracks.
 */
function getCaptionTracks(Video $video, $json=false)
{
    $fileService = new FileService();
    $videoId = $video->videoId;
    $tracks = "";
    if (class_exists('AttachCaptions')) 
    {
        $captions = AttachCaptions::get_all_captions($videoId);
        $video_meta = AttachCaptions::get_video_meta($videoId, 'default_caption');
        if ($video_meta) {
            $defaultCaption = $video_meta->meta_value;
        } else {
            $defaultCaption = 0;
        }

        foreach ($captions as $caption) {
            $dir = $url = "";
            $default = ($defaultCaption == $caption->fileId) ? true : false;
            $language = AttachCaptions::get_caption_language($caption->fileId);
            if (class_exists('Wowza')) 
            {
                    $dir = Wowza::get_url_by_video_id($videoId, 'attachments');
                    $url = $dir . $caption->filename . '.' . $caption->extension;
            }
            else 
            {
                $url = $fileService->getUrl($caption);
            }                
            $tracks .= buildCaptionTrack($url, $language, $default, $json);
        }
    }

    $tracks .= buildLocalCaptionTracks($video);
    
    return $tracks;
}
/**
 * Builds caption track for loading via JSON or HTML5  
 * @param string URL to the caption 
 * @param string language to use for caption label 
 * @param bool indicate if this is the default caption in the list
 * @param bool format to return
 * @return string JSON/HTML5 track
 */
function buildCaptionTrack($url, $language, $default, $json=true)
{
    $track = $languageLabel = $defaultLabel = "";
    $languages = AttachCaptions::language_list();
    $languageLabel = $languages[$language];
    if ($json) {
        $defaultLabel = ($default) ? ', "default": true' : "";
        $track = '{"kind": "caption", "file": "' . $url . '", "label": "' . $languageLabel . '"' . $defaultLabel . '},';
    } else {
        $defaultLabel = ($default) ? ' default' : "";
        $track = '<track label="' . $languageLabel . '" kind="subtitles" srclang="' . $language . '" src="' . $url . '" ' . $defaultLabel . '>';
    }
    return $track;
}
/**
 * Builds caption tracks for videos whose caption files have been stored alongside the .mp4 assets 
 * @param Video $video The video object the captions are attached to
 * @return string HTML/JSON for track listings 
 */
function buildLocalCaptionTracks(Video $video, $json=true)
{
    // Set label for captions that don't have a language attached
    $defaultLanguage = Settings::get('default_language');
    $default = true;
    $tracks = $urlPath = "";
    if (class_exists('Wowza')) {
        // Build local paths to look for existing caption files.
        $h264Path = Settings::get('wowza_upload_dir') . Wowza::get_video_owner_homedir($video->videoId) . '/h264/'; 
        $srtFile = $h264Path . $video->filename . '.srt';
        $vttFile = $h264Path . $video->filename . '.vtt';
        $urlPath = Wowza::get_url_by_video_id($video->videoId, 'h264');
        if (file_exists($srtFile)) {
            $srtUrl = $urlPath . $video->filename . '.srt';
            $tracks = buildCaptionTrack($srtUrl, $defaultLanguage, $default, $json);
        }
        if (file_exists($vttFile)) {
            $vttUrl = $urlPath . $video->filename . '.vtt';
            $tracks .= buildCaptionTrack($vttUrl, $defaultLanguage, $default, $json);
        }
    }
    return $tracks;
}
/**
 * Retrieves full URL to a video asset 
 * @param Video $video The video object to retrieve the video URL for
 * @return string URL to the a video
 */
function getMediaUrl(Video $video)
{
    $config = Registry::get('config');
    $url = $config->h264Url . "/" . $video->filename . ".mp4";
    $isMp3 = ($video->originalExtension == ".mp3") ? true : false;
    if( isAudio($video) && $isMp3 ) 
    {
        $url = $config->mp3Url . "/" . $video->filename . ".mp3";
    }	
    if( class_exists( 'Wowza' ) ){
        if( isAudio($video) && $isMp3 ) {
            $dir = Wowza::get_url_by_video_id($video->videoId, 'mp3');
            $url = $dir . $video->filename . ".mp3";
        } else {
            $dir = Wowza::get_url_by_video_id($video->videoId, 'h264');
            $url = $dir . $video->filename . ".mp4";
        }
    }
    return $url;
	
}
/**
 * Retrieves full URL to an attachment asset 
 * @param Attachment $attachment The attachment object to retrieve the URL for
 * @return string URL to the attachment 
 */
function getAttachmentUrl(File $file)
{
	$url = "";
	if (class_exists('Wowza')) {
		$base_url = Wowza::get_url_by_user_id($file->userId, 'attachments');
		$url = $base_url . $file->filename . '.' . $file->extension;
	}
	else {
		$fileService = new FileService();
		$url = $fileService->getUrl($attachment);
	}
	return $url;
}

/**
 * Check for existence of SMIL file for this video.
 * @param Video $video The video object to inspect.
 * @return bool True if SMIL file exists.
 */
function smilFileExists(Video $video) 
{
	if( class_exists( 'Wowza' ) )
	{
		$dir = Wowza::get_video_owner_homedir($video->videoId);
		$exists = file_exists($dir . "/" . $video->filename . ".smil");
		return $exists;
	}	
	return false;
}

/**
 * Checks to see if this is an audio file and that audio is allowed.
 * @param Video $video The video object to inspect.
 * @return bool True if audio's allowed and it's an audio file.
 */
function isAudio(Video $video) 
{
    if( class_exists( 'EnableAudio' ) ) {
        $audioFormats = json_decode(Settings::get('enable_audio_formats'));
        if( in_array($video->originalExtension, $audioFormats) ) {
            return true;
        }
    }
    return false;
}

function watchLaterButton($video, $loggedInUser, $linkText = '')
{

    if($loggedInUser) {
        $playlistService = new PlaylistService();
        $watchLater = $playlistService->getUserSpecialPlaylist($loggedInUser, \PlaylistMapper::TYPE_WATCH_LATER);

    if (isInPlaylist($video, $watchLater)) {
        $dataAction = 'remove';
        $iconClass = ' fas';
        $alt = 'In playlist.';
    } else {
        $dataAction = 'add';
        $iconClass = ' far';
        $alt = 'Not in playlist.';
    }

    $button = <<<BUTTON
<a data-playlist_id="$watchLater->playlistId" data-video_id="$video->videoId" data-action="$dataAction" data-toggle="tooltip" title="Watch Later" class="btn btn-sm btn-outline-success addToPlaylist watch-later-mini" role="button" href="#"><i class="playlist-icon fa-bookmark $iconClass" alt="$alt"></i>$linkText</a>
BUTTON;

    return $button;
    }
}

/**
 * Check to see if this rating was already made by this user.
 * @param object $video Video object
 * @param object $loggedInUser User object for this user
 * @return bool false if it hasn't been rated.
 *
 **/
function isRated($video, $loggedInUser) {
    $liked = false;
    $ratingMapper = new RatingMapper();
    $userId = (isset($loggedInUser->userId)) ? $loggedInUser->userId : 0;
    $usersLikes = $ratingMapper->getRatingByCustom(array('video_id' => $video->videoId, 'user_id' => $userId, 'rating' => 1));
    if($usersLikes) {
	    if (count(get_object_vars($usersLikes))) {
		    $liked = true;
	    }
    }
    return $liked;
}

/**
 * Output a custom video card block to the browser
 * @param string $viewFile Name of the block to be output
 * @return mixed Block is output to browser
 *
 **/
function videoCardBlock($viewFile, $video)
{
    // Detect correct block path
    $request_block = $this->getFallbackPath("blocks/$viewFile");
    $block = ($request_block) ? $request_block : $viewFile;
    extract(get_object_vars($this->vars));
    include($block);
}

/**
* Set url for temp directory in meta, to allow thumb display on upload.
*
*/
function getTempDirUrl($user) {
    if (class_exists('Wowza')) {
        $tempDir = Wowza::get_url_by_user_id($user->userId, 'temp');
    } else {
        $tempDir = BASE_URL . '/cc-content/uploads/temp/';
    }
    return $tempDir;
}

/**
 * Set message type class to use bootstrap alert styles
 * @param string $message_type status message passed from controller
 * @return string bootstrap message type
 *
 **/
function setMessageType($message_type)
{
	if ( $message_type == 'errors' ){
		$message_type = 'alert-danger';
	}
	if ( $message_type == 'success' ){
		$message_type = 'alert-success';
	}
	return $message_type;
}

/**
 * Display alert messaging.
 * @param string $message_type
 * @param string $message
 *
 **/
function showAlertMessage($message, $message_type)
{
	$message_type = setMessageType($message_type);
	echo '<div class="alert message ' . $message_type . '" role="alert">' . $message . '</div>';
}
/**
 * Show attachment list item
 * @param array $fileInfo array containing name, size and temp path.
 * @return string html for the attachment item
 *
 **/
function attachmentItem($fileInfo, $attachmentCount, $isNew)
{
	if($isNew) {
		$file = "temp";
	}
	else {
		$file = "file";
	}

	$attachedItem = '   <input type="hidden" name="attachment[' . $attachmentCount . '][name]" value="' . $fileInfo['name'] . '" />
                        <input type="hidden" name="attachment[' . $attachmentCount . '][size]" value="' . $fileInfo['size'] . '" />
                        <input type="hidden" name="attachment[' . $attachmentCount . '][' . $file . ']" value="' . $fileInfo[$file] . '" />
                        ';

	return $attachedItem;
}
/**
 * Show attachment image/icon
 * @param mixed $fileId id of the file attachment, or temp file path
 * @return string HTML DOM element
 *
 **/
function attachmentIcon($fileId)
{
    $config = Registry::get('config');

    $fileMapper = new FileMapper();
    $file = $fileMapper->getById($fileId);

    if (!is_file($fileId)) {
        $ext = $file->extension;
    } 
    else {
        $ext = pathinfo($fileId, PATHINFO_EXTENSION);
        $fileName = pathinfo($fileId, PATHINFO_BASENAME);
    }

    $img = '<i class="playlist-mini-thumb pt-3 fas fa-file-alt"></i>';
    // Check if the extension matches an image type 
    if (in_array($ext, $config->acceptedImageFormats)) {
        if ($file) {
	    if (class_exists('Wowza'))
	    {
		    $thumb_dir = Wowza::get_url_by_user_id($file->userId, 'attachments');
		    $image_url = $thumb_dir . $file->filename . '.' . $ext;
	    }
	    else 
	    {
		    $fileService = new FileService();
		    $image_url = $fileService->getURL($file);
	    }
        }
        else {
	    $authService = new AuthService();
	    $loggedInUser = $authService->getAuthUser();
            $tempDir = getTempDirUrl($loggedInUser);
            $image_url = $tempDir . $fileName;
        }
        $img = '<img class="playlist-mini-thumb" src="' . $image_url. '" alt="">';
    }
    if (class_exists('AttachCaptions')) {
        if (in_array($ext, $config->acceptedCaptionFormats)) {
            $img = '<i class="playlist-mini-thumb pt-3 fas fa-closed-captioning"></i>';
        }
    }
    return $img;

} 
/**
 * Get video processing/approval status
 * @param string $status Flag for status state of the video.
 * @return bool 
 *
 **/
function isVideoPending($status)
{
	if (in_array($status, array(VideoMapper::PENDING_CONVERSION, 'processing', VideoMapper::PENDING_APPROVAL))){
		return true;
	}
	else return false;
}


function isInPlaylist($video, $playlist)
{
	$playlistService = new PlaylistService();
	$playlistMapper = new PlaylistMapper();
	return $playlistService->checkListing($video, $playlistMapper->getPlaylistById($playlist->playlistId));
}

function durationInWords($duration)
{
    if(substr_count($duration, ":") == 1) {
        sscanf($duration, "%d:%d", $minutes, $seconds);
        return $minutes . " Minutes, "  . $seconds . " Seconds";
    } else {
        sscanf($duration, "%d:%d:%d", $hours, $minutes, $seconds);
        return $hours . " Hours, " . $minutes . " Minutes, "  . $seconds . " Seconds";
    }


}

/**
 * Get popular videos based on "views"
 * @return array of video objects
 *
 **/
function getPopularVideos()
{
  $query = "SELECT video_id FROM " . DB_PREFIX . "videos WHERE status = 'approved' AND private = '0' ORDER BY views DESC limit 6";
  $db = Registry::get('db');
  $result = $db->fetchAll($query);

  $videoMapper = new VideoMapper();
  $popular = $videoMapper->getVideosFromList(Functions::arrayColumn($result, 'video_id'));
  return $popular;
}

