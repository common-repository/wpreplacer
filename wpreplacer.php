<?php

/* 
 * Plugin Name:   WP Replacer
 * Version:       1.0.3
 * Plugin URI:    http://wpmass.com/wpreplacer/
 * Description:   Automatic replace the post content
 * Author:        ykjsw
 * Author URI:    http://wpmass.com
 */

error_reporting(0);

define( 'WR_CACHE_DIR', 'cache' );

$wpr_settings = get_option('wpr_settings');

if( $wpr_settings['wpr_level'] == '' ){	$wpr_settings['wpr_level'] = 3; }
if( $wpr_settings['wpr_percent'] == '' ){$wpr_settings['wpr_percent'] = 10; }
if( $wpr_settings['wpr_key'] == '' ){$wpr_settings['wpr_key'] = file_get_contents('http://api.wpmass.com/wpreplacer.php?do=getafreekey');update_option('wpr_settings', $wpr_settings); }

function wpContentReplacer( $post_content ){
	global $post, $wpr_settings;
	if( $wpr_settings['wpr_enable'] != '1' ){
		return $post_content;
	}
	$cdfile = getPostCacheDir().'/'.$post->ID.'_'.$wpr_settings['wpr_level'].'_'.$wpr_settings['wpr_percent'].'.wpr';
	if( $wpr_settings['wpr_test'] != 1 && file_exists($cdfile) && filesize($cdfile) > 2 ){
		$post_content = file_get_contents($cdfile);
	}elseif( strlen($wpr_settings['wpr_key']) == 32 ){
		if( $wpr_settings['wpr_newords'] ){
			foreach ( $wpr_settings['wpr_newords'] AS $nw ){
				if( $nw = trim($nw) ){
					$post_content = str_ireplace(' '.$nw.' ', ' <nw>'.$nw.'</nw> ', $post_content);
				}
			}
		}
		$content = @file_post_contents('http://api.wpmass.com/wpreplacer.php?key='.$wpr_settings['wpr_key'].'&level='.$wpr_settings['wpr_level'].'&percent='.$wpr_settings['wpr_percent'].'&test='.$wpr_settings['wpr_test'].'&post='.urlencode($post_content));
		if( $content && strstr($content, 'WPREPLACER: REPLACE SUCCESS') ){
			$_t = explode('WPREPLACER: REPLACE SUCCESS', $content, 2);
			preg_match('/WPREPLACER: REPLACE SUCCESS(.*?)WPREPLACER: REPLACE END/is', $content, $metch);
			if( $metch[1] ){
				$post_content = $metch[1];
				$post_content = str_replace(array('<nw>','</nw>'), '', $post_content);
				if( $wpr_settings['wpr_test'] != 1 ){
					file_put_contents($cdfile, $post_content);
				}
			}
		}
	}
	$post->post_content = $post_content;
	return $post_content;
}

function getPostCacheDir(){
	global $post;
	$pdir = WP_PLUGIN_DIR.'/wpreplacer';
	if( $post->ID > 0 ){
		$did = ceil( $post->ID / 1000 );
		if( !is_dir($pdir.'/'.WR_CACHE_DIR.'/'.$did) ){
			if( !is_dir($pdir.'/'.WR_CACHE_DIR) ){
				mkdir($pdir.'/'.WR_CACHE_DIR, 0777);
			}
			mkdir($pdir.'/'.WR_CACHE_DIR.'/'.$did, 0777);
		}
		return $pdir.'/'.WR_CACHE_DIR.'/'.$did;
	}
}

function wpReplacerPages(){
	add_menu_page('wpReplacer Setting', 'wpReplacer', 10, 'wpReplacer', 'wpReplacerSeting');
}

