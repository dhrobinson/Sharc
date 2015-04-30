<?php
/**
 * @package Sharc
 * @version 1.0
 */
/*
Plugin Name: Sharc
Plugin URI: http://
Description: Enable Sharc image lookups on media 
Author: David Robinson / The Swarm
Version: 1.0
Author URI: http://dhrobinson.com/
*/

// Add tags to images
 function mta_add_tags_to_attachments(){
	register_taxonomy_for_object_type('post_tag','attachment');
}
add_action('init','mta_add_tags_to_attachments');	// Let admins tag images
add_action('init','mta_ajax_hooks');				// API endpoints
add_action('wp','mta_social');						// Social connect 
add_action('wp','mta_setup');						// Enqueue scripts, CSS
add_action('wp','mta_admin_setup');					// Admin JS

// Enable AJAX
// embed the javascript file that makes the AJAX request
//if(!is_admin()){
function mta_ajax_hooks(){
	// AJAX hooks
	add_action( 'wp_ajax_nopriv_mta-imglookup', 'mta_imglookup' );
	add_action( 'wp_ajax_mta-imglookup', 'mta_imglookup' );

	add_action( 'wp_ajax_nopriv_mta-go', 'mta_go' );
	add_action( 'wp_ajax_mta-go', 'mta_go' );

	//add_action( 'wp_ajax_nopriv_mta-comment', 'mta_comment' );
	add_action( 'wp_ajax_mta-comment', 'mta_comment' );

	add_action( 'wp_ajax_nopriv_mta-blacklist', 'mta_blacklist' );
	add_action( 'wp_ajax_mta-blacklist', 'mta_blacklist' );
}
function mta_setup(){
	global $post;
	
	// Is Sharc enabled?
	$sharc=get_post_meta($post->ID,'mta_post_sharc',true);
	if($sharc!='on')return;

	$opts=mta_getopts();
	wp_enqueue_script( 'mta', plugin_dir_url( __FILE__ ) . 'mta.js', array( 'jquery' ) );
	wp_enqueue_style( 'mta', plugin_dir_url( __FILE__ ) . 'mta.css' );
	// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)

	$localvars=array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'plugindir'=>plugin_dir_url( __FILE__ ),
		'instagram'=>false,
		'flickr'=>false,
	);

	$uid=get_current_user_id();
	$usermeta=get_user_meta($uid);
	if(!$usermeta['flickr_username'][0])update_usermeta($uid,'flickr_connected',0);
	$localvars['usermeta']=$usermeta;

	if(mta_social_connected('instagram')){
		$localvars['instagram']=true;
	}else{
		$localvars['instagram_connect']='https://api.instagram.com/oauth/authorize/?client_id='.$opts['instagram_id'].'&redirect_uri=http://dhrobinson.com/swarm/mta/?extra='.$post->ID.'&response_type=code&scope=basic+comments';
	}
	if(mta_social_connected('flickr')){
		$localvars['flickr']=true;
	}else{
		// Need to get a request token every time unfortunately

		$str1='oauth_callback=http%3A%2F%2Fdhrobinson.com%2Fswarm%2Fmta%2F';
		$str2='&oauth_consumer_key='.$opts['flickr_key'];
		$str3='&oauth_nonce='.rand(1,10000000);
		$str4='&oauth_signature_method=HMAC-SHA1';
		$str5='&oauth_timestamp='.time();
		$str6='&oauth_version=1.0';

		$part1='GET&';
		$part2='http%3A%2F%2Fwww.flickr.com%2Fservices%2Foauth%2Frequest_token&';
		$part3=urlencode($str1.$str2.$str3.$str4.$str5.$str6);

		$request=$part1.$part2.$part3;

		$signature=base64_encode(hash_hmac('sha1', $request, $opts['flickr_secret'].'&', true));

		$str7='&oauth_signature='.urlencode($signature);

		$params=$str1.$str2.$str3.$str4.$str5.$str6.$str7;

		// Request auth token
		$c=curl_init();
		curl_setopt($c,CURLOPT_URL,'http://www.flickr.com/services/oauth/request_token?'.$params);
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		$output=curl_exec($c);

		parse_str($output,$response);

		/*
		$localvars['flickr_signature']=$signature;
		$localvars['flickr_request']=$request;
		$localvars['flickr_response']=$output;
		$localvars['flickr_params']=$params;
		*/

		update_user_meta($uid,'flickr_oauth_token_secret',$response['oauth_token_secret']);


		$localvars['flickr_connect']='https://www.flickr.com/services/oauth/authorize?perms=write&extra='.$post->ID.'&oauth_token='.$response['oauth_token'];
	}
	#if(mta_social_connected('flickr'))	 $localvars['flickr']=true;

	wp_localize_script( 'mta', 'MTA', $localvars );

}
function mta_admin_setup(){
	if(is_wp_admin())wp_enqueue_script( 'mtaadmin', plugin_dir_url( __FILE__ ) . 'mtaadmin.js', array( 'jquery','mta' ) );
}
function mta_social(){
	// Save access tokens 

	if(!is_wp_admin())return;
	$uid=get_current_user_id();
	$opts=mta_getopts();

	// Check for Instagram API tokens
	if($_GET['code']){
		// Instagram
		$postfields=array(
			'client_id'=>$opts['instagram_id'],
			'client_secret'=>$opts['instagram_secret'],
			'grant_type'=>'authorization_code',
			'redirect_uri'=>'http://dhrobinson.com/swarm/mta/?extra='.$_GET['extra'],
			'code'=>$_GET['code']
		);
		
		$c=curl_init();
		curl_setopt($c,CURLOPT_URL,'https://api.instagram.com/oauth/access_token');
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($c,CURLOPT_POST,true);
		curl_setopt($c,CURLOPT_POSTFIELDS,$postfields);
		curl_setopt($c,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($c,CURLOPT_SSL_VERIFYHOST,false);
		$output=curl_exec($c);

		// curl
		$auth=json_decode($output);

		// usermeta
		$usermeta=array(
			'instagram_connected'=>true,
			'instagram_access_token'=>$auth->access_token,
			'instagram_user_id'=>$auth->user->id,
			'instagram_username'=>$auth->user->username,
			'instagram_full_name'=>$auth->user->full_name,
			'instagram_profile_picture'=>$auth->user->profile_picture,
		);

		foreach($usermeta as $field=>$value){
			update_user_meta($uid,$field,$value);
		}

		// Redirect user back to post/page
		$redirect=get_permalink($_GET['extra']);
		wp_redirect($redirect,302);
		die();
	}

	if($_GET['oauth_token']&&$_GET['oauth_verifier']){
		// Flickr
		
		$str1='oauth_consumer_key='.$opts['flickr_key'];
		$str2='&oauth_nonce='.rand(1,10000000);
		$str3='&oauth_signature_method=HMAC-SHA1';
		$str4='&oauth_timestamp='.time();
		$str5='&oauth_token='.$_GET['oauth_token'];
		$str6='&oauth_verifier='.$_GET['oauth_verifier'];
		$str7='&oauth_version=1.0';

		$part1='GET&';
		$part2='http%3A%2F%2Fwww.flickr.com%2Fservices%2Foauth%2Faccess_token&';
		$part3=urlencode($str1.$str2.$str3.$str4.$str5.$str6.$str7);

		$request=$part1.$part2.$part3;

		#print_r($request);
		#echo "\n\n";

		$signature=base64_encode(hash_hmac('sha1', $request, $opts['flickr_secret'].'&'.get_user_meta($uid,'flickr_oauth_token_secret',true), true));

		$str8='&oauth_signature='.urlencode($signature);

		$params=$str1.$str2.$str3.$str4.$str5.$str6.$str7.$str8;

		// Request auth token
		$c=curl_init();
		curl_setopt($c,CURLOPT_URL,'http://www.flickr.com/services/oauth/access_token?'.$params);
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		$output=curl_exec($c);
		#print_r($output);

		parse_str($output,$response);

		/*
		fullname=Jamal%20Fanaian
		&oauth_token=72157626318069415-087bfc7b5816092c
		&oauth_token_secret=a202d1f853ec69de
		&user_nsid=21207597%40N07
		&username=jamalfanaian
		*/

		// usermeta
		$usermeta=array(
			'flickr_connected'=>true,
			'flickr_access_token'=>$response['oauth_token'],
			'flickr_access_secret'=>$response['oauth_token_secret'],
			'flickr_user_id'=>$response['user_nsid'],
			'flickr_username'=>$response['username'],
			'flickr_full_name'=>$response['fullname'],
		);

		foreach($usermeta as $field=>$value){
			update_user_meta($uid,$field,$value);
		}

		// Redirect user back to post/page
		$redirect=get_permalink($_GET['extra']);
		wp_redirect($redirect,302);
		die();

	}

	// update user
	// redirect back to page
}

/**
 * Adding our custom fields to the $form_fields array
 * 
 * @param array $form_fields
 * @param object $post
 * @return array
 */
function mta_attachment_custom_fields($form_fields, $post) {
    // $form_fields is a special array of fields to include in the attachment form
    // $post is the attachment record in the database
    //     $post->post_type == 'attachment'
    // (attachments are treated as posts in Wordpress)
     
    // add our custom field to the $form_fields array
    // input type="text" name/id="attachments[$attachment->ID][custom1]"
    $form_fields["isbn"] = array(
        "label" => __("ISBN (optional)"),
        "input" => "text", // this is default if "input" is omitted
        "value" => get_post_meta($post->ID, "isbn", true)
    );
     
    return $form_fields;
}
// attach our function to the correct hook
add_filter("attachment_fields_to_edit", "mta_attachment_custom_fields", null, 2);
function mta_attachment_custom_fields_save( $post, $attachment ) {
	if( isset( $attachment['isbn'] ) )
		update_post_meta( $post['ID'], 'isbn', $attachment['isbn'] );

	if( isset( $attachment['isbn'] ) )
		update_post_meta( $post['ID'], 'isbn', $attachment['isbn'] );

	return $post;
}

add_filter( 'attachment_fields_to_save', 'mta_attachment_custom_fields_save', 10, 2 );

function mta_imglookup(){
	$response=array();
	$imgsrc=$_POST['src'];

	#print_r($_POST);

	if($id=mta_get_attachment_id_by_url($imgsrc)){
		// This item is in the media library, and thus valid for MTA analysis
		$image=get_post($id);
		$meta=get_post_meta($id);
		$tags=get_the_tags($id);
		$file=get_attached_file($id);

		// Get metadata
		$exif = exif_read_data($file,0,true);
		foreach ($exif as $key => $section) {
			foreach ($section as $name => $val) {
				$response['exif'][$key][$name]=$val;
			}
		}

		// Build response
		$response['sha1']=sha1($file);
		$response['guid']=$image->guid;
		$response['title']=$image->post_title;

		if($tags){
			foreach($tags as $tag){
				(array)$response['tags'][]=$tag->name;
			}
		}

		if($meta['isbn'][0])$response['isbn']=$meta['isbn'][0];
	}else{
		$response['error']='No attachment found for '.$_POST['src'];
	}

	$response = json_encode($response);
	header("Content-Type: application/json");
	echo $response;
	exit;
}
function mta_go(){
	if($_POST['uri']&&$_POST['tags']&&$_POST['exif']&&$_POST['id']){
		$opts=mta_getopts();
		if($_POST['nocache']==true&&!is_wp_admin())$_POST['nocache']='false';

		$c=curl_init();
		curl_setopt($c,CURLOPT_URL,$opts['neo4j']);
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($c,CURLOPT_POST,true);
		curl_setopt($c,CURLOPT_POSTFIELDS,$_POST);
		curl_setopt($c,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($c,CURLOPT_SSL_VERIFYHOST,false);
		$output=curl_exec($c);
		#print_r(curl_getinfo($c));
		header("Content-Type: application/json");
		echo $output;
		exit;
	}
}
function mta_comment(){
	if(!is_wp_admin())return;
	$uid=get_current_user_id();
	$opts=mta_getopts();
	for($i=0;$i<count($_POST['items']);$i++){
		list($source,$id)=explode('|',$_POST['items'][$i]);

		if($source=='instagram'){
			$url='https://api.instagram.com/v1/media/'.$id.'/comments';
			$comment=$_POST['comment'];
			$access_token=get_user_meta($uid,'instagram_access_token',true);

			$postfields=array(
				'text'=>$comment,
				'access_token'=>$access_token,
			);

		
			$c=curl_init();
			curl_setopt($c,CURLOPT_URL,$url);
			curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($c,CURLOPT_POST,true);
			curl_setopt($c,CURLOPT_POSTFIELDS,$postfields);
			curl_setopt($c,CURLOPT_SSL_VERIFYPEER,false);
			curl_setopt($c,CURLOPT_SSL_VERIFYHOST,false);
			$output=json_decode(curl_exec($c));

			$_POST['output']=json_decode($output);

			if($output->meta->code==400){
				// OAuth failure. Clear token.
				// usermeta
				print_r($output);
				$usermeta=array(
					'instagram_connected'=>'',
					'instagram_access_token'=>'',
					'instagram_user_id'=>'',
					'instagram_username'=>'',
					'instagram_full_name'=>'',
					'instagram_profile_picture'=>'',
				);

				$uid=get_current_user_id();
				foreach($usermeta as $field=>$value){
					update_user_meta($uid,$field,$value);
				}
				/*
				*/
			}

			
			header("Content-Type: application/json");
			echo json_encode($_POST);
		}

		if($source=='flickr'){
	
			//$str1='api_key=14d01ca7481b555849120a4e287715ce';
			$str1='api_key='.$opts['flickr_key'];
			$str1.='&comment_text='.rawurlencode($_POST['comment']);
			$str1.='&format=json';
			$str1.='&method=flickr.photos.comments.addComment';
			$str1.='&nojsoncallback=1';
			$str2='&oauth_consumer_key='.$opts['flickr_key'];
			$str3='&oauth_nonce='.rand(1,10000000);
			$str4='&oauth_signature_method=HMAC-SHA1';
			$str5='&oauth_timestamp='.time();
			$str6='&oauth_token='.get_user_meta($uid,'flickr_access_token',true);
			$str7='&oauth_version=1.0';
			$str7.='&photo_id='.$id;

			$part1='POST&';
			$part2='https%3A%2F%2Fapi.flickr.com%2Fservices%2Frest&';
			$part3=urlencode($str1.$str2.$str3.$str4.$str5.$str6.$str7);

			$request=$part1.$part2.$part3;

			$_POST['request']=$request;
			$_POST['flickr_secret']=get_user_meta($uid,'flickr_access_secret',true);

			$signature=base64_encode(hash_hmac('sha1', $request, $opts['flickr_secret'].'&'.get_user_meta($uid,'flickr_access_secret',true), true));

			$str8='&oauth_signature='.urlencode($signature);

			$params=$str1.$str2.$str3.$str4.$str5.$str6.$str7.$str8;

			$_POST['params']=$params;

			// Request auth token
			$c=curl_init();
			curl_setopt($c,CURLOPT_URL,'https://api.flickr.com/services/rest');
			curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
			curl_setopt($c,CURLOPT_SSL_VERIFYHOST,false);
			curl_setopt($c,CURLOPT_SSL_VERIFYPEER,false);
			curl_setopt($c,CURLOPT_POST,true);
			curl_setopt($c,CURLOPT_POSTFIELDS,$str1.$str2.$str3.$str4.$str5.$str6.$str7.$str8);
			$output=curl_exec($c);

			//parse_str($output,$response);

			$_POST['response']=json_decode($output);
			if($_POST['response']->comment->id){
				$_POST['ok']=true;
			}
			$_POST['output']=$output;
			
			
			header("Content-Type: application/json");
			echo json_encode($_POST);
		}
	}
	die();
	
}
function mta_blacklist(){
	if(!is_wp_admin())return;
	if($id=mta_get_attachment_id_by_url($_POST['guid'])){
		$opts=mta_getopts();
		$file=get_attached_file($id);
		$sid=sha1($file);

		$_POST['sid']=$sid;
		$_POST['items']=implode(',',$_POST['items']);

		
		$c=curl_init();
		curl_setopt($c,CURLOPT_URL,$opts['neo4j']);
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($c,CURLOPT_POST,true);
		curl_setopt($c,CURLOPT_POSTFIELDS,$_POST);
		curl_setopt($c,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($c,CURLOPT_SSL_VERIFYHOST,false);
		$output=curl_exec($c);

		$_POST['ok']=true;

		$_POST['output']=$output;

		header("Content-Type: application/json");
		echo json_encode($_POST);
	}
	die();
}
/**
 * http://frankiejarrett.com/get-an-attachment-id-by-url-in-wordpress/
 * Return an ID of an attachment by searching the database with the file URL.
 *
 * First checks to see if the $url is pointing to a file that exists in
 * the wp-content directory. If so, then we search the database for a
 * partial match consisting of the remaining path AFTER the wp-content
 * directory. Finally, if a match is found the attachment ID will be
 * returned.
 *
 * @param string $url The URL of the image (ex: http://mysite.com/wp-content/uploads/2013/05/test-image.jpg)
 * 
 * @return int|null $attachment Returns an attachment ID, or null if no attachment is found
 */
function mta_get_attachment_id_by_url( $url ) {
	// Split the $url into two parts with the wp-content directory as the separator
	$parsed_url  = explode( parse_url( WP_CONTENT_URL, PHP_URL_PATH ), $url );

	// Get the host of the current site and the host of the $url, ignoring www
	$this_host = str_ireplace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );
	$file_host = str_ireplace( 'www.', '', parse_url( $url, PHP_URL_HOST ) );

	// Return nothing if there aren't any $url parts or if the current host and $url host do not match
	if ( ! isset( $parsed_url[1] ) || empty( $parsed_url[1] ) || ( $this_host != $file_host ) ) {
		return;
	}

	// Now we're going to quickly search the DB for any attachment GUID with a partial path match
	// Example: /uploads/2013/05/test-image.jpg
	global $wpdb;

	// DHR Added regex to match thumbnails against the original record
	$parsed_url[1]=preg_replace('/(-[0-9]+x[0-9]+)(\.[a-z]+)$/i','$2',$parsed_url[1]);

	$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}posts WHERE guid LIKE %s;", '%'.$parsed_url[1] ) );

	#echo "SELECT ID FROM {$wpdb->prefix}posts WHERE guid LIKE {$parsed_url[1]}";

	// Returns null if no attachment is found
	return $attachment[0];
}
function is_wp_admin(){
    $currentUser = wp_get_current_user();
    return in_array('administrator', $currentUser->roles);
}
function mta_social_connected($network){
	// Looks for user_meta field i.e. instagram_connected
	$uid=get_current_user_id();
	$field="{$network}_connected";
	if(get_user_meta($uid,$field,true)==true)return true;
	return false;
}


if(!function_exists('mta_admin')){
	function mta_admin(){
		// header
		$opts=mta_getopts();
		?>
		<style>
		.ng table{
			border-collapse:collapse;
		}
		.ng table td,th{
			padding:0.5em;
		}
		.ng table th{
			text-align:left;
			font-size:1.2em;
			padding-top:2em;
		}
		</style>
		<div class="wrap ng">
			<div id="icon-options-general" class="icon32"><br/></div>
			<h2 style="font-family:Calibri,sans-serif;">Sharc</h2>

			<form method="post">
				<table>
					<tr>
						<th>Server configuration</th>
					</tr>
					<tr>
						<td>Neo4J Path</td>
						<td><input name="mtaopts[neo4j]" value="<?php echo $opts['neo4j']?>"/></td>
					</tr>
					<tr>
						<th>Flickr configuration</th>
					</tr>
					<tr>
						<td>API Key</td>
						<td><input name="mtaopts[flickr_key]" value="<?php echo $opts['flickr_key']?>"/></td>
					</tr>
					<tr>
						<td>API Secret</td>
						<td><input name="mtaopts[flickr_secret]" value="<?php echo $opts['flickr_secret']?>"/></td>
					</tr>
					<tr>
						<th>Instagram configuration</th>
					</tr>
					<tr>
						<td>Client ID</td>
						<td><input name="mtaopts[instagram_id]" value="<?php echo $opts['instagram_id']?>"/></td>
					</tr>
					<tr>
						<td>Client Secret</td>
						<td><input name="mtaopts[instagram_secret]" value="<?php echo $opts['instagram_secret']?>"/></td>
					</tr>
					<tr>
						<th></th>
					</tr>
					<tr>
						
						<td><input class="button button-primary" type="submit" value="Save changes"/></td><td></td>
					</tr>
				</table>
			</form>
		<?php
			/*
			*/
			$query_images_args = array(
				'post_type' => 'attachment', 
				'post_mime_type' =>'image', 
				'post_status' => 'inherit', 
				'posts_per_page' => -1,
			);

			$query_images = new WP_Query( $query_images_args );
			$urls = array();
			$source=$_SERVER['HTTP_HOST'];
			echo '<table><tr>';
			$i=1;
			foreach ( $query_images->posts as $image) {
				$sha1=sha1(get_attached_file($image->ID));
				#print_r($image);
				echo '<td>';
				echo '<img src="'.wp_get_attachment_thumb_url($image->ID).'"/></td/><td valign=top> ';
				$info=mta_neostats($sha1,$source);
				echo '<strong>'.$image->post_title.'</strong><br/><br/>Flickr:'.$info->flickr.' images<br/>Instagram: '.$info->instagram.' images</td>';
				if($i==3){
					echo '</tr><tr>';
					$i=1;
				}else{
					$i++;
				}
				#flush();
			}
			echo '</tr></table>';
			?>
			</div>
		</div>
		<?php
	}
}

if(!function_exists('mta_neostats')){
	function mta_neostats($sid,$source){
		#echo '<pre>';
		$opts=get_option('mtaopts');

		$postfields=array(
			'action'=>'mta-stats',
			'sid'=>$sid,
			'source'=>$source
		);
		#print_r($postfields);
		#echo '</pre>';

		$c=curl_init();
		curl_setopt($c,CURLOPT_URL,$opts['neo4j']);
		curl_setopt($c,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($c,CURLOPT_POST,true);
		curl_setopt($c,CURLOPT_POSTFIELDS,$postfields);
		curl_setopt($c,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($c,CURLOPT_SSL_VERIFYHOST,false);
		$output=curl_exec($c);

		return json_decode($output);
		
	}
}
if(!function_exists('mta_getopts')){
	function mta_getopts(){
		if($_POST['mtaopts']&&is_wp_admin())update_option('mtaopts', $_POST['mtaopts']);
		$opts=get_option('mtaopts');
		return $opts;
	}
}

if(function_exists('add_action')){
	// Menu
	function mta_to_admin(){add_options_page('Sharc', 'Sharc', 'manage_options', 'Sharc', 'mta_admin');}
	add_action('admin_menu', 'mta_to_admin');

	// Custom checkbox
	/* Fire our meta box setup function on the post editor screen. */
	add_action( 'load-post.php', 'mta_post_meta_boxes_setup' );
	add_action( 'load-post-new.php', 'mta_post_meta_boxes_setup' );
	/* Meta box setup function. */
	function mta_post_meta_boxes_setup() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', 'mta_add_post_meta_boxes' );

		/* Save post meta on the 'save_post' hook. */
		add_action( 'save_post', 'mta_save_post_class_meta', 10, 2 );
	}
	function mta_add_post_meta_boxes() {
		
		// Posts
		add_meta_box(
			'mta-post-sharc',							// Unique ID
			esc_html__( 'Sharc enabled?', 'example' ),	// Title
			'mta_post_class_meta_box',					// Callback function
			'post',										// Admin page (or post type)
			'side',										// Context
			'default'									// Priority
		);
		// Pages
		add_meta_box(
			'mta-post-sharc',							// Unique ID
			esc_html__( 'Sharc enabled?', 'example' ),	// Title
			'mta_post_class_meta_box',					// Callback function
			'page',										// Admin page (or post type)
			'side',										// Context
			'default'									// Priority
		);
	}

	/* Display the post meta box. */
	function mta_post_class_meta_box( $object, $box ) { ?>

	  <?php wp_nonce_field( basename( __FILE__ ), 'mta_post_class_nonce' ); ?>

	  <p>
		<label for="mta-post-sharc"><?php _e( "Yes, load Sharc on this page.", 'example' ); ?></label>
		<input class="widefat" type="checkbox" name="mta-post-sharc" id="mta-post-sharc" <?php echo get_post_meta( $object->ID, 'mta_post_sharc', true )?'checked="checked"':'';?> size="30" />
	  </p>
	<?php }


	function mta_save_post_class_meta( $post_id, $post ) {

	  /* Verify the nonce before proceeding. */
	  if ( !isset( $_POST['mta_post_class_nonce'] ) || !wp_verify_nonce( $_POST['mta_post_class_nonce'], basename( __FILE__ ) ) )
		return $post_id;

	  /* Get the post type object. */
	  $post_type = get_post_type_object( $post->post_type );

	  /* Check if the current user has permission to edit the post. */
	  if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
		return $post_id;

	  /* Get the posted data and sanitize it for use as an HTML class. */
	  $new_meta_value = ( isset( $_POST['mta-post-sharc'] ) ? sanitize_html_class( $_POST['mta-post-sharc'] ) : '' );

	  /* Get the meta key. */
	  $meta_key = 'mta_post_sharc';

	  /* Get the meta value of the custom field key. */
	  $meta_value = get_post_meta( $post_id, $meta_key, true );

	  /* If a new meta value was added and there was no previous value, add it. */
	  if ( $new_meta_value && '' == $meta_value )
		add_post_meta( $post_id, $meta_key, $new_meta_value, true );

	  /* If the new meta value does not match the old value, update it. */
	  elseif ( $new_meta_value && $new_meta_value != $meta_value )
		update_post_meta( $post_id, $meta_key, $new_meta_value );

	  /* If there is no new meta value but an old value exists, delete it. */
	  elseif ( '' == $new_meta_value && $meta_value )
		delete_post_meta( $post_id, $meta_key, $meta_value );
	}
}
?>