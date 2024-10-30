<?php
/*
Plugin Name: IImage Gallery
Version: 1.9
Plugin URI: http://fredfred.net/skriker/
Description: Simple but powerful plugin for creating image galleries.
Author: Martin Chlupáč
Author URI: http://fredfred.net/skriker/
Update: http://fredfred.net/skriker/plugin-update.php?p=116
*/


/*
IImage Gallery
Copyright (C) 2004 Martin Chlupac


This program is free software; you can redistribute it and/or 
modify it under the terms of the GNU General Public License as 
published by the Free Software Foundation; either version 2 of the 
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but 
WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU 
General Public License for more details.

You should have received a copy of the GNU General Public License 
along with this program; if not, write to the Free Software 
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 
USA
*/
$idpost_safe = $_REQUEST['idpost'];
$idg_safe = $_REQUEST['idg'];
$idi_safe = $_REQUEST['idi'];

$ig_sa = false; //true if stand-alone mode detected

if(is_numeric($idpost_safe) && is_numeric($idg_safe) && is_numeric($idi_safe)){//for stand-alone mode
	$ig_sa = true;
}
elseif(preg_match('/iimage-gallery.php\/([0-9]+)\/([0-9]+)\/([0-9]+)\/?/i',$_SERVER['REQUEST_URI'],$matches)){
						$idpost_safe = $matches[1];
						$idg_safe = $matches[2];
						$idi_safe = $matches[3];
						$ig_sa = true;
}

if($ig_sa)	{require_once('./../../wp-config.php');}
//====================EDIT HERE=================
$ig_settings['ig_before'] = '<div class="gallery">';//will be placed before every gallery
$ig_settings['ig_after'] = '</div>';//will be placed after every gallery


$ig_settings['ig_default_stand_alone'] = true; //use stand-alone browser or not?

/*in next patters can be used: 
%src - for path to original image
%tsrc - for path to thumbnail
%title - title of image
%alt - alt of image
%sasrc - path to stand-alone browser for current gallery and image
*/
$ig_settings['ig_before_each'] = '<a href="%src" class="gallery_item">';
$ig_settings['ig_each'] = '<img src="%tsrc" alt="%alt" title="%title" />';
$ig_settings['ig_after_each'] = '</a>';

$ig_settings['ig_before_each_stand_alone'] = '<a href="%sasrc" class="gallery_item">';
$ig_settings['ig_each_stand_alone'] = '<img src="%tsrc" alt="%alt" title="%title" border="0" />';
$ig_settings['ig_after_each_stand_alone'] = '</a>';

$ig_settings['ig_cache_dir'] = 'thumb-cache/';		//relative to the "wp-content" directory
									//this plugin will try to create it for you, but don't rely on it
									//ending slash must be there

$ig_settings['ig_recreate_when_updated'] = true; //should be thumbnails recreated when post is updated?

$ig_settings['default_sharpening'] = 0; //sharpening coefficient 0..100
										//0 means no sharpening at all
										//be carefull about this parameter because sharpening takes quite a long time
										//and the script can exceed the time limit for running

$ig_settings['ig_default_quality'] = 90; //0..100 - quality of JPEG compression

$ig_settings['ig_default_max_side'] = 100;	//largest side of thumbnail in pixels

$ig_settings['ig_default_crop'] = false; //crop images to square thumbnails

$ig_settings['ig_default_crop_center'] = true;//crop image to upper left corner or to the center?

$ig_settings['ig_show_errors'] = true; //if true - error message will be added at the end of your post

$ig_settings['path_to_plugin'] = get_bloginfo('url').'/wp-content/plugins/iimage-gallery.php';
/*
If the address of your web is different from the address of WordPress - use this code:

$ig_settings['path_to_plugin'] = get_bloginfo('wpurl').'/wp-content/plugins/iimage-gallery.php';
*/


//set to "true" if you want link to stand-alone browser in the form of: http://yourweb/wp-admin/iimage-gallery.php/555/3/1/
//instead of http://yourweb/wp-admin/iimage-gallery.php?idpost=555&idg=3&idi=1
//unfortunately it doesn't work on all servers:(

