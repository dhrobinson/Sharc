<?php
header('Access-Control-Allow-Origin:*');
#error_reporting(E_ALL);
$debug=false;
$cached=$cache=true;
$places=$imagesa=$imagesb=$imagesc=$books=array();

use Everyman\Neo4j\Client,
    Everyman\Neo4j\Index\NodeIndex,
    Everyman\Neo4j\Index\RelationshipIndex,
    Everyman\Neo4j\Index\NodeFulltextIndex,
    Everyman\Neo4j\Node,
    Everyman\Neo4j\Batch;

use Instagram\Instagram;
use DHRobinson\Goodreads\Goodreads;

include('lib/phpFlickr.php');
include('lib/FoursquareAPI.class.php');
require_once 'bootstrap.php';

$flickrkey='61c91dd83f9f49b8f87c833b694832a8';
$flickr_secret='bb163c19af8c0db1';

$instagramkey='f542480d6d704ab6bf63110d74d4b72e';

$neo= new Everyman\Neo4j\Client();
$gr = new DHRobinson\Goodreads\Goodreads(array('apiKey'=>'3BBleZ5kdeJQL062Yu66Q','apiSecret'=>'YXOXJmenQjeb3FQYqXV0R2WtowKa1ugQ6h1geRdsfM'));
#$fl = new phpFlickr('61c91dd83f9f49b8f87c833b694832a8','bb163c19af8c0db1',true);
$in = new Instagram('f542480d6d704ab6bf63110d74d4b72e');
$fs = new FoursquareAPI("XGBDBKZKQYFNPLOVISNOWGAXDFGSHKKOLXFCNU2AOV0BNQET", "1DFPJ2DWDMRFV3JRXUNHJBPNLA0PKIDUAZJYDUYDNWZKBQ1T");

$db = new mysqli("localhost", "mta", "FPdcUHdfG8MwDpBQ", "mta");

if($debug){
	if(!@$_POST['uri']){
		$_POST['uri']='http://localhost/wpdev1/wp-content/uploads/2015/01/WP_20150201_10_00_52_Pro.jpg';
		$_POST['id']='testuri';
		$_POST['tags']='lasvegas,vegas,desert';
		$_POST['nocache']=true;
	}
	if(@$_GET['action'])$_POST['action']=$_GET['action'];
}

