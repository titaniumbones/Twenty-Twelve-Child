<?php 
/* folloiwing guides here:
http://lisles.net/header-image/
http://op111.net/53
http://codex.wordpress.org/Child_Themes
*/

// include CCTM getposts functions for now
include_once( CCTM_PATH . '/includes/GetPostsQuery.php');
include_once( CCTM_PATH . '/includes/SummarizePosts.php');

// so easy! this turns our function into a shortcode that a user
// can add to a post. shouldbe renamed to something more descriptive
// do this by changing "my_gallery to "historical_gallery" or 
// something like that; and probably you should change 
// my_gallery_shortcode to something else too, both here, and in the 
// function definition below (line 30)
add_shortcode('my_gallery', 'my_gallery_shortcode');

/**
 * The HistoricalGallery shortcode.
 *
 * This copies the gallery shortcode
 * and lets you display a group of historical images in a post.
 *
 *
 * @param array $attr Attributes of the shortcode.
 * @return string HTML content to display gallery.
 */
function my_gallery_shortcode($attr) {
	global $post;

	static $instance = 0;
	$instance++;

	// Allow plugins/themes to override the default gallery template.
	$output = apply_filters('post_gallery', '', $attr);
	if ( $output != '' )
		return $output;

	// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
	if ( isset( $attr['orderby'] ) ) {
		$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
		if ( !$attr['orderby'] )
			unset( $attr['orderby'] );
	}

	extract(shortcode_atts(array(
		'order'      => 'ASC',
		'orderby'    => 'ID',
		'id'         => $post->ID,
		'itemtag'    => 'dl',
		'icontag'    => 'dt',
		'captiontag' => 'dd',
		'columns'    => 4,
		'size'       => 'thumbnail',
        'taxonomy'   => '',
        'taxonomy_term' => ''
	), $attr));

	$id = intval($id);
	if ( 'RAND' == $order )
		$orderby = 'none';

    // this is the line that needs to be replaced by a new query, either 
    // standard wordpress get_posts query or a new summarize_post query
    // $attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

    // using the CCTM GetPostsQuery, which returns a nice neat array
    // with all custom fields as array elements.  see below, around line 135
    $Q = new GetPostsQuery();
    $args['post_type'] = 'historical-images';
    $args['orderby']=$orderby;
    $args['order']=$order;
    if (isset($taxonomy))   {
      $args['taxonomy']=$taxonomy;
      $args['taxonomy_term']=$taxonomy_term;
    }
   
    $attachments = $Q->get_posts($args);
    //print_r($attachments);

	if ( empty($attachments) )
		return '';


    // this bit still needs to be changed. right now feeds won't work right.
	if ( is_feed() ) {
		$output = "\n";
		foreach ( $attachments as $att_id => $attachment )
			$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
		return $output;
	}
    // smart tag munging from the original. leave it alone
	$itemtag = tag_escape($itemtag);
	$captiontag = tag_escape($captiontag);
	$columns = intval($columns);
	$itemwidth = $columns > 0 ? floor(100/$columns) : 100;
	$float = is_rtl() ? 'right' : 'left';

	$selector = "gallery-{$instance}";

    // all the style info is taken directly from the original 
    // gallery shortcode, so all that css should still apply. 
	$gallery_style = $gallery_div = '';
	if ( apply_filters( 'use_default_gallery_style', true ) )
		$gallery_style = "
		<style type='text/css'>
			#{$selector} {
				margin: auto;
			}
			#{$selector} .gallery-item {
				float: {$float};
				margin-top: 10px;
				text-align: center;
				width: {$itemwidth}%;
			}
			#{$selector} img {
				border: 2px solid #cfcfcf;
			}
			#{$selector} .gallery-caption {
				margin-left: 0;
			}
		</style>
		<!-- see gallery_shortcode() in wp-includes/media.php -->";
	$size_class = sanitize_html_class( $size );
	$gallery_div = "<div id='$selector' class='gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}'>";
	$output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );

    // this is the actual loop
	$i = 0;
	foreach ( $attachments as  $attachment ) {
      // this is the key change
      // replaced the original $link with CCTM's more flexible function
      //$link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);
      $link = '<a href ="' . $attachment['permalink'] . '" title="'. $attachment['post_title'] . '">' . CCTM::filter($attachment['historicalphotograph'], 'to_image_tag', $size) . '</a>';
      // all this stuff is just generating the html. I left it alone
      $output .= "<{$itemtag} class='gallery-item'>";
      $output .= "
            <{$icontag} class='gallery-icon'>
				$link
			</{$icontag}>";
		if ( $captiontag && trim($attachment['post_title']) ) {
			$output .= "
				<{$captiontag} class='wp-caption-text gallery-caption'>
				" . wptexturize($attachment['post_title']) . "
				</{$captiontag}>";
		}
		$output .= "</{$itemtag}>";
		if ( $columns > 0 && ++$i % $columns == 0 )
			$output .= '<br style="clear: both" />';
	}

	$output .= "
			<br style='clear: both;' />
		</div>\n";

	return $output;
}


