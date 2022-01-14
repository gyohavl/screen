const proxyLocation = 'proxy.php?get='
let suplovaniDelimiter = ';!;'
let refreshMillis = 30 * 1000
const endpoints = ['rss', 'suplovani', 'owm', 'nameday', 'images']
const elements = [
    document.getElementById('left'),
    document.getElementById('right'),
    document.getElementById('statusbar')
]
const data = {
    endpoints: ['', '', '', '', ''],
    elements: ['', '', '']
}
const formatFunctions = [
    function left() {
        let imageHtml = gec('images').split('\n').map(url => `<img src="${url}" />`).join('')
        return imageHtml
        // return gec('rss')
    },
    function right() {
        return getPartOfSuplovani(2)
    },
    function statusbar() {
        return getPartOfSuplovani(0) + gec('owm') + gec('nameday') + getPartOfSuplovani(1)
    }
]
const scroll = {
    numberOfElements: 2,
    rate: 30,
    interval: []
}

getData()
setInterval(getData, refreshMillis)
scrollInit()

function getData() {
    for (let i = 0; i < endpoints.length; i++) {
        fetch(proxyLocation + endpoints[i])
            .then(r => r.text())
            .then(html => checkAndSetDownloaded(i, html))
    }
}

function checkAndSetDownloaded(endpointId, content) {
    if (content !== data.endpoints[endpointId]) {
        data.endpoints[endpointId] = content
        updateAllElements()
    }
}

function updateAllElements() {
    for (let i = 0; i < elements.length; i++) {
        checkAndSetElement(i)
    }
}

function checkAndSetElement(elementId) {
    let html = formatFunctions[elementId]()

    if (html && html !== data.elements[elementId]) {
        data.elements[elementId] = html
        elements[elementId].innerHTML = html
        elements[elementId].scrollTop = 0
    }
}

function scrollInit() {
    for (let i = 0; i < scroll.numberOfElements; i++) {
        elements[i].scrollTop = 0
        scroll.interval[i] = setInterval('scrollDiv(' + i + ')', scroll.rate)
    }
}

function scrollDiv(i) {
    elements[i].scrollTop++
    let reachedMax = elements[i].scrollTop >=
        (elements[i].scrollHeight - elements[i].offsetHeight)

    if (reachedMax) {
        elements[i].innerHTML += data.elements[i]
    }
}

function gec(endpointName) {
    // getEndpointContent
    let index = endpoints.indexOf(endpointName)
    return index === -1 ? '' : data.endpoints[index]
}

function getPartOfSuplovani(index) {
    return gec('suplovani').split(suplovaniDelimiter)[index] || ''
}
