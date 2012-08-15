<?php

/***************************************************************************
 *   Copyright (C) 2009-2012 by http://dizel-by.livejournal.com            *
 *   http://dizel-by.livejournal.com/47660.html                            *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 *   This program is distributed in the hope that it will be useful,       *
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of        *
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         *
 *   GNU General Public License for more details.                          *
 *                                                                         *
 *   You should have received a copy of the GNU General Public License     *
 *   along with this program; if not, write to the                         *
 *   Free Software Foundation, Inc.,                                       *
 *   59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.             *
 ***************************************************************************/

$versions = array(
	"2012-08-15" => "Добавлена почта Словакии, багфиксы (thanks to Andrew Shadura), отключена почта Китая",
	"2012-06-30" => "Трекинг посылок из Сингапура через 4PX",
	"2012-05-21" => "добавлены почта Сингапура и USPS",
	"2012-03-01" => "RSS в тестовом режиме. Пожелания принимаются :)",
	"2012-03-01" => "добавлены Български пощи",
	"2012-01-24" => "добавлены ParcelForce и почта Испании",
	"2012-01-23" => "обновление почты России",
	"2011-03-15" => "обновление почты Китая",
	"2011-02-16" => "добавлена почта Китая",
	"2011-02-14" => "Значительно увеличена скорость работы",
	"2011-02-10" => "обновление белпочты",
	"2010-03-07" => "обработка EMS от HK post",
	"2010-03-06" => "обработка нескольких кодов, информация о выходе новых версий",
	"2010-03-05" => "обновление белпочты",
	"2010-02-20" => "обновление белпочты",
	"2009-07-08" => "добавлен ещё один тип белорусской почты (теперь их 3), улучшена выдача HK post",
	"2009-06-16" => "первый запуск",
	);

error_reporting(0);
set_time_limit(0);

function showUpdates($url) {
	global $versions;
	echo @file_get_contents("http://dizel.homeip.net/post/index.php?version=".max(array_keys($versions)));
}

function compareDates($a1, $a2) {

	if ($a1['date'] < $a2['date']) return -1;
	if ($a1['date'] > $a2['date']) return 1;
	if ($a1['step'] < $a2['step']) return -1;
	if ($a1['step'] > $a2['step']) return 1;
	return 0;
}

function file_post_contents($url, $data, $headers = "") {
	$data = http_build_query($data);
	$context_options = array(
		"http" => array(
			"method" => "POST",
			"timeout" => 10,
			"header" => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: ". strlen($data). "\r\n" . $headers,
			"content" => $data
			)
		);
	$context = stream_context_create($context_options);
	return file_get_contents($url, false, $context);
}

function getUS_post($id) {
	if (substr($id, -2) != "US")
		return array();
	$html = file_get_contents("https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1={$id}&qtc_senddate1=&qtc_zipcode1=");
	if (!preg_match_all("~<div class=\"td-status\">(.+)</div>.+<div class=\"td-date-time\">(.+)</div>.+<div class=\"td-location\">(.+)</div>~Ums", $html, $tds))
		return array();
	foreach ($tds[1] as $k => $v)
		$data[] = array(
			"date" => date('Y-m-d H:i', strtotime(trim(strip_tags($tds[2][$k])))),
			"action" => trim(strip_tags($tds[1][$k])),
			"src" => trim(strip_tags($tds[3][$k])),
			);
	return $data;
}

function getSG_post($id) {
	if (substr($id, -2) != "SG")
		return array();

	$html = file_get_contents("http://en.4px.cc/Track.html?objTrackDto.hawbCode={$id}&outerservice=en");
	$html = str_replace("&nbsp;", "", $html);
	if (!preg_match_all("~<td class=\"track_right[56]\">(.+)</td>~Ums", $html, $tds))
		return array();

	for ($i = 0; $i < count($tds[1]); $i+= 4)
		$data[] = array(
			"date" => date('Y-m-d H:i:s', strtotime($tds[1][$i+0])),
			"src" => trim(strip_tags($tds[1][$i+2])),
			"action" => trim(strip_tags($tds[1][$i+3])),
			);
	return $data;
}

