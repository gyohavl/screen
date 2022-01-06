const proxyLocation = 'proxy.php?get='
const elements = [
    document.getElementById('left'),
    document.getElementById('right'),
    document.getElementById('statusbar')
]
const data = ['', '', '']
const scroll = {
    numberOfElements: 2,
    rate: 30,
    interval: []
}

getData()
scrollInit()

function getData() {
    fetch(proxyLocation + 'rss').then(r => r.text()).then(html => checkAndSet(html, 0))
    fetch(proxyLocation + 'suplovani').then(r => r.text()).then(html => checkAndSet(html, 1))
    fetch(proxyLocation + 'owm').then(r => r.text()).then(html => checkAndSet(html, 2))
}

function checkAndSet(content, elementId) {
    if (content !== data[elementId]) {
        data[elementId] = content
        elements[elementId].innerHTML = content
    }
}

function scrollInit() {
    for (var i = 0; i < scroll.numberOfElements; i++) {
        elements[i].scrollTop = 0
        scroll.interval[i] = setInterval('scrollDiv(' + i + ')', scroll.rate)
    }
}

function scrollDiv(i) {
    elements[i].scrollTop++
    let reachedMax = elements[i].scrollTop >=
        (elements[i].scrollHeight - elements[i].offsetHeight)

    if (reachedMax) {
        elements[i].innerHTML += data[i]
    }
}