// another hsortcode to insert just one image
add_shortcode('histimage', 'histimage_shortcode');

/**
 * The HistoricalGallery shortcode.
 *
 * This copies the gallery shortcode
 * and lets you display a group of historical images in a post.
 *
 *
 * @param array $attr Attributes of the shortcode.
 * @return string HTML content to display gallery.
 */
function histimage_shortcode($attr) {
	global $post;

	static $instance = 0;
	$instance++;

	extract(shortcode_atts(array(
		/* 'id'         => $post->ID, */
        'id'         => '',
		'caption' => False,
		'size'       => 'thumbnail',
        'slug'       => '',
        'float'      => 'right',
	), $attr));

	$id = intval($id);
	if ( 'RAND' == $order )
		$orderby = 'none';

    // this is the line that needs to be replaced by a new query, either 
    // standard wordpress get_posts query or a new summarize_post query
    // $attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

    // using the CCTM GetPostsQuery, which returns a nice neat array
    // with all custom fields as array elements.  see below, around line 135
    $Q = new GetPostsQuery();
    $args['post_type'] = 'historical-images';
    if (isset ($id)) {
      $args['ID'] = $id;
    } elseif (isset ($slug)) {
      $args['slug'] = $slug;
    } else {
      return "id or slug needs to be set for this shortcode";
        }
    $attachments = $Q->get_posts($args);
    //print_r($attachments);

	if ( empty($attachments) )
		return 'no images found';


    // smart tag munging from the original. leave it alone
	$columns = intval($columns);
	$itemwidth = $columns > 0 ? floor(100/$columns) : 100;

	$selector = "historicalimage-{$instance}";

    // all the style info is taken directly from the original 
    // gallery shortcode, so all that css should still apply. 
	$gallery_style = $gallery_div = '';
	if ( apply_filters( 'use_default_gallery_style', true ) )
		$gallery_style = "
		<style type='text/css'>
			#{$selector} {
				margin: auto;
			}
			#{$selector} {
				float: {$float};
				margin-top: 10px;
				text-align: center;
			}
			#{$selector} img {
				border: 2px solid #cfcfcf;
			}
			#{$selector} .gallery-caption {
				margin-left: 0;
			}
		</style>
		<!-- see gallery_shortcode() in wp-includes/media.php -->";
	$size_class = sanitize_html_class( $size );
	$gallery_div = "<div id='$selector' class='historicalimage'>";
	$output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );

    // this is the actual loop
	$i = 0;
	foreach ( $attachments as  $attachment ) {
      $link = '<a href ="' . $attachment['permalink'] . '" title="Learn more about &quot;'. $attachment['post_title'] . '&quot;">' . CCTM::filter($attachment['historicalphotograph'], 'to_image_tag', $size) . '</a>';
      // all this stuff is just generating the html. I left it alone
      $output .= "$link";
		if ( $caption && trim($attachment['post_title']) ) {
			$output .= "
				<div class='wp-caption-text gallery-caption'>
				" . wptexturize($attachment['post_title']) . "
				</div>";
		}
	}

	$output .= "
			</div>\n";

	return $output;
}

?>