if(@$_POST['action']=='mta-go'&&@$_POST['uri']){
	/*
	$filename=sha1($_POST['uri']);
	if($file=file_get_contents($_POST['uri'])){
		$filename=sha1($file).'-'.$filename;
		file_put_contents($filename,$file);
	}
	*/
	if(@$_POST['nocache']=='true')$cache=false;

	if(@$_POST['exif'])$_POST['exif']=json_decode($_POST['exif'],true);

	$search_args=array(
		'tags'=>explode(',',@$_POST['tags']),
		'isbn'=>@$_POST['isbn'],
	);

	if(@$_POST['exif']['GPS']){
		$gps=exif2gps($_POST['exif']['GPS']);
		$search_args['lat']=$gps['lat'];
		$search_args['lng']=$gps['lng'];
	}
	$search_args['source']=parse_url($_POST['uri'],PHP_URL_HOST);
	$search_args['sid']=@$_POST['id'];
	

	// Hash before setting debug
	$request_hash=sha1(serialize($search_args));
	if($debug)$search_args['debug']=true;

	/*
	*/
	// Cached?
	#if($_POST['nocache'])$cache=false;
	if($cache&&$results=cache('check',$request_hash)){
		return return_results_json($results);
	}

	// Create/update intentional
	$source=json_decode(json_encode($search_args));
	foreach($source as $k=>$v)if(is_object($v)||is_array($v))$source->$k=serialize($v);
	$intentional=neo_update($source);


	// New
	if($search_args){
		if(0){
			$resultscopy=$results=json_decode(json_encode(search($search_args)));
			
			// Serialize multidimensional
			foreach($results as $type=>$records){
				foreach($records as $rkey=>$record){
					foreach($record as $key=>$value){
						// If the value is an array, serialize it for storage. 
						// This can be expanded later if required.
						if(is_array($value)||is_object($value)){
							$results->{$type}[$rkey]->{$key}=serialize($value);
							$results->{$type}[$rkey]->_type=$type;
						}
					}
				}
			}

			/*
			START n=node(*) 
							WHERE has (n.id)
							and has(n.source)
							and n.id="914427492077682057_1662817318"
							and n.source="instagram"
							RETURN n

			*/

			// Create/update unintentional
			foreach($results as $type=>$items){
				foreach($items as $key=>$record){
					#print_r($record);
					
					(array)$unintentional[]=neo_update($record);
				}
			}

			// Create relationships

			for($i=0;$i<count(@$unintentional);$i++){
				$intentional->relateTo($unintentional[$i],'FOUND')->setProperty('date',date('Y-m-d H:i:s'))->save();
			}
		}

		$object = new stdClass();

		/*
		*/
		$queryString = "START n=node({nodeId}) MATCH (n)-[:FOUND]->(x) WHERE NOT (n)-[:BLOCKED]->(x) AND x.source='instagram' RETURN count(DISTINCT x.sid), x LIMIT 500";
		$query = new Everyman\Neo4j\Cypher\Query($neo, $queryString, array('nodeId' => $intentional->getId()));
		$result = $query->getResultSet();
		if($result){
			foreach($result as $row) {
				$output->{$row['x']->getProperty('_type')}[]=json_decode(json_encode($row['x']->getProperties()));
			}
		}
		$queryString = "START n=node({nodeId}) MATCH (n)-[:FOUND]->(x) WHERE NOT (n)-[:BLOCKED]->(x) AND x.source='flickr' RETURN count(DISTINCT x.sid), x LIMIT 500";
		$query = new Everyman\Neo4j\Cypher\Query($neo, $queryString, array('nodeId' => $intentional->getId()));
		$result = $query->getResultSet();
		if($result){
			foreach($result as $row) {
				$output->{$row['x']->getProperty('_type')}[]=json_decode(json_encode($row['x']->getProperties()));
			}
		}
		/*
		$queryString = "START n=node({nodeId}) MATCH (n)-[:FOUND]->(x) WHERE x.source='foursqure' RETURN count(DISTINCT x.sid), x LIMIT 10";
		$query = new Everyman\Neo4j\Cypher\Query($neo, $queryString, array('nodeId' => $intentional->getId()));
		$result = $query->getResultSet();
		if($result){
			foreach($result as $row) {
				$output->{$row['x']->getProperty('_type')}[]=json_decode(json_encode($row['x']->getProperties()));
			}
		}
		$queryString = "START n=node({nodeId}) MATCH (n)-[:FOUND]->(x) WHERE x.source='goodreads' RETURN count(DISTINCT x.sid), x LIMIT 10";
		$query = new Everyman\Neo4j\Cypher\Query($neo, $queryString, array('nodeId' => $intentional->getId()));
		$result = $query->getResultSet();
		if($result){
			foreach($result as $row) {
				$output->{$row['x']->getProperty('_type')}[]=json_decode(json_encode($row['x']->getProperties()));
			}
		}
		*/
		/*
		*/
		/*
		foreach($output as $type=>$items){
			for($i=0;$i<count($items);$i++){
				foreach($items[$i] as $key=>$val){
					$output[$type][$i][$key]=maybe_unserialize($val);
					
				}
			}
		}
		*/
		// select items related to source
		// 
		// collate into results[type][items]
		//
		// return

		// Requested
		//if($output->images)shuffle($output->images);

		// Ranking

		$scores=$tmpimages=$newimages=array();
		require_once('badwords.php');

		// Score images
		foreach(@$output->images as $image){
			$newtags=array();
			// Positive matches
			$score=match_tags(unserialize($image->tags),$search_args['tags']);
			// Negative matches
			$score-=match_tags(unserialize($image->tags),$badwords);

			$scores[$image->sid]=$score;
			$tmpimages[$image->sid]=$image;
			$tmpimages[$image->sid]->score=$score;

			//Sanitise tags
			$tmptags=unserialize($image->tags);
			if(!is_array($tmptags)){
				for($i=0;$i<count($tmptags->tag);$i++){
					(array)$newtags[]=$tmptags->tag[$i]->_content;
				}
				$tmpimages[$image->sid]->tags=serialize($newtags);
			}
			unset($newtags);

			$tags=unserialize($tmpimages[$image->sid]->tags);
			sort($tags);
			$tmpimages[$image->sid]->tags=serialize($tags);
		}

		// Sort scores
		arsort($scores);
		/*
		foreach($scores as $k=>$v){
			arsort($scores[$k]);
		}
		*/

		// Rebuilt output
		foreach($scores as $k=>$v){
			/*
			foreach($v as $key=>$val){
				$newimages[$k][]=$tmpimages[$k][$key];
			}
			*/
			$newimages[]=$tmpimages[$k];
		}

		$outputimages=array();
		/*
		foreach($newimages as $k=>$v){
			$outputimages=array_merge($outputimages,array_slice($v,0,18));
		}
		*/
		$outputimages=array_slice($newimages,0,18);
		@$output->images=$outputimages;

		// Do we want to cache these results?
		if($cached)cache('update',$request_hash,$output,null);

		return_results_json($output);
	}
}

