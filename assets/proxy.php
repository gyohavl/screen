<?php
date_default_timezone_set('Europe/Prague');

if (!file_exists(__DIR__ . '/config.php')) {
	file_put_contents(__DIR__ . '/config.php', "<?php
return array(
	'owm_key' => '<openweathermap-api-key>',
	'debug' => true
);
");
}

$config = include(__DIR__ . '/config.php');
error_reporting($config['debug'] ? E_ALL : 0);
$sites = array(
	'rss' => 'https://www.gyohavl.cz/aktuality?action=atom',
	'owm' => 'http://api.openweathermap.org/data/2.5/weather?lat=49.8465503&lon=18.1733089&lang=cz&units=metric&appid='
		. (isset($config['owm_key']) ? $config['owm_key'] : ''),
	'suplovani' => '../data/right/suplobec.htm'
);
$firstImageNumber = 2;

if (!empty($_GET['get'])) {
	$key = $_GET['get'];
	if (array_key_exists($key, $sites)) {
		echo format(getContent($sites[$key]), $key);
	} else if ($key == 'images') {
		header('Content-Type: text/plain');
		$location = str_replace(' ', '%20', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/data/left/');
		for ($i = $firstImageNumber; $i < 100; $i++) {
			$headers = get_headers($location . $i . '.png', 1);
			if ($headers['Content-Type'] != 'image/png') {
				break;
			} else {
				$etag = isset($headers['ETag']) ? '?etag=' . trim($headers['ETag'], '"') : '';
				echo "$location$i.png$etag\n";
			}
		}
	} else if ($key == 'nameday') {
		echo getNameDay();
	}
}

function getContent($url) {
	if (strpos($url, '://') === false) {
		return file_get_contents($url);
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

function format($html, $key) {
	switch ($key) {
		case 'suplovani':
			return formatSuplovani($html);
		case 'owm':
			return formatOWM($html);
		case 'rss':
			return formatRSS($html);
		default:
			return $html;
	}
}

function formatOWM($input) {
	$data = json_decode($input);

	if (isset($data->weather[0]->id) && isset($data->main->temp)) {
		$temp = $data->main->temp;
		$temp = round($temp, 1);
		$temp = ($temp == 0) ? 0 : $temp; // prevent negative zero
		$temp = str_replace(array('.', '-'), array(',', '&minus;'), $temp);
		return "<i class=\"wi wi-owm-{$data->weather[0]->id}\"></i>$temp ??C";
	}
}

function formatRSS($xml) {
	$simplexml = simplexml_load_string($xml);
	$jsonxml = json_decode(json_encode($simplexml));
	$result = '';

	if (isset($jsonxml->channel->item)) {
		foreach ($jsonxml->channel->item as $item) {
			if (isset($item->title)) {
				$result .= '<div class="title">' . $item->title . '</div>';
				if (isset($item->description) && (array)$item->description && preg_replace('/\xc2\xa0|\s/', '', $item->description)) {
					$result .= '<div class="description">' . insertNbsp(strip_tags($item->description)) . '</div>';
				}
			}
		}
	}

	return $result;
}

function insertNbsp($text) {
	return preg_replace('/(?<=[\s(])([kvszaiou])\s/i', "$1&nbsp;", $text);
}

function formatSuplovani($html) {
	$delimiter = ';!;';
	$months = array("M??s??ce", "ledna", "??nora", "b??ezna", "dubna", "kv??tna", "??ervna", "??ervence", "srpna", "z??????", "????jna", "listopadu", "prosince");
	$currentDate = date('j') . ". " . $months[date('n')] . $delimiter;

	if ($html) {
		if (strpos($html, 'charset=windows-1250') !== false) {
			$html = iconv('windows-1250', 'UTF-8', $html);
		}

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
			$date = $currentDate . "$day. {$months[$month]}";
		} else {
			$date = $currentDate;
		}

		$classes = $html;
		if (strpos($classes, '<tr><td class="td_supltrid_3" width="11%"><p>  ') !== false) {
			$classes = formatBoth($classes, true);
			$classes = preg_replace("/<td>(\d)\.\s?hod<\/td>/", "<td>$1.&nbsp;hodina</td>", $classes);
			$classes = preg_replace('/<td colspan=\"7\">([^<]*?)(\d)\.-\s(\d)\.\shod\s(.+?)<\/td>/', '<td>$2.???$3. hod.</td><td colspan="6">$1$4</td>', $classes);
			$classes = preg_replace('/<td colspan=\"7\">([^<]*?)(\d)\.\,\s(\d)\.\shod\s(.+?)<\/td>/', '<td>$2. a $3. hod.</td><td colspan="6">$1$4</td>', $classes);
			$classes = preg_replace('/<td colspan=\"7\">([^<]*?)(\d)\.\shod\s(.+?)<\/td>/', '<td>$2. hodina</td><td colspan="6">$1$3</td>', $classes);
			$classes = preg_replace("/skup /", "skupina ", $classes);
			$classes = preg_replace("/Odpad??/", "odpad??", $classes);
		} else {
			$classes = '';
		}

		$teachers = $html;
		if (strpos($teachers, '<tr><td class="td_suplucit_3" width="27%"><p>  ') !== false) {
			$teachers = formatBoth($teachers, false);
			$teachers = preg_replace("/(\d?\d:\d\d) - (\d?\d:\d\d) dohled/", "$1???$2 dohled", $teachers);
			$teachers = preg_replace("/<td>(\d)\.\s?hod<\/td>/", "<td>$1.&nbsp;hodina</td>", $teachers);
			$teachers = preg_replace("/<td colspan=\"5\">(.*? dohled .*?)<\/td><td>&nbsp;<\/td>/", '<td colspan="6">$1</td>', $teachers);
			$teachers = preg_replace("/(\d\.),(\d\.)/", "$1 a $2", $teachers);
			$teachers = preg_replace("/(\d\. a \d\.)(\S)/", "$1 $2", $teachers);
			$teachers = preg_replace("/\s-\s/", ' ??? ', $teachers);
			$teachers = preg_replace("/(\d)\. t????dn??/", '$1t????dn??', $teachers);
			$teachers = preg_replace("/(\d)\. ([ABC])/", '$1.$2', $teachers);
		} else {
			$teachers = '';
		}

		$html = $date . $delimiter . $classes . $teachers;
	} else {
		$html = $currentDate . $delimiter;
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
	$html = preg_replace("/(\d\w?)\.\s?hod([^\w\.])/", "$1.&nbsp;hod.$2", $html);
	$html = preg_replace("/Chl(\W)/", "chl.$1", $html);
	$html = preg_replace("/D??v(\W)/", "d??v.$1", $html);
	$html = preg_replace("/(\d?\d\.)(\d?\d\.)/", '$1&nbsp;$2,', $html);
	$html = preg_replace("/<td>&nbsp;<\/td>/", '<td></td>', $html);
	$html = preg_replace("/\s?-\s/", ' ??? ', $html);
	$html = preg_replace("/(\.,)(\d\.)(\w)/", '$1 $2 $3', $html);
	return $html;
}

function getNameDay() {
	// https://github.com/vaniocz/svatky-vanio-cz/blob/master/api/index.php
	$names = [
		1 => [1 => 'Nov?? rok', 'Karina', 'Radmila', 'Diana', 'Dalimil', 'T??i kr??lov??', 'Vilma', '??estm??r', 'Vladan', 'B??etislav', 'Bohdana', 'Pravoslav', 'Edita', 'Radovan', 'Alice', 'Ctirad', 'Drahoslav', 'Vladislav', 'Doubravka', 'Ilona', 'B??la', 'Slavom??r', 'Zden??k', 'Milena', 'Milo??', 'Zora', 'Ingrid', 'Ot??lie', 'Zdislava', 'Robin', 'Marika'],
		[1 => 'Hynek', 'Nela a Hromnice', 'Bla??ej', 'Jarmila', 'Dobromila', 'Vanda', 'Veronika', 'Milada', 'Apolena', 'Mojm??r', 'Bo??ena', 'Slav??na', 'V??nceslav', 'Valent??n', 'Ji??ina', 'Ljuba', 'Miloslava', 'Gizela', 'Patrik', 'Old??ich', 'Lenka', 'Petr', 'Svatopluk', 'Mat??j', 'Liliana', 'Dorota', 'Alexandr', 'Lum??r', 'Horym??r'],
		[1 => 'Bed??ich', 'Ane??ka', 'Kamil', 'Stela', 'Kazim??r', 'Miroslav', 'Tom????', 'Gabriela', 'Franti??ka', 'Viktorie', 'And??la', '??eho??', 'R????ena', 'R??t a Matylda', 'Ida', 'Elena a Herbert', 'Vlastimil', 'Eduard', 'Josef', 'Sv??tlana', 'Radek', 'Leona', 'Ivona', 'Gabriel', 'Mari??n', 'Emanuel', 'Dita', 'So??a', 'Ta????na', 'Arno??t', 'Kvido'],
		[1 => 'Hugo', 'Erika', 'Richard', 'Ivana', 'Miroslava', 'Vendula', 'He??man a Herm??na', 'Ema', 'Du??an', 'Darja', 'Izabela', 'Julius', 'Ale??', 'Vincenc', 'Anast??zie', 'Irena', 'Rudolf', 'Val??rie', 'Rostislav', 'Marcela', 'Alexandra', 'Ev????nie', 'Vojt??ch', 'Ji????', 'Marek', 'Oto', 'Jaroslav', 'Vlastislav', 'Robert', 'Blahoslav'],
		[1 => 'Sv??tek pr??ce', 'Zikmund', 'Alexej', 'Kv??toslav', 'Klaudie', 'Radoslav', 'Stanislav', 'Den v??t??zstv??', 'Ctibor', 'Bla??ena', 'Svatava', 'Pankr??c', 'Serv??c', 'Bonif??c', '??ofie', 'P??emysl', 'Aneta', 'Nata??a', 'Ivo', 'Zby??ek', 'Monika', 'Emil', 'Vladim??r', 'Jana', 'Viola', 'Filip', 'Valdemar', 'Vil??m', 'Maxim', 'Ferdinand', 'Kamila'],
		[1 => 'Laura', 'Jarmil', 'Tamara', 'Dalibor', 'Dobroslav', 'Norbert', 'Iveta', 'Medard', 'Stanislava', 'Gita', 'Bruno', 'Antonie', 'Anton??n', 'Roland', 'V??t', 'Zbyn??k', 'Adolf', 'Milan', 'Leo??', 'Kv??ta', 'Alois', 'Pavla', 'Zde??ka', 'Jan', 'Ivan', 'Adriana', 'Ladislav', 'Lubom??r', 'Petr a Pavel', '????rka'],
		[1 => 'Jaroslava', 'Patricie', 'Radom??r', 'Prokop', 'Cyril a Metod??j', 'Jan Hus', 'Bohuslava', 'Nora', 'Drahoslava', 'Libu??e a Am??lie', 'Olga', 'Bo??ek', 'Mark??ta', 'Karol??na', 'Jind??ich', 'Lubo??', 'Martina', 'Drahom??ra', '??en??k', 'Ilja', 'V??t??zslav', 'Magdal??na', 'Libor', 'Krist??na', 'Jakub', 'Anna', 'V??roslav', 'Viktor', 'Marta', 'Bo??ivoj', 'Ign??c'],
		[1 => 'Oskar', 'Gustav', 'Milu??e', 'Dominik', 'Kristi??n', 'Old??i??ka', 'Lada', 'Sob??slav', 'Roman', 'Vav??inec', 'Zuzana', 'Kl??ra', 'Alena', 'Alan', 'Hana', 'J??chym', 'Petra', 'Helena', 'Ludv??k', 'Bernard', 'Johana', 'Bohuslav', 'Sandra', 'Bartolom??j', 'Radim', 'Lud??k', 'Otakar', 'August??n', 'Evel??na', 'Vlad??na', 'Pavl??na'],
		[1 => 'Linda a Samuel', 'Ad??la', 'Bronislav', 'Jind??i??ka', 'Boris', 'Boleslav', 'Reg??na', 'Mariana', 'Daniela', 'Irma', 'Denisa', 'Marie', 'Lubor', 'Radka', 'Jolana', 'Ludmila', 'Nad????da', 'Kry??tof', 'Zita', 'Oleg', 'Matou??', 'Darina', 'Berta', 'Jarom??r', 'Zlata', 'Andrea', 'Jon????', 'V??clav', 'Michal', 'Jeron??m'],
		[1 => 'Igor', 'Ol??vie a Oliver', 'Bohumil', 'Franti??ek', 'Eli??ka', 'Hanu??', 'Just??na', 'V??ra', '??tefan a S??ra', 'Marina', 'Andrej', 'Marcel', 'Ren??ta', 'Ag??ta', 'Tereza', 'Havel', 'Hedvika', 'Luk????', 'Michaela', 'Vendel??n', 'Brigita', 'Sabina', 'Teodor', 'Nina', 'Be??ta', 'Erik', '??arlota a Zoe', 'Jid????', 'Silvie', 'Tade????', '??t??p??nka'],
		[1 => 'Felix', 'Pam??tka zesnul??ch', 'Hubert', 'Karel', 'Miriam', 'Lib??na', 'Saskie', 'Bohum??r', 'Bohdan', 'Ev??en', 'Martin', 'Benedikt', 'Tibor', 'S??va', 'Leopold', 'Otmar', 'Mahulena', 'Romana', 'Al??b??ta', 'Nikola', 'Albert', 'Cec??lie', 'Klement', 'Em??lie', 'Kate??ina', 'Artur', 'Xenie', 'Ren??', 'Zina', 'Ond??ej'],
		[1 => 'Iva', 'Blanka', 'Svatoslav', 'Barbora', 'Jitka', 'Mikul????', 'Ambro??', 'Kv??toslava', 'Vratislav', 'Julie', 'Dana', 'Simona', 'Lucie', 'L??die', 'Radana', 'Alb??na', 'Daniel', 'Miloslav', 'Ester', 'Dagmar', 'Nat??lie', '??imon', 'Vlasta', 'Adam a Eva', '1. sv??tek v??no??n??', '??t??p??n', '??aneta', 'Bohumila', 'Judita', 'David', 'Silvestr'],
	];

	if (isset($names[date('n')][date('j')])) {
		return $names[date('n')][date('j')];
	}
}