$ig_settings['use_permalinks'] = false;

//====================STOP EDITING==============


$ig_max_side = $ig_settings['ig_default_max_side'];

$ig_crop = $ig_settings['ig_default_crop'];

$ig_crop_center = $ig_settings['ig_default_crop_center'];

$ig_quality = $ig_settings['ig_default_quality'];

$ig_abs_path = get_bloginfo('url').'/wp-content/'.$ig_settings['ig_cache_dir'];

$ig_stand_alone = $ig_settings['ig_default_stand_alone'];

$ig_sharpen = $ig_settings['default_sharpening'];

$ig_patterns[0] = '/\%src/i';
$ig_patterns[1] = '/\%alt/i';
$ig_patterns[2] = '/\%title/i';
$ig_patterns[3] = '/\%tsrc/i';
$ig_patterns[4] = '/\%sasrc/i';

function iimage_gallery_link($idpost,$idg,$idi){
		global $ig_settings;
		
		if($ig_settings['use_permalinks']){
			return "{$ig_settings['path_to_plugin']}/$idpost/$idg/$idi/";
		}
		else {
			return "{$ig_settings['path_to_plugin']}?idpost=$idpost&amp;idg=$idg&amp;idi=$idi";
		}

}


//--------------part where the "plugin" stuff is