function getES_post($id) {
	if (substr($id, -2) != "ES")
		return array();
	$post = array("accion" => "LocalizaUno", "numero" => $id);
	$html = iconv("iso-8859-1", "utf-8", file_post_contents("http://www.correos.es/comun/Localizador/track.asp", $post));
	if (!preg_match('~<table id="Table2".+</table>~Ums', $html, $table))
		return array();
	preg_match_all("~<td[^>]*>(.*)</td>~Umsi", $table[0], $tds);
	$data = array();
	for($i = 3; $i < count($tds[1]); $i+=2)
		$data[] = array(
			"date" => date('Y-m-d', strtotime(str_replace("/", ".", $tds[1][$i]))),
			"action" => trim(strip_tags($tds[1][$i+1])),
			);
	return $data;
}

function getPF_post($id) {
	if (substr($id, -2) != "GB")
		return array();
	$html = file_get_contents("http://www.parcelforce.com/track-trace?trackNumber={$id}&page_type=parcel-tracking-details&parcel_number={$id}");
	if (!preg_match('~<table summary="Tracked parcel events">(.+)</table>~Ums', $html, $table))
		return array();

	if (!preg_match_all("~<td>(.*)</td>~Umsi", $table[1], $tds))
		return array();

	$data = array();
	for ($i = 0; $i < count($tds[0]); $i += 4) {
		$data[] = array(
			'date' => date('Y-m-d H:i', strtotime($tds[1][$i+0].' '.$tds[1][$i+1])),
			'src' => $tds[1][$i+2],
			'action' => $tds[1][$i+3],
			);
	}
	return $data;
}

function getBG_post($id) {
	if (substr($id, -2) != "BG")
		return array();
	$html = file_get_contents("http://www.bgpost.bg/IPSWebTracking/IPSWeb_item_events.asp?itemid={$id}&Submit=Submit");
	if (!preg_match_all("~<tr class=tabl\d>.+</tr>~Ums", $html, $trs))
		return array();
	$data = array();
	foreach ($trs[0] as $tr) {
		if (!preg_match_all("~<td[^>]*>(.*)</td>~Ums", $tr, $tds))
			continue;
		$data[] = array(
			'date' => date('Y-m-d H:i', strtotime($tds[1][0])),
			'src' => $tds[1][1].' ('.$tds[1][2].')',
			'action' => $tds[1][3],
			);
	}
	return $data;
}

/*
function getCN_post($id) {

	if (substr($id, -2) != "CN")
		return array();

	$data = array();
	$html = file_post_contents("http://track-chinapost.com/tracking_chinapost.php", array("itemNo" => $id));
	if (preg_match_all("~<tr>(.+)</tr>~Umsi", $html, $tr)) {
		unset($tr[1][0]);
		foreach ($tr[1] as $t) {
			if (preg_match_all("~<td[^>]*>(.*)</td>~Umsi", $t, $td)) {

				$data[] = array(
					'date' => $td[1][5],
					'src' => $td[1][4],
					'dst' => $td[1][3],
					'action' => $td[1][2],
					);
			}
		}
	}
	return $data;
}
*/

