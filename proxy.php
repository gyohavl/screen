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
	'suplovani' => 'data/right/suplobec.htm'
);
$firstImageNumber = 2;

if (!empty($_GET['get'])) {
	$key = $_GET['get'];
	if (array_key_exists($key, $sites)) {
		echo format(file_get_contents($sites[$key]), $key);
	} else if ($key == 'images') {
		header('Content-Type: text/plain');
		$location = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/data/left/';
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
		return "<i class=\"wi wi-owm-{$data->weather[0]->id}\"></i>$temp °C";
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
				if (isset($item->description) && (array)$item->description) {
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
	$months = array("Měsíce", "ledna", "února", "března", "dubna", "května", "června", "července", "srpna", "září", "října", "listopadu", "prosince");
	$currentDate = date('j') . ". " . $months[date('n')] . $delimiter;

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
			$date = $currentDate . "$day. {$months[$month]}";
		} else {
			$date = $currentDate;
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
	$html = preg_replace("/Dív(\W)/", "dív.$1", $html);
	$html = preg_replace("/(\d?\d\.)(\d?\d\.)/", '$1&nbsp;$2,', $html);
	$html = preg_replace("/<td>&nbsp;<\/td>/", '<td></td>', $html);
	$html = preg_replace("/\s?-\s/", ' – ', $html);
	$html = preg_replace("/(\.,)(\d\.)(\w)/", '$1 $2 $3', $html);
	return $html;
}

function getNameDay() {
	// https://github.com/vaniocz/svatky-vanio-cz/blob/master/api/index.php
	$names = [
		1 => [1 => 'Nový rok', 'Karina', 'Radmila', 'Diana', 'Dalimil', 'Tři králové', 'Vilma', 'Čestmír', 'Vladan', 'Břetislav', 'Bohdana', 'Pravoslav', 'Edita', 'Radovan', 'Alice', 'Ctirad', 'Drahoslav', 'Vladislav', 'Doubravka', 'Ilona', 'Běla', 'Slavomír', 'Zdeněk', 'Milena', 'Miloš', 'Zora', 'Ingrid', 'Otýlie', 'Zdislava', 'Robin', 'Marika'],
		[1 => 'Hynek', 'Nela a Hromnice', 'Blažej', 'Jarmila', 'Dobromila', 'Vanda', 'Veronika', 'Milada', 'Apolena', 'Mojmír', 'Božena', 'Slavěna', 'Věnceslav', 'Valentýn', 'Jiřina', 'Ljuba', 'Miloslava', 'Gizela', 'Patrik', 'Oldřich', 'Lenka', 'Petr', 'Svatopluk', 'Matěj', 'Liliana', 'Dorota', 'Alexandr', 'Lumír', 'Horymír'],
		[1 => 'Bedřich', 'Anežka', 'Kamil', 'Stela', 'Kazimír', 'Miroslav', 'Tomáš', 'Gabriela', 'Františka', 'Viktorie', 'Anděla', 'Řehoř', 'Růžena', 'Rút a Matylda', 'Ida', 'Elena a Herbert', 'Vlastimil', 'Eduard', 'Josef', 'Světlana', 'Radek', 'Leona', 'Ivona', 'Gabriel', 'Marián', 'Emanuel', 'Dita', 'Soňa', 'Taťána', 'Arnošt', 'Kvido'],
		[1 => 'Hugo', 'Erika', 'Richard', 'Ivana', 'Miroslava', 'Vendula', 'Heřman a Hermína', 'Ema', 'Dušan', 'Darja', 'Izabela', 'Julius', 'Aleš', 'Vincenc', 'Anastázie', 'Irena', 'Rudolf', 'Valérie', 'Rostislav', 'Marcela', 'Alexandra', 'Evžénie', 'Vojtěch', 'Jiří', 'Marek', 'Oto', 'Jaroslav', 'Vlastislav', 'Robert', 'Blahoslav'],
		[1 => 'Svátek práce', 'Zikmund', 'Alexej', 'Květoslav', 'Klaudie', 'Radoslav', 'Stanislav', 'Den vítězství', 'Ctibor', 'Blažena', 'Svatava', 'Pankrác', 'Servác', 'Bonifác', 'Žofie', 'Přemysl', 'Aneta', 'Nataša', 'Ivo', 'Zbyšek', 'Monika', 'Emil', 'Vladimír', 'Jana', 'Viola', 'Filip', 'Valdemar', 'Vilém', 'Maxim', 'Ferdinand', 'Kamila'],
		[1 => 'Laura', 'Jarmil', 'Tamara', 'Dalibor', 'Dobroslav', 'Norbert', 'Iveta', 'Medard', 'Stanislava', 'Gita', 'Bruno', 'Antonie', 'Antonín', 'Roland', 'Vít', 'Zbyněk', 'Adolf', 'Milan', 'Leoš', 'Květa', 'Alois', 'Pavla', 'Zdeňka', 'Jan', 'Ivan', 'Adriana', 'Ladislav', 'Lubomír', 'Petr a Pavel', 'Šárka'],
		[1 => 'Jaroslava', 'Patricie', 'Radomír', 'Prokop', 'Cyril a Metoděj', 'Jan Hus', 'Bohuslava', 'Nora', 'Drahoslava', 'Libuše a Amálie', 'Olga', 'Bořek', 'Markéta', 'Karolína', 'Jindřich', 'Luboš', 'Martina', 'Drahomíra', 'Čeněk', 'Ilja', 'Vítězslav', 'Magdaléna', 'Libor', 'Kristýna', 'Jakub', 'Anna', 'Věroslav', 'Viktor', 'Marta', 'Bořivoj', 'Ignác'],
		[1 => 'Oskar', 'Gustav', 'Miluše', 'Dominik', 'Kristián', 'Oldřiška', 'Lada', 'Soběslav', 'Roman', 'Vavřinec', 'Zuzana', 'Klára', 'Alena', 'Alan', 'Hana', 'Jáchym', 'Petra', 'Helena', 'Ludvík', 'Bernard', 'Johana', 'Bohuslav', 'Sandra', 'Bartoloměj', 'Radim', 'Luděk', 'Otakar', 'Augustýn', 'Evelína', 'Vladěna', 'Pavlína'],
		[1 => 'Linda a Samuel', 'Adéla', 'Bronislav', 'Jindřiška', 'Boris', 'Boleslav', 'Regína', 'Mariana', 'Daniela', 'Irma', 'Denisa', 'Marie', 'Lubor', 'Radka', 'Jolana', 'Ludmila', 'Naděžda', 'Kryštof', 'Zita', 'Oleg', 'Matouš', 'Darina', 'Berta', 'Jaromír', 'Zlata', 'Andrea', 'Jonáš', 'Václav', 'Michal', 'Jeroným'],
		[1 => 'Igor', 'Olívie a Oliver', 'Bohumil', 'František', 'Eliška', 'Hanuš', 'Justýna', 'Věra', 'Štefan a Sára', 'Marina', 'Andrej', 'Marcel', 'Renáta', 'Agáta', 'Tereza', 'Havel', 'Hedvika', 'Lukáš', 'Michaela', 'Vendelín', 'Brigita', 'Sabina', 'Teodor', 'Nina', 'Beáta', 'Erik', 'Šarlota a Zoe', 'Jidáš', 'Silvie', 'Tadeáš', 'Štěpánka'],
		[1 => 'Felix', 'Památka zesnulých', 'Hubert', 'Karel', 'Miriam', 'Liběna', 'Saskie', 'Bohumír', 'Bohdan', 'Evžen', 'Martin', 'Benedikt', 'Tibor', 'Sáva', 'Leopold', 'Otmar', 'Mahulena', 'Romana', 'Alžběta', 'Nikola', 'Albert', 'Cecílie', 'Klement', 'Emílie', 'Kateřina', 'Artur', 'Xenie', 'René', 'Zina', 'Ondřej'],
		[1 => 'Iva', 'Blanka', 'Svatoslav', 'Barbora', 'Jitka', 'Mikuláš', 'Ambrož', 'Květoslava', 'Vratislav', 'Julie', 'Dana', 'Simona', 'Lucie', 'Lýdie', 'Radana', 'Albína', 'Daniel', 'Miloslav', 'Ester', 'Dagmar', 'Natálie', 'Šimon', 'Vlasta', 'Adam a Eva', '1. svátek vánoční', 'Štěpán', 'Žaneta', 'Bohumila', 'Judita', 'David', 'Silvestr'],
	];

	if (isset($names[date('n')][date('j')])) {
		return $names[date('n')][date('j')];
	}
}