function wpReplacerSeting(){
	global $wpr_settings;
	if( ($_POST['checkkey'] || !isset($wpr_settings['wpr_checkvalue']) ) && strlen($_POST['wpr_key']) == 32 ){
		$wpr_settings['wpr_checkvalue'] = @file_get_contents('http://api.wpmass.com/wpreplacer.php?do=checkkey&key='.$_POST['wpr_key']);
	}
	if( $_POST['cleancache'] ){
		wpRcleancache( WP_PLUGIN_DIR.'/wpreplacer/'.WR_CACHE_DIR );
		echo '<br />cache files cleaned<br />';
	}
	if( strlen($_POST['wpr_key']) == 32 && in_array($_POST['wpr_level'], array(1,2,3)) ){
		if( $_POST['wpr_newords'] ){
			$_POST['wpr_newords'] = trim(str_replace(array('\'','"', '\\'), '', $_POST['wpr_newords']));
			$newords = explode("\n", $_POST['wpr_newords']);
			$newords = array_map('trim', $newords);
		}
		$wpr_settings['wpr_key'] = $_POST['wpr_key'];
		$wpr_settings['wpr_level'] = $_POST['wpr_level'];
		$wpr_settings['wpr_enable'] = $_POST['wpr_enable'];
		$wpr_settings['wpr_test'] = $_POST['wpr_test'];
		$wpr_settings['wpr_percent'] = $_POST['wpr_percent'];
		if( $newords ){
			$wpr_settings['wpr_newords'] = $newords;
		}
		update_option('wpr_settings', $wpr_settings);
	}
	$checked[$wpr_settings['wpr_level']] = 'checked';
	if( $wpr_settings['wpr_percent'] == '' ){
		$wpr_settings['wpr_percent'] = 10;
	}
	$percentlist = '';
	for ( $i = 10; $i>= 1; $i -- ){
		$slt = $wpr_settings['wpr_percent'] == $i ? 'selected' : '';
		$percentlist .= '<option value="'.$i.'" '.$slt.'>'.(10*$i).'%</option>';
	}
	if( $wpr_settings['wpr_newords'] ){
		$newords = '';
		foreach ( $wpr_settings['wpr_newords'] AS $nw ){
			$newords .= $nw."\n";
		}
	}
	?>
	<div class="wrap">
		<h2>Wordpress Replacer Settings</h2>
		<form method="post" action="">
		 <table border="0" cellpadding="10" cellspacing="10">
		 	<tr><td>wpReplacer Key:</td><td><input id="wpr_key" type="text" name="wpr_key" value="<?php echo $wpr_settings['wpr_key'] ?>" size="48" /> <font color="red"><b><?php echo $wpr_settings['wpr_checkvalue']; ?></b></font> <input type="submit" name="checkkey" value="Check Key" /></td></tr>
		 	<tr><td valign="top">Replace Level:</td><td><input <?php echo $checked[1] ?> type="radio" value="1" name="wpr_level" /> Good  &nbsp;&nbsp;&nbsp;&nbsp;<i>replace the most terms</i><br /><input type="radio" value="2" name="wpr_level" <?php echo $checked[2] ?> /> Better &nbsp;&nbsp;&nbsp;<i>fewer replacements, higher quality</i><br /><input type="radio" value="3" <?php echo $checked[3] ?> name="wpr_level" /> Best  &nbsp;&nbsp;&nbsp;&nbsp;<i>fewest replacements, higher quality</i></td></tr>
		 	<tr><td>Rewrite Percent:</td><td><select name="wpr_percent"><?php echo $percentlist; ?></select> <i>the percentage of replaceable words you would like to rewrite</i></td></tr>
			<tr><td>Replace Enable:</td><td><input type="checkbox" name="wpr_enable" value="1" <?php if($wpr_settings['wpr_enable']=='1'){echo 'checked';} ?> /> <i>checked to start replace</i></td></tr>
		 	<tr><td>Test Mode Enable:</td><td><input type="checkbox" name="wpr_test" value="1" <?php if($wpr_settings['wpr_test']=='1'){echo 'checked';} ?> /> <i>checked for test</i></td></tr>
			<tr><td valign="top">Negative Words:<br />words that will not be replaced<br />one word per line</td><td><textarea name="wpr_newords" cols="20" rows="4"><?php echo $newords; ?></textarea></td></tr>
		 	<tr><td><input name="cleancache" value="Clean Cache" type="submit" /></td><td><input class="saveChanges" name="save" value="Save Settings" type="submit"></td></tr>
		 </table>
		 </form>
		 <br />
		 <?php
		 if( $wpr_settings['wpr_checkvalue'] != '1' ){
				echo '<form id="payforwpr" method="post" action="https://www.paypal.com/cgi-bin/webscr" target="_blank">
				<font color=red><b>
				You get a free key &nbsp;&nbsp;&nbsp;
				<input type="hidden" value="_xclick" name="cmd"/>
				<input type="hidden" value="mp3seek@hotmail.com" name="business"/>
				<input type="hidden" value="wpReplacer key fee" name="item_name"/>
				<input type="hidden" value="1" id="item_number" name="item_number"/>
				<input type="hidden" value="2" name="rm"/>
				<input type="hidden" value="http://wpmass.com/thankyou.php" name="return"/>
				<input type="hidden" value="http://wpmass.com/" name="cancel_return"/>
				<input type="hidden" value="'.$wpr_settings['wpr_key'].'" name="custom"/>
				<input type="hidden" value="15" name="amount"/>
				<a href="javascript:document.forms[\'payforwpr\'].submit();">Get you full license key for only $15/year (Unlimited sites within one ip)</a>
				</b></font>
				<img src="https://www.paypal.com/en_US/i/scr/pixel.gif" alt=""/>
				</form><br />Only good level can be used with the free key, also the rewrite percent will be locked at 50%. powered by <a href="http://wpmass.com" target="_blank">wpmass</a><br><br><script src="http://wpmass.com/notice.js"></script>';
		 }
		 ?>
	</div>
	<?php
}

function wpRcleancache( $dir ){
    if(!$dh = @opendir($dir)) { 
        return; 
    }
    while (false !== ($obj = readdir($dh))) { 
        if($obj == '.' || $obj == '..') { 
            continue; 
        }
        if (!@unlink($dir . '/' . $obj)) { 
            wpRcleancache($dir.'/'.$obj); 
        } 
    }
    closedir($dh); 
    return; 
}

function file_post_contents($url) {
    $url = parse_url($url);

    if (!isset($url['port'])) {
      if ($url['scheme'] == 'http') { $url['port']=80; }
      elseif ($url['scheme'] == 'https') { $url['port']=443; }
    }

    $url['protocol'] = $url['scheme'].'://';
    $eol="\r\n";

    $headers =  "POST ".$url['path']." HTTP/1.1".$eol. 
                "Host: ".$url['host'].$eol. 
                "Referer: ".$url['protocol'].$url['host'].$url['path'].$eol. 
                "Content-Type: application/x-www-form-urlencoded".$eol. 
                "Content-Length: ".strlen($url['query']).$eol.
                $eol.$url['query'];
    $fp = fsockopen($url['host'], $url['port'], $errno, $errstr, 30); 
	if($fp) {
		fputs($fp, $headers);
		$result = '';
		while(!feof($fp)) { $result .= fgets($fp, 1024); }
		fclose($fp);
		return $result;
    }
}


add_filter('the_content', 'wpContentReplacer', 1, 1);
add_action('admin_menu', 'wpReplacerPages');