if(@$_POST['action']=='mta-blacklist'&&@$_POST['items']&&@$_POST['sid']){
	if(!preg_match('/^[a-z0-9]{40}/',$_POST['sid']))die();
	$_POST['items']=explode(',',$_POST['items']);
	for($i=0;$i<count(@$_POST['items']);$i++){
		$queryString="MATCH a,b WHERE a.sid = '{$_POST['sid']}' AND b.sid = '{$_POST['items'][$i]}' CREATE (a)-[r:BLOCKED]->(b)";
		echo $queryString."\n";
		$query = new Everyman\Neo4j\Cypher\Query($neo, $queryString);
		$result = $query->getResultSet();
	/*

	*/
	}
	print_r($_POST);
}
#if($_GET['action']=='mta-stats')$_POST=$_GET;
if($_POST['action']=='mta-stats'&&$_POST['sid']&&$_POST['source']){
	if(!preg_match('/^[a-z0-9]{40}/',$_POST['sid']))die();
	if(!preg_match('/^[a-z0-9\.-]+/',$_POST['source']))die();

	$info=array('flickr'=>0,'instagram'=>0);
	$cypher="MATCH (n)-[:FOUND]->(x) WHERE n.sid='".$_POST['sid']."' AND NOT (n)-[:BLOCKED]->(x) AND n.source='".$_POST['source']."' and x.source='flickr'  RETURN count(DISTINCT x.sid) as total";
	echo $cypher;
	
	$query = new Everyman\Neo4j\Cypher\Query($neo, $cypher);
	$result = $query->getResultSet();
	if($result){
		$info['flickr']=$result[0]['total'];
	}

	$cypher="MATCH (n)-[:FOUND]->(x) WHERE n.sid='".$_POST['sid']."' AND NOT (n)-[:BLOCKED]->(x) AND n.source='".$_POST['source']."' and x.source='instagram'  RETURN count(DISTINCT x.sid) as total";
	
	$query = new Everyman\Neo4j\Cypher\Query($neo, $cypher);
	$result = $query->getResultSet();
	if($result){
		$info['instagram']=$result[0]['total'];
	}

	
	header("Content-Type: application/json");
	echo json_encode($info);;
	exit;

	#$cypher="MATCH (n)-[:FOUND]->(x) WHERE n.sid='{$_POST['sid']}' AND NOT (n)-[:BLOCKED]->(x) AND n.source='dhrobinson.com' and x.source='flickr'  RETURN count(DISTINCT x.sid) as total";
}
		/*
		*/