if(!$ig_sa){
	
	
	/*
	Creates the thumbnails from the original images
	*/
	function iimage_gallery_create_thumbs($text) {
		global $ig_max_side,$ig_quality, $ig_crop, $ig_crop_center, $ig_settings, $ig_stand_alone, $ig_sharpen;
	
	$ig_real_path = './../wp-content/'.$ig_settings['ig_cache_dir'];
	
	
	//check if the thumb-cache directory exists and try to create it
	if(!is_dir($ig_real_path)){
		@mkdir($ig_real_path,0777);
		@chmod($ig_real_path,0777);//on some servers mkdir doesn't do that:o(
		}
	
	
	$mytext = preg_split("/\n/",stripslashes($text));
	
	foreach($mytext as $line){
			$line = trim($line);
			
			if(preg_match('/(.*?)[<|\[]gallery([^>\]]*)[>|\]](.*?)/i',$line,$foo)){
				$ingallery = true;
				$current_gallery = $foo[3];
				
				if(preg_match("/crop=\"true\"/i",$foo[2])) $ig_crop = true;
				if(preg_match("/crop=\"false\"/i",$foo[2])) $ig_crop = false;
				if(preg_match("/max_side=\"(\d+)\"/i",$foo[2],$barr)) $ig_max_side = $barr[1];
				if(preg_match("/sharpen=\"(\d+)\"/i",$foo[2],$barr)) $ig_sharpen = $barr[1];
				if(preg_match("/quality=\"(\d+)\"/i",$foo[2],$barr)) $ig_quality = $barr[1];
				if(preg_match("/crop_center=\"true\"/i",$foo[2])) $ig_crop_center = true;
				if(preg_match("/crop_center=\"false\"/i",$foo[2])) $ig_crop_center = false;
			
			}
			elseif(preg_match('/(.*?)[<|\[]\/gallery[>|\]](.*?)/i',$line,$foo)){
					$ingallery = false;
					
					
					for($i=0;$i<preg_match_all("/<img[^>]+src=\"([^\"]+)\"[^>]*>/i",$current_gallery.$foo[1],$matches,PREG_SET_ORDER);$i++)
						{
						
						$ig_dest_path = $ig_real_path.md5($matches[$i][1]).strrchr($matches[$i][1], '.');
						
						if(!file_exists($ig_dest_path) || $ig_settings['ig_recreate_when_updated']){
							$fooer = iimage_gallery_create_thumbnail($matches[$i][1], $ig_dest_path, $ig_max_side, NULL, 1, $ig_quality);
							if($fooer != 1)
								$error .= $fooer;
							
							}
						
						};
					$current_gallery = '';
	
					$ig_max_side = $ig_settings['ig_default_max_side'];
					$ig_quality = $ig_settings['ig_default_quality'];
					$ig_crop = $ig_settings['ig_default_crop'];
					$ig_crop_center = $ig_settings['ig_default_crop_center'];
					$ig_sharpen = $ig_settings['default_sharpening'];
					
					
					}
			elseif($ingallery){
				$current_gallery .= $line; //we get all "ingallery" lines to one string
				}
		}
	
		
		
		
	
	return ($ig_settings['ig_show_errors'] && !empty($error)) ? ($text.' Errors: '.$error) : $text;
	}
	
	/*
	Creates the gallery code that is shown in the post	
	*/
	//------------------------------------------------------------------------------
	
	function iimage_gallery_create_gallery($text){
	global $ig_settings, $ig_abs_path, $ig_patterns,$post,$ig_stand_alone;
	
	
	
	$gallery = false;
	$gallery_counter = 0;
	
	$original_text = $text;//hack to avoid strange bahaviour reported by 'kelly'
	
	$text = preg_split("/\n/",$text);
	
	
	foreach($text as $line){
			$line = trim($line);
			
			if(preg_match('/(.*?)[<|\[]gallery[^>\]]*[>|\]](.*?)/i',$line,$foo)){$ingallery = true;$line = $foo[1].$ig_settings['ig_before'];$current_gallery = $foo[2];$gallery_counter++;
			
			if(preg_match("/stand_alone=\"true\"/i",$foo[0])) $ig_stand_alone = true;
			if(preg_match("/stand_alone=\"false\"/i",$foo[0])) $ig_stand_alone = false;
			
			}
			elseif(preg_match('/(.*?)[<|\[]\/gallery[>|\]](.*?)/i',$line,$foo)){
					$ingallery = false;
					$ingallery_items = '';
					for($i=0;$i<preg_match_all("/<img[^>]+src=\"([^\"]+)\"[^>]*>/i",$current_gallery.$foo[1],$matches,PREG_SET_ORDER);$i++)
						{$alt = '';
						$title = '';
						$src = $ig_abs_path.md5($matches[$i][1]).strrchr($matches[$i][1], '.');
						
						
						if(preg_match("/.*?alt=\"([^\"]*)\".*?/i",$matches[$i][0],$bar)){
							$alt = $bar[1];
						}
						
						if(preg_match("/.*?title=\"([^\"]*)\".*?/i",$matches[$i][0],$bar)){
							$title = $bar[1];
						}
						
				
							$ig_replacement = array(0 => iimage_gallery_absolute_it($matches[$i][1]), 1=>$alt, 2=>$title, 3=>$src, 4=>iimage_gallery_link($post->ID,$gallery_counter,$i+1));
						
						
						if($ig_stand_alone){
							$ingallery_items .= preg_replace($ig_patterns,$ig_replacement,$ig_settings['ig_before_each_stand_alone'].$ig_settings['ig_each_stand_alone'].$ig_settings['ig_after_each_stand_alone']);
						}
						else{
							$ingallery_items .= preg_replace($ig_patterns,$ig_replacement,$ig_settings['ig_before_each'].$ig_settings['ig_each'].$ig_settings['ig_after_each']);}
						
						
						
						};
					
					$line = $ingallery_items.$ig_settings['ig_after'].$foo[2];
					$current_gallery = '';
					$ig_stand_alone = $ig_settings['ig_default_stand_alone'];
					
					}
			elseif($ingallery){
				$current_gallery .= $line; //we get all "ingallery" lines to one string
				$line = '';
				}
			else {$line .=" \n";}
			
			
			$lineout[] = $line;
		}
	
		
		
		$text = implode(" ",$lineout);
	
	if($gallery_counter > 0)
		return $text;
	else
		return $original_text;
	}


	
	/**
	* another enhanced copy of wp_create_thumbnail()
	*
	*/
	function iimage_gallery_create_thumbnail($file, $dest_path, $max_side, $effect = '', $method = 1, $quality = 80) {//method ... resampled or resized?
	global $ig_crop,$ig_crop_center,$ig_sharpen;
		// 1 = GIF, 2 = JPEG, 3 = PNG
		$file = iimage_gallery_absolute_it($file);
		
		$type = @getimagesize($file);
		
		if($type == false){
			$error = $file.' is not accessible or supported filetype.';
		}
		else {
			
			// if the associated function doesn't exist - then it's not
			// handle. duh. i hope.
			
			if(!function_exists('imagegif') && $type[2] == 1) {
				$error = __('Filetype not supported. Thumbnail not created.');
			}elseif(!function_exists('imagejpeg') && $type[2] == 2) {
				$error = __('Filetype not supported. Thumbnail not created.');
			}elseif(!function_exists('imagepng') && $type[2] == 3) {
				$error = __('Filetype not supported. Thumbnail not created.');
			} else {
			
				// create the initial copy from the original file
				if($type[2] == 1) {
					$image = @imagecreatefromgif($file);
				} elseif($type[2] == 2) {
					$image = @imagecreatefromjpeg($file);
				} elseif($type[2] == 3) {
					$image = @imagecreatefrompng($file);
				}
				
				if (function_exists('imageantialias'))
					imageantialias($image, TRUE);
				
				
				
				$image_attr = getimagesize($file);
				$image_width = $image_attr[0];
				$image_height = $image_attr[1];
				
				//crop source image?
				if($ig_crop){
				$fooImageSize = min($image_width,$image_height);
	/*            $fooImage = imagecreatetruecolor($fooImageSize,$fooImageSize);
				imagecopy ($fooImage, $image, 0, 0, 0, 0, $fooImageSize, $fooImageSize);
				$image = $fooImage;*/
				
				$image_width = $fooImageSize;
				$image_height = $fooImageSize;
				$image_new_width = $max_side;
				$image_new_height = $max_side;
				}
				else {
						// figure out the longest side
						
						if($image_attr[0] > $image_attr[1]) {
							$image_width = $image_attr[0];
							$image_height = $image_attr[1];
							$image_new_width = $max_side;
							
							$image_ratio = $image_width/$image_new_width;
							$image_new_height = $image_height/$image_ratio;
							//width is > height
						} else {
							$image_width = $image_attr[0];
							$image_height = $image_attr[1];
							$image_new_height = $max_side;
							
							$image_ratio = $image_height/$image_new_height;
							$image_new_width = $image_width/$image_ratio;
							//height > width
						}
				}
				
				
				
				$thumbnail = imagecreatetruecolor($image_new_width, $image_new_height);
				if( function_exists('imagecopyresampled') && $method == 1 ){
					if($ig_crop && $ig_crop_center)
						@imagecopyresampled($thumbnail, $image, 0, 0, floor( ($image_attr[0]-$image_width)/2) , floor( ($image_attr[1]-$image_height)/2), $image_new_width, $image_new_height, $image_width, $image_height);
					else
						@imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $image_new_width, $image_new_height, $image_width, $image_height);
					}
				else{
					if($ig_crop && $ig_crop_center)
						@imagecopyresized($thumbnail, $image, 0, 0, floor( ($image_attr[0]-$image_width)/2) , floor( ($image_attr[1]-$image_height)/2), $image_new_width, $image_new_height, $image_width, $image_height);
					else
						@imagecopyresized($thumbnail, $image, 0, 0, 0, 0, $image_new_width, $image_new_height, $image_width, $image_height);
					}
				
				// move the thumbnail to it's final destination
				
				//$path = explode('/', $file);
				//$thumbpath = substr($file, 0, strrpos($file, '/')) . "/{$thumb_prefix}" . $path[count($path)-1];
				$thumbpath = $dest_path;
				
				/*if(file_exists($thumbpath))
					return sprintf(__("The filename '%s' already exists!"), $thumbpath);*/
			
				//sharpen the image
				$thumbnail = ig_sharpen($thumbnail,$ig_sharpen);
				
				touch($thumbpath);
				
				if($type[2] == 1) {
					if(!@imagegif($thumbnail, $thumbpath)) {
						$error = __("Thumbnail path invalid {$thumbpath}");
					}
				} elseif($type[2] == 2) {
				//echo $quality;
					if(!@imagejpeg($thumbnail, $thumbpath,$quality)) {
						$error = __("Thumbnail path invalid {$thumbpath}");
					}
				} elseif($type[2] == 3) {
					if(!@imagepng($thumbnail, $thumbpath)) {
						$error = __("Thumbnail path invalid {$thumbpath}");
						
					}
				}
				
			}
		}
		
		if(!empty($error))
		{
			return $error;
		}
		else
		{
			@chmod($thumbpath, 0666);//fixme
			return 1;
		}
	}
	
	
	/*
	Function for sharpenin images
	*/
	function ig_sharpen($resourceImage,$k=100)
		{
		if($k == 0) return $resourceImage;
		
		$k = $k/100;
			
			$info['w'] = imagesx($resourceImage);
			$info['h'] = imagesy($resourceImage);
			
			$img2=imagecreatetruecolor($info['w']-2,$info['h']-2);
			for($x=1;$x<$info['w']-1;$x++)
			{
				for($y=1;$y<$info['h']-1;$y++)
				{
					$rgb[1][0]=imagecolorat($resourceImage,$x,$y-1);
					$rgb[0][1]=imagecolorat($resourceImage,$x-1,$y);
					$rgb[1][1]=imagecolorat($resourceImage,$x,$y);
					$rgb[2][1]=imagecolorat($resourceImage,$x+1,$y);
					$rgb[1][2]=imagecolorat($resourceImage,$x,$y+1);
	
					$r = 	 -$k *(($rgb[1][0] >> 16) & 0xFF) +
							 -$k *(($rgb[0][1] >> 16) & 0xFF) +
						(1+4*$k) *(($rgb[1][1] >> 16) & 0xFF) +
							 -$k *(($rgb[2][1] >> 16) & 0xFF) +
							 -$k *(($rgb[1][2] >> 16) & 0xFF) ;
	
					$g = 	 -$k *(($rgb[1][0] >> 8) & 0xFF) +
							 -$k *(($rgb[0][1] >> 8) & 0xFF) +
						(1+4*$k) *(($rgb[1][1] >> 8) & 0xFF) +
							 -$k *(($rgb[2][1] >> 8) & 0xFF) +
							 -$k *(($rgb[1][2] >> 8) & 0xFF) ;
	
					$b = 	 -$k *($rgb[1][0] & 0xFF) +
							 -$k *($rgb[0][1] & 0xFF) +
						(1+4*$k) *($rgb[1][1] & 0xFF) +
							 -$k *($rgb[2][1] & 0xFF) +
							 -$k *($rgb[1][2] & 0xFF) ;
	
					$r=min(255,max(0,$r));
					$g=min(255,max(0,$g));
					$b=min(255,max(0,$b));
	
					if(!$cols[$r][$g][$b])
					{
						
						$cols[$r][$g][$b]=imagecolorallocate($img2,$r,$g,$b);
					}
					imagesetpixel($img2,$x-1,$y-1,$cols[$r][$g][$b]);
				}
			}
			return $img2;
		} 
	
	
	// And now for the filters
	
	add_filter('format_to_post', 'iimage_gallery_create_thumbs');
	add_filter('content_save_pre', 'iimage_gallery_create_thumbs');//since WP1.5
	add_filter('the_content', 'iimage_gallery_create_gallery',4);

}

