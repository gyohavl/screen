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
	'suplovani' => '../data/right/suplprehtrid.html'
);
$firstImageNumber = 1;

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

	// part names are used without trailing colon
	$supportedParts = array('Suplování', 'Nepřítomné třídy', 'Nepřítomní učitelé', 'Místnosti mimo provoz', 'Změny v rozvrzích učitelů', 'Změny v rozvrzích tříd', 'Pedagogické dohledy u tříd');
	$enabledParts = array('Místnosti mimo provoz', 'Změny v rozvrzích učitelů', 'Změny v rozvrzích tříd');
	$classesPartName = 'Změny v rozvrzích tříd';
	$teachersPartName = 'Změny v rozvrzích učitelů';

	$months = array('Měsíce', 'ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince');
	$currentDate = date('j') . '. ' . $months[date('n')];

	if ($html) {
		$dom = new DOMDocument;
		$dom->loadHTML($html);
		$tableRows = $dom->getElementsByTagName('tr');
		$outputEnabled = false;
		$suplovaniDate = '';
		$htmlTable = '';
		$currentPart = '';
		$lastSection = '';

		foreach ($tableRows as $tr) {
			$rowOutput = '';
			$rowIsEmpty = true;
			$cellNumber = 0;

			foreach ($tr->childNodes as $node) {
				if ($node->nodeName === 'td' || $node->nodeName === 'th') {
					$cellText = trim($node->nodeValue);
					$cellText = str_replace(' - ', ' – ', $cellText);
					$cellText = str_replace('.- ', '.–', $cellText);

					// if cell contains only a non-breaking space, remove the space
					if ($cellText == "\xC2\xA0") {
						$cellText = '';
					}

					if ($rowIsEmpty && $cellText != '') {
						$rowIsEmpty = false;
					}

					if ($cellNumber == 0 && $cellText != '' && in_array(rtrim($cellText, ':'), $supportedParts)) {
						$currentPart = rtrim($cellText, ':');
						$outputEnabled = (in_array($currentPart, $enabledParts));

						if ($outputEnabled) {
							if ($htmlTable != '') {
								$htmlTable .= '</tbody></table>' . PHP_EOL;
							}

							$htmlTable .= '<table><tbody>' . PHP_EOL;
						}
					} else if ($suplovaniDate === '') {
						$bareDate = preg_replace('/^(.*\s)?(\d+\.\s?\d+\.\s?\d+)(\s.*)?$/', '$2', $cellText);
						$dateObj = DateTime::createFromFormat('j. n. Y', $bareDate);

						if ($dateObj !== false) {
							$day = $dateObj->format('j');
							$month = $dateObj->format('n');
							$suplovaniDate = "$day. {$months[$month]}";
						}
					} else if (
						$cellNumber == 0 && $cellText != ''
						&& ($currentPart == $classesPartName || $currentPart == $teachersPartName)
					) {
						// does the trick with alternating background
						if ($cellText == $lastSection) {
							$cellText = '';
						} else {
							$rowOutput .= '</tbody><tbody>' . PHP_EOL;
						}

						$lastSection = $cellText;
					} else if ($currentPart == $teachersPartName && $lastSection != '') {
						// fixes teacher's name randomly appearing after a student group name
						$cellText = str_replace($lastSection, '', $cellText);
					}

					if ($cellNumber == 0) {
						$rowOutput .= '<tr>'; // prevents inserting </tbody> directly after <tr>
					}

					$colspan = $node->getAttribute('colspan');
					$colspanHtml = ($colspan === '') ? '' : (' colspan="' . $colspan . '"');
					$rowOutput .= '<td' . $colspanHtml . '>' . $cellText . '</td>';
					$cellNumber++;
				}
			}

			if (!$rowIsEmpty && $outputEnabled) {
				$htmlTable .= $rowOutput . '</tr>' . PHP_EOL;
			}
		}

		$htmlTable .= '</tbody></table>';
		echo $currentDate . $delimiter . $suplovaniDate . $delimiter . $htmlTable;
	} else {
		echo $currentDate;
	}
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