function search($args){
	#echo 'searched';
	global $in,$fs,$fl,$gr;
	global $flickrkey,$instagramkey;
	global $places,$imagesa,$imagesb,$imagesc,$books;
	
	/*
	*/
	// Instagram via individual tags
	// $imagesa
	for($i=0;$i<count($args['tags']);$i++){
		$res=$in->getTagMedia($args['tags'][$i]);
		if(@$res->data){
			#print_r($res->data);
			for($j=0;$j<count($res->data);$j++){
				$image=$res->data[$j];
				$image->source='instagram';
				$image->sid=$image->id;
				unset($image->id);
				//$image->thumbnail=
				$imagesa[]=$image;
			}
		}
	}
	
	// Instagram multi_url
	for($i=0;$i<count($args['tags']);$i++){
		#$res=$in->getTagMedia($args['tags'][$i]);
		$urls[]='https://api.instagram.com/v1/tags/'.$args['tags'][$i].'/media/recent?client_id='.$instagramkey;
	}
	rolling_curl($urls,'add_instagram_a');
	
	
	// Flickr multi_url
	if(count(@$args['tags'])){
		// Flickr multitags
		// Sanitise tags
		for($i=0;$i<count($args['tags']);$i++){
			(array)$flickrtags[]=urlencode($args['tags'][$i]);
		}
		// $imagesb
		global $urlcache,$images;
		// Flickr multitags
		// $imagesb
		#$res=$fl->photos_search(array('tags'=>implode(',',$args['tags']),'tag_mode'=>'any'));
		$url='https://api.flickr.com/services/rest/?method=flickr.photos.search&api_key='.$flickrkey.'&tags='.implode(',',$flickrtags).'&tag_mode=any&format=json&nojsoncallback=1';

		
		$callback='url_cache';
		rolling_curl(array($url),$callback);

		$photos=json_decode($urlcache);

		if(@$photos->stat='ok'){

			$count=count(@$photos->photos->photo);

			for($i=0;$i<$count;$i++){
				(array)$urls[]='https://api.flickr.com/services/rest/?method=flickr.photos.getInfo&api_key='.$flickrkey.'&photo_id='.$photos->photos->photo[$i]->id.'&format=json&nojsoncallback=1';
			}
		}
		rolling_curl($urls,'add_flickr');


		/*
		$res=$fl->photos_search(array('tags'=>implode(',',$args['tags']),'tag_mode'=>'any'));
		#print_r($res['photo']);
		#$res=json_decode($res->response);
		if($count=count($res['photo'])){
			for($i=0;$i<$count,$i<10;$i++){
				$info=$fl->photos_getInfo($res['photo'][$i]['id']);
				$image=json_decode(json_encode($res['photo'][$i]));
				@$image->images->thumbnail->url='http://c1.staticflickr.com/'.$info['photo']['farm'].'/'.$info['photo']['server'].'/'.$info['photo']['id'].'_'.$info['photo']['secret'].'_n.jpg';
				@$image->caption->text=strip_tags($info['photo']['description']['_content']);
				@$image->info=json_decode(json_encode($info));
				$image->link=$info['photo']['urls']['url'][0]['_content'];
				$image->source='flickr';
				$image->type='image';
				$image->searchtags=implode(',',$args['tags']);
				$image->sid=$image->id;
				unset($image->id);
				(array)$imagesb[]=$image;
			}
		}
		*/
	}
	/*
	*/

	// Foursquare place
	if(isset($args['lat'])&&isset($args['lng'])){
		$res=$fs->GetPublic('venues/search',array('ll'=>"{$args['lat']},{$args['lng']}"));
		for($i=0;$i<3;$i++){
			if($res->response->venues[$i]){
				(array)$args['fs_id'][]=$res->response->venues[$i]->id;
				$res->response->venues[$i]->source='foursquare';
				$res->response->venues[$i]->sid=$res->response->venues[$i]->id;
				unset($res->response->venues[$i]->id);
				$places[]=$res->response->venues[$i];
			}
		}
	}

	// Instagram via location; within 100m
	// LAT/LNG or Foursquare Location ID
	// TODO: Refactor fs/latlng
	/*
	*/


	if((isset($args['lat'])&&isset($args['lng']))||isset($args['fs_id'])){
		if(@$args['fs_id']){
			for($i=0;$i<count($args['fs_id']);$i++){
				$locations=$in->searchLocation(array('distance'=>100,'foursquare_v2_id'=>$args['fs_id'][$i]));
				
				$url='https://api.instagram.com/v1/locations/search?foursquare_v2_id='.$args['fs_id'][$i].'&distance=100&client_id='.$instagramkey;
				rolling_curl(array($url),'url_cache');
				$locations=json_decode($urlcache);

				if($location=@$locations->data[0]->id){
					
					$urls[]='https://api.instagram.com/v1/locations/'.$location.'/media/recent?client_id='.$instagramkey;

					/*
					$res=$in->getLocationMedia($location);
					if($res->data){
						#print_r($res->data);
						for($j=0;$j<count($res->data);$j++){
							$image=$res->data[$j];
							$image->source='instagram';
							$image->sid=$image->id;
							unset($image->id);
							(array)$imagesc[]=$image;
						}
					}
					*/
				}
			}
		}else{

			
			$url='https://api.instagram.com/v1/locations/search?lat='.$args['lat'].'&lng='.$args['lng'].'&distance=100&client_id='.$instagramkey;
			rolling_curl(array($url),'url_cache');
			$locations=json_decode($urlcache);

			for($i=0;$i<3;$i++){
				if($location=$locations->data[$i]->id){
					
					$urls[]='https://api.instagram.com/v1/locations/'.$location.'/media/recent?client_id='.$instagramkey;
				}
			}

			/*
			$locations=$in->searchLocation(array('lat'=>$args['lat'],'lng'=>$args['lng'],'distance'=>100));

			for($i=0;$i<3;$i++){
				if($location=$locations->data[$i]->id){
					
					$urls[]='https://api.instagram.com/v1/locations/'.$args['tags'][$i].'/media/recent?client_id='.$instagramkey;
					$res=$in->getLocationMedia($location);
					if($res->data){
						#print_r($res->data);
						for($j=0;$j<count($res->data);$j++){
							$image=$res->data[$j];
							$image->source='instagram';
							$image->sid=$image->id;
							unset($image->id);
							(array)$imagesc[]=$image;
						}
					}
				}
			}
			rolling_curl($urls,'add_instagram_c');
			*/
		}
		rolling_curl($urls,'add_instagram_c');
	}

	/*
	if(@$args['isbn']){
		$book=json_decode($gr->bookShowByISBN($args['isbn']));
		$book->book->source='goodreads';
		$book=$book->book;
		$book->sid=$book->id;
		unset($book->id);
		
		if($book){
			$books[]=$book;
		}
	}
	*/

	$images=array_merge($imagesa,$imagesb,$imagesc);

	#$return=array('images'=>$images,'places'=>$places,'books'=>$books);
	$return=array('images'=>$images);
	if($args['debug']&&@$debug)$return['debug']=$debug;
	return json_decode(json_encode($return));
}
function match_tags($tags,$list){
	// $tags are on the unintentional, $list is the tags on the intentional
	$total=0;

	if(is_array($tags)){
		for($i=0;$i<count($tags);$i++){
			if(in_array($tags[$i],$list))$total++;
		}
	}else{
		for($i=0;$i<count($tags->tag);$i++){
			if(in_array($tags->tag[$i]->_content,$list))$total++;
		}
	}
	return $total;
}
function cache($method='check',$request_hash,$items='',$time='-7 days'){
	global $db;

	if($method=='check'){
		// if exists & in range, return
		$expires=strtotime($time);
		$sql="SELECT items FROM cache WHERE hash='$request_hash' AND updated>$expires";
		$res = $db->query($sql);
		if($res&&$res->num_rows){
			$res->data_seek(0);
			$row = $res->fetch_assoc();
			return unserialize($row['items']);
		}else{
			return false;
		}
	}
	if($method=='update'){
		// otherwise save & return
		$now=time();
		$serialized=addslashes(serialize($items));
		$sql="REPLACE INTO cache SET hash='$request_hash',items='$serialized',updated=$now";

		$res = $db->query($sql);

		return $items;
	}

	return false;
}