//-----------------code for stand-alone gallery


//get text of the post
if($ig_sa){
	
	$ig_error = false;
	$ig_text = '';
	$ig_message = '';
	$ig_has_next_gallery = false;
	$ig_has_next_image = false;
	
	$ig_gallery = array();
	//$ig_image = '';
	
	$ig_galleries_count = 0;
	$ig_images_count = 0;
	
	
	
	$ig_has_previous_gallery = ($idg_safe == 1) ? false : $idg_safe-1;
	$ig_has_previous_image = ($idi_safe == 1) ? false : $idi_safe-1;
	
	$today = current_time('mysql', 1);
	
	if($ig_post = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE ID = '$idpost_safe' AND (post_status = 'publish' OR post_status = 'static' OR post_status='private' ) AND post_date_gmt < '$today' LIMIT 1"))
		{
		
		$ig_post = $ig_post[0];
		
		remove_filter('the_content','iimage_gallery_create_gallery',4);
		remove_filter('the_content', 'af_display_attachments');//fix for plugin collision
		$ig_text = apply_filters('the_content', $ig_post->post_content);	
		$ig_text = str_replace(array("\r\n", "\r", "\n"),array(' ',' ',' '),$ig_text);
		}
	else {
		$ig_error = true;
		$ig_message = __('Post has not been found.');
		}

//get galleries
	if($ig_galleries_count = preg_match_all("/[<|\[]gallery[^>\]]*[>|\]].*?[<|\[]\/gallery[>|\]]/i",
		$ig_text,
	   $ig_galleries_from_post, 
	   PREG_SET_ORDER)){
			//echo "máme: ".count($ig_galleries_from_post)."<br />";//fix
			
			
			
			if($ig_galleries_count < $idg_safe){
				$ig_error = true;
				$ig_message = __('Passed gallery id is not valid.');
				}
			else {
				if($ig_galleries_count > $idg_safe){
					$ig_has_next_gallery = $idg_safe+1;}
				
				if(!$ig_error && $ig_images_count = preg_match_all("/<img[^>]+src=\"([^\"]+)\"[^>]*>/i",$ig_galleries_from_post[$idg_safe-1][0],$foo,PREG_PATTERN_ORDER)){
				
				
					if($ig_images_count > $idi_safe){
						$ig_has_next_image = $idi_safe+1;
						}
					elseif($ig_images_count < $idi_safe){
						$ig_error = true;
						$ig_message = __('Passed image id is not valid.');
					}
					
					if(!$ig_error) {
						$ig_gallery['full'] = $foo[0];
						$ig_gallery['src'] = $foo[1];
						
						
						
						
						for($i=0;$i<$ig_images_count;$i++)
							{$alt = '';
							$title = '';
							$src = $ig_abs_path.md5($ig_gallery['src'][$i]).strrchr($ig_gallery['src'][$i], '.');
							
							
							if(preg_match("/.*?alt=\"([^\"]*)\".*?/i",$ig_gallery['full'][$i],$bar)){
								$alt = $bar[1];
							}
							
							if(preg_match("/.*?title=\"([^\"]*)\".*?/i",$ig_gallery['full'][$i],$bar)){
								$title = $bar[1];
							}
							
							$ig_gallery['title'][] = $title;
							$ig_gallery['alt'][] = $alt;
							
				
								$ig_replacement = array(0 => $ig_gallery['src'][$i], 1=>$alt, 2=>$title, 3=>$src,  4=>iimage_gallery_link($idpost_safe,$idg_safe,$i+1));
							
							$ig_gallery['thumb'][] = preg_replace($ig_patterns,$ig_replacement,$ig_settings['ig_before_each_stand_alone'].$ig_settings['ig_each_stand_alone'].$ig_settings['ig_after_each_stand_alone']);
							
							
							
							}; 
						
						$ig_gallery['src'] = array_map('iimage_gallery_absolute_it',$ig_gallery['src']);
						
						array_unshift($ig_gallery['full'],'');//just to have index starting from 1
						array_unshift($ig_gallery['src'],'');//just to have index starting from 1
						array_unshift($ig_gallery['thumb'],'');//just to have index starting from 1
						array_unshift($ig_gallery['title'],'');//just to have index starting from 1
						array_unshift($ig_gallery['alt'],'');//just to have index starting from 1
						
						$ig_gallery['title'][0] = strip_tags(apply_filters('single_post_title', $ig_post->post_title));
					}
					
					}
				else {
					$ig_error = true;
					$ig_message = __('Gallery is empty.');
				}
				
			}
		}
	else {
		
		$ig_error = true;
		$ig_message = __('There is no gallery in this post.');
		}

require_once('iimage-gallery-template.php');//template

}

//-------------------common code

//makes the uri of the file absolute.	
function iimage_gallery_absolute_it($file){
	
	
		switch($file{0}){
			case '.':
				$file = get_bloginfo('url').substr($file, 1);
				break;
			case '/':
				$file = get_bloginfo('url').$file;
				break;
		}
		
		return $file;
}


?>