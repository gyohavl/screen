<?php
date_default_timezone_set('Europe/Prague');

$sites = array(
	'rss' => 'https://www.gyohavl.cz/aktuality?action=atom',
	'owm' => 'http://api.openweathermap.org/data/2.5/weather?lat=49.8465503&lon=18.1733089&lang=cz&units=metric&appid=327f641ce35e595aec70ba9f684c9764',
	'suplovani' => 'data/right/suplobec.htm'
);

if (!empty($_GET['get'])) {
	$key = $_GET['get'];
	if (array_key_exists($key, $sites)) {
		echo format(file_get_contents($sites[$key]), $key);
	} else if ($key == 'images') {
		header('Content-Type: text/plain');
		$location = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/data/left/';
		for ($i = 2; $i < 100; $i++) {
			$headers = get_headers($location . $i . '.png', 1);
			if ($headers['Content-Type'] != 'image/png') {
				break;
			} else {
				$etag = isset($headers['ETag']) ? '?etag=' . trim($headers['ETag'], '"') : '';
				echo "$location$i.png$etag\n";
			}
		}
	}
}

function format($html, $key) {
	switch ($key) {
		case 'suplovani':
			return formatSuplovani($html);
		default:
			return $html;
	}
}

function formatSuplovani($html) {
	if ($html) {
		$html = iconv('windows-1250', 'UTF-8', $html);
		$html = preg_replace('/[\n\r]/', '', $html);
		$html = preg_replace('/>\s+</', '><', $html);

		$date = $html;
		if (strpos($date, '<p class="textlarge_3">') !== false) {
			$date = explode('<p class="textlarge_3">', $date, 2);
			$date = explode('</p>', $date[1], 2);
			$date = $date[0];
			$date = preg_replace("/.+?\s(\d+\.\d+\.\d+)/", "$1", $date);
			$dateObj = DateTime::createFromFormat('j. n. Y', $date);
			$day = $dateObj->format('j');
			$month = $dateObj->format('n');
			$months = array("Měsíce", "ledna", "února", "března", "dubna", "května", "června", "července", "srpna", "září", "října", "listopadu", "prosince");
			$date = date('j') . ". " . $months[date('n')] . ";$day. {$months[$month]}";
		} else {
			$date = ';';
		}

		$classes = $html;
		if (strpos($classes, '<tr><td class="td_supltrid_3" width="11%"><p>  ') !== false) {
			$classes = formatBoth($classes, true);
			$classes = preg_replace("/<td>(\d)\.\s?hod<\/td>/", "<td>$1.&nbsp;hodina</td>", $classes);
			$classes = preg_replace('/<td colspan=\"7\">([^<]*?)(\d)\.-\s(\d)\.\shod\s(.+?)<\/td>/', '<td>$2.–$3. hod.</td><td colspan="6">$1$4</td>', $classes);
			$classes = preg_replace('/<td colspan=\"7\">([^<]*?)(\d)\.\,\s(\d)\.\shod\s(.+?)<\/td>/', '<td>$2. a $3. hod.</td><td colspan="6">$1$4</td>', $classes);
			$classes = preg_replace('/<td colspan=\"7\">([^<]*?)(\d)\.\shod\s(.+?)<\/td>/', '<td>$2. hodina</td><td colspan="6">$1$3</td>', $classes);
			$classes = preg_replace("/skup /", "skupina ", $classes);
			$classes = preg_replace("/Odpadá/", "odpadá", $classes);
		} else {
			$classes = '';
		}

		$teachers = $html;
		if (strpos($teachers, '<tr><td class="td_suplucit_3" width="27%"><p>  ') !== false) {
			$teachers = formatBoth($teachers, false);
			$teachers = preg_replace("/(\d?\d:\d\d) - (\d?\d:\d\d) dohled/", "$1–$2 dohled", $teachers);
			$teachers = preg_replace("/<td>(\d)\.\s?hod<\/td>/", "<td>$1.&nbsp;hodina</td>", $teachers);
			$teachers = preg_replace("/<td colspan=\"5\">(.*? dohled .*?)<\/td><td>&nbsp;<\/td>/", '<td colspan="6">$1</td>', $teachers);
			$teachers = preg_replace("/(\d\.),(\d\.)/", "$1 a $2", $teachers);
			$teachers = preg_replace("/(\d\. a \d\.)(\S)/", "$1 $2", $teachers);
			$teachers = preg_replace("/\s-\s/", ' – ', $teachers);
			$teachers = preg_replace("/(\d)\. třídní/", '$1třídní', $teachers);
			$teachers = preg_replace("/(\d)\. ([ABC])/", '$1.$2', $teachers);
		} else {
			$teachers = '';
		}

		$html = $date . ";" . $classes . $teachers;
	}

	return $html;
}

function formatBoth($html, $isClass) {
	$specific = $isClass ? 'class="td_supltrid_3" width="11%"' : 'class="td_suplucit_3" width="27%"';
	$html = preg_replace('/>>/', '&gt;&gt;', $html);
	$html = preg_replace('/<</', '&lt;&lt;', $html);
	$html = explode("<tr><td $specific><p>  ", $html, 2);
	$html = str_replace("<tr><td $specific><p>  ", '</tbody><tbody><tr><td><p>', $html[1]);
	$html = explode('</table>', $html, 2);
	$html = "<table><tbody><tr><td $specific><p>" . $html[0];
	$html .= '</tbody></table>';
	$html = preg_replace("/\swidth=\"\d+%\"/", "", $html);
	$html = preg_replace("/\sclass=\"td_suplucit_3\"/", "", $html);
	$html = preg_replace("/<\/*p>/", "", $html);
	$html = preg_replace("/(\d)\.\s?hod([^\w\.])/", "$1.&nbsp;hod.$2", $html);
	$html = preg_replace("/Chl(\W)/", "chl.$1", $html);
	$html = preg_replace("/Dív(\W)/", "dív.$1", $html);
	$html = preg_replace("/(\d?\d\.)(\d?\d\.)/", '$1&nbsp;$2,', $html);
	$html = preg_replace("/<td>&nbsp;<\/td>/", '<td></td>', $html);
	$html = preg_replace("/\s?-\s/", ' – ', $html);
	$html = preg_replace("/(\.,)(\d\.)(\w)/", '$1 $2 $3', $html);
	return $html;
}