function instagram_location_media($args){
	$in->searchLocation(array('distance'=>100,'foursquare_v2_id'=>$args['fs_id']));
}

function return_results_json($array){
	#print_r($array);
	foreach($array as $type=>$objects){
		for($i=0;$i<count($objects);$i++){
			foreach($objects[$i] as $key=>$val){
				$array->{$type}[$i]->{$key}=maybe_unserialize($val);
			}
		}
		#foreach($object as $k=>$v){
		#	if($v[0]=='{')$array->{$type}[$k]->{$key}=serialize($value);
		#}
	}
	$response = json_encode($array);
	header("Content-Type: application/json");
	echo $response;
	exit;
}
function exif2gps($gps){
	if(!is_array($gps['GPSLatitude']))return null;
	if(!$gps['GPSLatitudeRef'])return null;
	if(!is_array($gps['GPSLongitude']))return null;
	if(!$gps['GPSLongitudeRef'])return null;

	$geo=array();
	$geo['lat']=getGps($gps['GPSLatitude'],$gps['GPSLatitudeRef']);
	$geo['lng']=getGps($gps['GPSLongitude'],$gps['GPSLongitudeRef']);
	return $geo;
}
function neo_update($node){
	// Update or insert 
	global $neo;
	$item=null;

	/*
		// Update?
		if($node->sid&&$node->source){
			$queryString='MATCH (n) WHERE n.source="'.$node->source.'" AND n.sid="'.$node->sid.'" RETURN n';
		}else{
			#print_r($node);
		}
		

		$query = new Everyman\Neo4j\Cypher\Query($neo, $queryString);
		$result = $query->getResultSet();

		if($node->source=='localhost'){
			#die();
		}
		
		// Try to loop
		foreach($result as $row){
			#$record = $row['n'];
			$item=$neo->getNode($row['n']->getId());
		}
		#@print_r($resultArray);

		
		b_start();
		if($item)print_r($item);
		ob_end_clean();

		if(!is_object($item)){
			#die('nothing found:'.$queryString);
			#echo 'made node instead';
			$item=$neo->makeNode();
		}
		/*else{
			#echo $queryString;
			#print_r($result);
			#echo $resultArray[0]->getProperty('id');
			$item=$neo->getNode($record->getId());
		}
		#print_r($item);
		
		
		// Not found OR created(!)
		if(!@$item){
			#die('Could not find '.$queryString);
			#$item=$neo->makeNode();
		}
	*/#
	#echo $node->sid."\n";
	
	$queryString='MATCH (n) WHERE n.source="'.$node->source.'" AND n.sid="'.$node->sid.'" RETURN n';
	#echo $queryString."\n";

	$query = new Everyman\Neo4j\Cypher\Query($neo, $queryString);
	$result = $query->getResultSet();

	foreach($result as $row){
		$item=$neo->getNode($row['n']->getId());
	}
	if(!$item){
		#echo 'no item, apprently';
		$item=$neo->makeNode();
		$item->setProperty('sid',$node->sid);
		$item->setProperty('source',$node->source);
		$item->save();
	} 

	// Update properties
	foreach($node as $key=>$value){
		if($key!='sid'&&$key!='source')$item->setProperty($key,$value);
		#echo "$key:$value\n";
	}
	$item->save();

	$label = $neo->makeLabel($node->source);
	$item->addLabels(array($label));

	return $item;
}
// http://ksankaran.com/wp/2013/06/11/exif-metadata-extract-gps-info/
function getGps($exifCoord, $hemi) {
    $degrees = count($exifCoord) > 0 ? gps2Num($exifCoord[0]) : 0;
    $minutes = count($exifCoord) > 1 ? gps2Num($exifCoord[1]) : 0;
    $seconds = count($exifCoord) > 2 ? gps2Num($exifCoord[2]) : 0;
    $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;
    return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}