function getHK_post($id) {

	if (substr($id, -2) != "HK")
		return array();

	$data = array();
	$html = file_get_contents("http://app3.hongkongpost.com/CGI/mt/genresult.jsp?tracknbr=".$id);
	if (preg_match("~(The item \(.+) (on|as of) (\d+\-\w+\-\d+?)~Umsi", $html, $o)) {

		$src = "";
		$dst = "";
		if (preg_match("~Destination</span> \- (.+)</p>~Umsi", $html, $d)) {
			$src = "Hong Kong";
			$dst = $d[1];
		}

		$date = date('Y-m-d H:i', strtotime(str_replace("-", " ", $o[3])));
		$data[] =
			array(
				"date" => $date,
				"src" => $src,
				"dst" => $dst,
				"action" => str_replace("({$id})", "", $o[1]),
				);
	}

	if (preg_match("~Full status~i", $html)) {

		if (!preg_match('~<!-- #BeginParcelDetail -->\s*<tr>\s*<td><a href="[^\"]+">[^<]+</a></td>\s*<td>(.+)</td>\s*<td>(.+)</td>~Umsi', $html, $o))
			return $data;
	
		list(,$src,$dst) = $o;

		$html = file_get_contents("http://app3.hongkongpost.com/CGI/mt/e_detail.jsp?mail_type=parcel_ouw&tracknbr={$id}&localno={$id}");

		if (!preg_match_all("~<table class=\"detail\">.+</table>~Umsi", $html, $o))
			return $data;
	
		$html = $o[0][1];

		preg_match_all("~<td>(.*)</td>~Umsi", $html, $o);

		for($i = 0; $i < count($o[1]); $i+=3) {
			$date = date('Y-m-d H:i', strtotime($o[1][$i]));
			$action = $o[1][$i+2];
	  
			$data[] = array(
				"date" => $date,
				"src" => $src,
				"dst" => $dst,
				"action" => $action,
				"step" => $i,
				);
		}
	
	}
  
	return $data;

}

function getRU_post($id) {
	$url = "http://russianpost.ru/resp_engine.aspx?Path=rp/servise/ru/home/postuslug/trackingpo";

	$data = array(
		"searchsign" => 1,
		"BarCode" => $id,
		"entryBarCode" => '',
		);

	$html = file_post_contents($url, $data);
	$html = str_replace("Белоруссия", "Беларусь", $html);
	$data = array();
	if (preg_match_all("~<tr align=\"center\">(.+)</TR>~Umsi", $html, $tr)) {
		$tr = $tr[1];
		foreach ($tr as $i => $t) {
			if (!preg_match_all("~<TD[^>]*>(.*)</TD>~Umsi", $t, $tds))
				continue;
			$src = trim(str_replace("&nbsp;", " ", $tds[1][3]));
			$date = date('Y-m-d H:i', strtotime($tds[1][1]));
			$action = array();
			if (trim(str_replace("&nbsp;", " " ,$tds[1][0])))	$action[] = $tds[1][0];
			if (trim(str_replace("&nbsp;", " " ,$tds[1][4])))	$action[] = $tds[1][4];
			$action = implode(" - ", $action);
			$data[] = array(
				"date" => $date,
				"src" => $src,
				"dst" => $dst,
				"action" => $action,
				"step" => $i,
				);
		}
	}

	return $data;

}

function getBY_post($id) {
	$url = "http://search.belpost.by/ajax/search/";
	$html = file_post_contents($url, array("internal" => "2", "item" => $id), "X-Requested-With: XMLHttpRequest");

	$data = array();

	$step = 0;

	preg_match_all("~<table[^>]*>.+</table>~Ums", $html, $tables);
	foreach ($tables[0] as $html) {

		preg_match_all("~<td class=\"theader\">~Ums", $html, $headers);

		if (preg_match_all("~<td>(.*)</td>~Umsi", $html, $tds)) {

			if (count($headers[0]) == 7) {

				for ($i = 0; $i < count($tds[1]); $i+=5) {
					$date = date('Y-m-d H:i', strtotime($tds[1][$i+0]));
					$src = $tds[1][$i+2];
					$dst = '';
					$action = $tds[1][$i+1];
					$data[] = array(
						"date" => $date,
						"src" => $src,
						"dst" => $dst,
						"action" => $action,
						"step" => $step,
						);
					$step++;
				}

			}

			if (count($headers[0]) == 2) {
		
				for ($i = 0; $i < count($tds[1]); $i+=2) {
					$date = date('Y-m-d H:i', strtotime(preg_replace("~^(\d+)\.(\d+)\.(\d+) (\d+):(\d+):(\d+)$~", "\\3-\\2-\\1 \\4:\\5:\\6", $tds[1][$i])));
					$action = preg_replace("~^\d{2}\. ~", "", $tds[1][$i+1]);
		  
					$src = $dst = "";
					if (preg_match("~из \(.+\) (.+) в \(.+\) (.+)$~", $action, $p)) {
						$src = $p[1]; $dst = $p[2];
					}
		  
					$data[] = array(
						"date" => $date,
						"src" => $src,
						"dst" => $dst,
						"action" => $action,
						"step" => $step,
						);
					$step++;
		  
				}
			}
		}
	}
	return $data;

}

