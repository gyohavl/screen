const proxyLocation = 'proxy.php?get='

fetch(proxyLocation + 'suplovani').then(r => r.text()).then(html => document.getElementById('suplovani').innerHTML = html)