function gps2Num($coordPart) {
    $parts = explode('/', $coordPart);
    if (count($parts) <= 0)
        return 0;
    if (count($parts) == 1)
        return $parts[0];
    return floatval($parts[0]) / floatval($parts[1]);
}

// Cheers, Wordpress
/**
 * Unserialize value only if it was serialized.
 *
 * @since 2.0.0
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	return $original;
}

/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since 2.0.5
 *
 * @param string $data   Value to check to see if was serialized.
 * @param bool   $strict Optional. Whether to be strict about the end of the string. Default true.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized( $data, $strict = true ) {
	// if it isn't a string, it isn't serialized.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
 	if ( 'N;' == $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	if ( $strict ) {
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
	} else {
		$semicolon = strpos( $data, ';' );
		$brace     = strpos( $data, '}' );
		// Either ; or } must exist.
		if ( false === $semicolon && false === $brace )
			return false;
		// But neither must be in the first X characters.
		if ( false !== $semicolon && $semicolon < 3 )
			return false;
		if ( false !== $brace && $brace < 4 )
			return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if ( $strict ) {
				if ( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
			} elseif ( false === strpos( $data, '"' ) ) {
				return false;
			}
			// or else fall through
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			$end = $strict ? '$' : '';
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
	}
	return false;
}


// add_instagram callback for rolling_curl
function add_instagram_a($content){
	// $content is an array of images
	global $imagesa,$args;

	$res=json_decode($content);
	if(@$res->data){
		#print_r($res->data);
		for($j=0;$j<count($res->data);$j++){
			$image=$res->data[$j];
			$image->source='instagram';
			$image->sid=$image->id;
			//$image->thumbnail=
			$imagesa[]=$image;
		}
	}
}
// add_instagram callback for rolling_curl
function add_instagram_c($content){
	// $content is an array of images
	global $imagesc,$args;

	$res=json_decode($content);
	if(@$res->data){
		#print_r($res->data);
		for($j=0;$j<count($res->data);$j++){
			$image=$res->data[$j];
			$image->source='instagram';
			$image->sid=$image->id;
			//$image->thumbnail=
			$imagesc[]=$image;
		}
	}
}

// add_flickr callback for rolling_curl
function add_flickr($content){
	global $imagesb,$args;

	$info=json_decode($content);
	if(@$info->photo){
		$image=json_decode(json_encode($info->photo));

		@$image->images->thumbnail->url='http://c1.staticflickr.com/'.$info->photo->farm.'/'.$info->photo->server.'/'.$info->photo->id.'_'.$info->photo->secret.'_n.jpg';
		@$image->caption->text=strip_tags($info->photo->description->_content);
		@$image->info=$info;
		$image->link=$info->photo->urls->url[0]->_content;
		$image->source='flickr';
		$image->type='image';
		@$image->searchtags=implode(',',$args['tags']);
		$image->sid=$image->id;

		$imagesb[]=$image;
	}

}
function url_cache($str){
	// Stores the contents of the last call
	global $urlcache;
	$urlcache=$str;
}

// https://github.com/joshfraser/rolling-curl
// http://www.onlineaspect.com/2009/01/26/how-to-use-curl_multi-without-blocking/
function rolling_curl($urls, $callback, $custom_options = null) {


    // make sure the rolling window isn't greater than the # of urls
    $rolling_window = 5;
    $rolling_window = (sizeof($urls) > $rolling_window) ? sizeof($urls) : $rolling_window;

    $master = curl_multi_init();
    $curl_arr = array();

    // add additional curl options here
    $std_options = array(CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_MAXREDIRS => 5);
    $options = ($custom_options) ? ($std_options + $custom_options) : $std_options;

    // start the first batch of requests
    for ($i = 0; $i < $rolling_window; $i++) {
        $ch = curl_init();
        @$options[CURLOPT_URL] = $urls[$i];
        curl_setopt_array($ch,$options);
        curl_multi_add_handle($master, $ch);
    }

    do {
        while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
        if($execrun != CURLM_OK)
            break;
        // a request was just completed -- find out which one
        while($done = curl_multi_info_read($master)) {
            $info = curl_getinfo($done['handle']);
            if ($info['http_code'] == 200)  {
                $output = curl_multi_getcontent($done['handle']);

                // request successful.  process output using the callback function.
                $callback($output);

                // start a new request (it's important to do this before removing the old one)
                $ch = curl_init();
                @$options[CURLOPT_URL] = $urls[$i++];  // increment i
                curl_setopt_array($ch,$options);
                curl_multi_add_handle($master, $ch);

                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $done['handle']);
            } else {
                // request failed.  add error handling.
            }
        }
    } while ($running);
    
    curl_multi_close($master);
    return true;
}