function getFunctions() {
	$result = array();
	$tmp = get_defined_functions();
	foreach ($tmp['user'] as $f)
		if (preg_match("~^get(.+)_post$~", $f))
			$result[] = $f;
	return $result;
}

if ($_GET['version']) {
	$news = "";
	foreach ($versions as $v => $title) {
		if ($v > $_GET['version'])
			$news .= "<li><b>".date('d.m.Y', strtotime($v))."</b><br/>{$title}</li>";
	}
	if ($news)
		$news = "<h1 style='display: inline'>Доступны обновления</h1> (<a href='http://dizel.homeip.net/post/index.php.txt'>Скачать</a>)<ul>".$news."</ul>";
  
	echo $news;exit;
	  
}

function getSK_post($id) {
	$url = "http://tandt.posta.sk/en?q=";
	$html = file_get_contents($url . $id);
	
	$data = array();
	$step = 0;
	preg_match_all("~<ul class=\"result-item\">.+</ul>~Ums", $html, $ul);
	foreach ($ul[0] as $html) {
		
		if (preg_match_all("~<li class=\"event\">(.*)</li>~Umsi", $html, $lis)) {
			
				for ($i = 0; $i < count($lis[1]); $i++) {
					preg_match("~<span class=\"event-day\">(.*)</span>~Umsi", $lis[0][$i], $date);
					$date = date('Y-m-d H:i', strtotime(preg_replace("~<.*>~", "", $date[1])));
					$src = '';
					$dst = '';
					
					preg_match("~<span class=\"event-name\">(.*)<span~Umsi", $lis[0][$i], $name);
					preg_match("~<span class=\"event-note\">(.*)</span~Umsi", $lis[0][$i], $note);
					$action = $name[1].$note[1];
					$data[] = array(
						"date" => $date,
						"src" => $src,
						"dst" => $dst,
						"action" => $action,
						"step" => $i,
						);
				}
		}
	}
	return $data;
}


$functions = getFunctions();
$ch = array();

if ($_GET['action'] == "part") {

	$part = strtolower($_GET['part']);
	$row = $_GET['id'];
	if (!preg_match("~[0-9A-z]+~", $row, $id))
		$data = array();
	else {
		$id = $id[0];
		
		if (array_search($part, $functions) !== false) {
			$data = array('id' => $row, "data" => call_user_func($part, $id));
		} 
	}

	echo serialize($data);exit;

}

$multi = function_exists("curl_multi_add_handle");
//$multi = 0;
$i = 0;
$url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
$time = microtime(true);

foreach (explode("\n", $_GET['id']) as $k => $row) {
	$row = trim($row);

	if (!preg_match("~[0-9A-z]+~", $row, $id))
		continue;

	$id = $id[0];

	$data[$row] = array();
	foreach ($functions as $f) {
		
		if (!$multi) {
			$t = time();
			if ($tmp = call_user_func($f, $id))
				$data[$row] = array_merge($data[$row], $tmp);
			//			echo $f.' - '.(time()-$t)."<br>\n";flush();
		}
		else {
			$ch[$i] = curl_init();
			curl_setopt($ch[$i], CURLOPT_URL, $url."?action=part&part={$f}&id=".urlencode($row));
			curl_setopt($ch[$i], CURLOPT_HEADER, 0);
			curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
			$i++;
		}
		
	}
	
}

if ($multi) {

	$mh = curl_multi_init();
	
	foreach ($ch as &$c) 
		curl_multi_add_handle($mh, $c);
	
	$running=null;
	
	do {
		usleep(50);
		curl_multi_exec($mh, $running);
	} while ($running > 0);
	
	foreach ($ch as &$c) {
		$tmp = unserialize(curl_multi_getcontent($c));
		$data[$tmp['id']] = array_merge($data[$tmp['id']], $tmp['data']);
		curl_multi_remove_handle($mh, $c);
	}

	curl_multi_close($mh);
	
}

