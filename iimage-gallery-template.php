<?php
/*
You can use

$idpost_safe - id of current post
$idg_safe - id of current gallery
$idi_safe - id of current image

$ig_post - array representing one post in WP database

$ig_gallery['full'] - <img> tags of all images in the current gallery
$ig_gallery['thumb'] - <img> tags of all thumbs of all images in the current gallery
$ig_gallery['src'] - array of all paths of all images in the current gallery

$ig_gallery['alt'] - array of all alts of all images in the current gallery
$ig_gallery['title'] - array of all titles of all images in the current gallery + post title of current post at index 0


$ig_error - true if error occured
$ig_message - error message if $ig_error is true

$ig_has_next_gallery - index of next gallery or false if the current gallery is the last one
$ig_has_next_image - index of next image or false if the current image is the last one

$ig_has_previous_gallery - index of previous gallery or false if the current gallery is the first one
$ig_has_previous_image - index of previous image or false if the current image is the first one

$ig_galleries_count - number of galleries in the post
$ig_images_count - number of images in the current gallery

*/?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head profile="http://gmpg.org/xfn/11">
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" /> 
<title><?php echo $ig_gallery['title'][0];?></title>

<style type="text/css">
a {color: #AAAAAA;}
a:visited {color: #888888;}
body {margin: 0; padding: 0;  font-family: sans-serif; color: #505050;}
body, .navigation {background-color: #202020;}
#envelope {margin: 0 auto 1em auto; background-color: #404040; text-align: center; color: white;}
#envelope img {margin: 5px; border: 0;}
#image {margin: 0 auto 0 auto; padding: 0.5em 0.5em 0.5em 0.5em; text-align: center;}
#image img {display: block; margin: 0 auto 0.5em auto; border: 0;}
.navigation {text-align: center; font-size: 0.8em; padding: 0.2em;}
#credit {font-size: 0.8em; text-align: right;}
</style>

<SCRIPT type="text/javascript" language="javascript" defer="defer">
<!--
//pre-load the next image
if (document.images && <?php echo ($ig_has_next_image) ? "true" : "false" ?>) {
Image1 = new Image();
Image1.src = "<?php echo $ig_gallery['src'][$ig_has_next_image] ?>";
} //-->
</SCRIPT>

</head>
<body>
<div id="envelope">
<div id="image">
<?php
if($ig_error){
	echo $ig_message;
}
else {
echo "<img src=\"{$ig_gallery['src'][$idi_safe]}\" alt=\"{$ig_gallery['alt'][$idi_safe]}\" title=\"{$ig_gallery['title'][$idi_safe]}\" /><br />
{$ig_gallery['title'][$idi_safe]}";



echo '</div>';
echo '<div class="navigation">';

if($ig_has_previous_image){
		echo "<a href=\"".iimage_gallery_link($idpost_safe,$idg_safe,$ig_has_previous_image)."\">Previous image</a>";
}
elseif($ig_has_previous_gallery) {
		echo "<a href=\"".iimage_gallery_link($idpost_safe,$ig_has_previous_gallery,1)."\">Previous gallery</a>";
}

if(($ig_has_previous_image || $ig_has_previous_gallery) && ($ig_has_next_image || $ig_has_next_gallery)){
echo ' | ';}

if($ig_has_next_image){
		echo "<a href=\"".iimage_gallery_link($idpost_safe,$idg_safe,$ig_has_next_image)."\">Next image</a>";
}
elseif($ig_has_next_gallery) {
		echo "<a href=\"".iimage_gallery_link($idpost_safe,$ig_has_next_gallery,1)."\">Next gallery</a>";
}

echo '</div>';

for($i=1;$i<=$ig_images_count;$i++){
	echo $ig_gallery['thumb'][$i];
	}
}

?>
</div>

<div class="navigation">
	<?php
			echo "<a href=\"".get_permalink($idpost_safe)."\">&laquo; Back to: ".apply_filters('the_title',$ig_post->post_title)."</a>";
	?>
</div>


<div id="credit">
Powered by <a href="http://fredfred.net/skriker/index.php/iimage-gallery">IImage Gallery</a>
</div>
</body>
</html>