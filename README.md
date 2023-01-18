# Obrazovka ve vestibulu

Tento repozitář obsahuje webovou stránku určenou k projekci na obrazovce ve vestibulu Gymnázia Olgy Havlové v Ostravě-Porubě.

## První nastavení

1. stáhnout tento repozitář (např. pomocí `git clone`)
2. umístit ho na PHP server
3. otevřít v prohlížeči `index.html` (tak by mělo dojít k vytvoření souboru `assets/config.php`)
4. v souboru `assets/config.php` nastavit klíč pro OpenWeatherMap API a přepnout `debug` na `false`
5. do složky `data/right` umístit HTML soubor se suplováním (suplobec.html)
6. do složky `data/left` umístit PNG obrázky (1.png, 2.png, …)
7. zkontrolovat, že je v PHP aktivovaná *cURL extension*

## Stáhnutí nové verze

1. pomocí `git pull` stáhnout novou verzi repozitáře
2. otevřít v prohlížeči `index.html`
3. zkontrolovat, zda vše funguje, jak má (v případě potřeby upravit soubor `config.php`)

## Vývoj

Kromě JavaScriptu (viz níže) není žádný kód nijak kompilován či transformován, vše lze upravovat přímo.

Kód pro [svátky](https://github.com/vaniocz/svatky-vanio-cz/blob/master/api/index.php) a [ikony počasí](https://erikflowers.github.io/weather-icons/) pochází z GitHubu. Nový klíč k API s daty o počasí lze v případě potřeby vygenerovat [na webu OpenWeatherMap](https://openweathermap.org/).

### JavaScript

1. pomocí `npm install` nainstalovat potřebné balíčky
2. provést potřebné úpravy v souboru `assets/script.js` (pozor – `script-compiled.js` je automaticky generovaný pomocí příkazu uvedeného níže)
3. generovat kód kompatibilní s Firefoxem 34 pomocí `npx babel assets/script.js -o assets/script-compiled.js` (pro sledování změn lze použít `-w`)