$time = microtime(true)-$time;

foreach ($data as &$d)
	usort($d, 'compareDates');

if ($_GET['output'] == "serialized") {
	echo serialize($data);
	exit;
}

if ($_GET['output'] == "rss") {

	header("Content-type: application/rss+xml");
	$ids = implode(", ", array_keys($data));

?>
<?php echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>"; ?>
<rss version="2.0">
<channel>
	<link>http://<?=$_SERVER['SERVER_NAME'].htmlspecialchars($_SERVER['REQUEST_URI']);?></link>
	<title><![CDATA[ Огрегатырь: <?=$ids?>]]></title>
	<description></description>
<?php
	foreach ($data as $id => $rows) {
		foreach ($rows as $row) {
			$desc = $row['date']."<br/>";
			if ($row['src']) $desc .= "{$row['src']}";
			if ($row['dst']) $desc .= "-> {$row['dst']}";
			if ($row['action']) $desc .= "<br/>".strip_tags($row['action']);
			$desc = str_replace("<br/><br/>", "<br/>", $desc);
			echo "<item>";
			echo "<title><![CDATA[\n{$id}: {$row['action']}\n]]></title>";
			echo "<description><![CDATA[\n{$desc}\n]]></description>";
			echo "<guid isPermaLink=\"false\">track{$id}</guid>";
			echo "<pubDate>{$row['date']}</pubDate>";
			echo "</item>";
		}
	}
?>
</channel>
</rss>
<?php
	exit;
}

$id = $_GET['id'];

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru">
<head>
	<title>Огрегатырь</title>
	<link href="http://<?=$_SERVER['SERVER_NAME'].htmlspecialchars($_SERVER['REQUEST_URI'])?>&amp;output=rss" rel="alternate" type="application/rss+xml" title="RSS" />
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <style type="text/css">

* {
  font-size: 12px;
  font-family: tahoma, sans-serif;
}

h1 {
  font-size: 13px;
  margin-bottom: 0px;
}

ul {
 padding: 0px;
}
    
.updates {
    float: right; 
    width: 400px;
    font-size: 11px;
}

table.dataout {
    border-color: #000;
    border-width: 0 0 1px 1px;
    border-style: solid;
    border-collapse: collapse;
    margin-top: 3px;
}

table.dataout td
{
    border-color: #000;
    border-width: 1px 1px 0 0;
    border-style: solid;
    margin: 0;
    padding: 4px;
}

table.dataout th
{
    border-color: #000;
    border-width: 1px 1px 0 0;
    border-style: solid;
    margin: 0;
    padding: 4px;
    background-color: #999;
}

  </style>
</head>
<body>

  <div class="updates"><div style="text-align: right"><a href="mailto:dizel@tut.by">Связаться с автором</a></div></div>
  <?php if (!$_GET) : ?>
  <div class="updates"><?=showUpdates()?></div>
  <?php endif; ?>
  <form method="get" action="index.php">
    Коды отправлений (с комментариями через пробел):<br/>
    <textarea name="id" cols="50" rows="5"><?=htmlspecialchars($id)?></textarea><br/>
    <input type="submit" value="Поиск" />
  </form>

  <?php if ($id) : ?>
  <?php if ($data) : ?>
  <?php foreach ($data as $title => $rows) : ?>
  <h1><?=$title?></h1>
  <table class="dataout">
    <tr>
      <th>Дата/время</th>
      <th>Откуда</th>
      <th>Куда</th>
      <th>Операция</th>
    </tr>
    <?php foreach ($rows as $row) : ?>
    <tr>
      <td><?=str_replace(" 00:00", "", $row['date'])?></td>
      <td><?=$row['src']?></td>
      <td><?=$row['dst']?></td>
      <td><?=$row['action']?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endforeach; ?>
  <?php endif; ?>
  <?php if (!$data) echo "Ничего не найдено"; ?>
  <?php endif; ?>

	<font color="white"><?=$time?></font>
</body>