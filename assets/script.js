const proxyLocation = 'assets/proxy.php?get='
const suplovaniDelimiter = ';!;'
const refreshMillis = 30 * 1000
const scrollRate = 30
const endpoints = ['rss', 'suplovani', 'owm', 'nameday', 'images']
const elements = [
    document.getElementById('left'),
    document.getElementById('right'),
    document.getElementById('statusbar')
]
const data = {
    endpoints: ['', '', '', '', ''],
    elements: [document.createElement('div'), document.createElement('div'), document.createElement('div')]
}
const formatFunctions = [
    function left() {
        const imageHtml = gec('images').split('\n').map(url => url ? `<img src="${url}" />` : '').join('')
        const htmlContent = `<div class="rss">${gec('rss')}</div><div class="images">${imageHtml}</div>`
        return (gec('rss') || imageHtml) ? htmlContent : ''
    },
    function right() {
        return getPartOfSuplovani(2)
    },
    function statusbar() {
        const wrap = (html) => (html ? `<span>${html}</span>` : '')
        const logo = '<img src="assets/goh.svg" />'
        let suplovaniDate = ''

        if (getPartOfSuplovani(0) !== getPartOfSuplovani(1)) {
            suplovaniDate = getPartOfSuplovani(1)
                ? `zobrazuje se suplování pro ${getPartOfSuplovani(1)}`
                : 'suplování není dostupné'
        }

        return (
            logo
            + wrap(getPartOfSuplovani(0))
            + wrap(gec('owm'))
            + wrap(gec('nameday'))
            + wrap(suplovaniDate)
        )
    }
]

getData()
setInterval(getData, refreshMillis)

// scrolling and checking logic is separated to avoid lags
setInterval(scrollDivs, scrollRate)
setInterval(checkMaxTwice, scrollRate * 50.5)

function getData() {
    for (let i = 0; i < endpoints.length; i++) {
        const xhttp = new XMLHttpRequest()
        xhttp.onload = function () { checkAndSetDownloaded(i, this.responseText) }
        xhttp.open('GET', proxyLocation + endpoints[i])
        xhttp.send()
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
    const html = formatFunctions[elementId]()
    const referenceNode = document.createElement('div')
    referenceNode.innerHTML = html
    
    if (html && !referenceNode.isEqualNode(data.elements[elementId])) {
        data.elements[elementId].innerHTML = html
        elements[elementId].textContent = ''
        elements[elementId].appendChild(data.elements[elementId].cloneNode(true))
        elements[elementId].scrollTop = 0
    }
}

function scrollDivs() {
    elements[0].scrollTop++
    elements[1].scrollTop++
}

function checkMaxTwice() {
    checkMax(0)
    checkMax(1)
}

function checkMax(i) {
    // 500 is here to avoid lags
    const reachedMax = (elements[i].scrollTop + 500) >=
        (elements[i].scrollHeight - elements[i].offsetHeight)

    if (reachedMax && data.elements[i].innerHTML) {
        elements[i].appendChild(data.elements[i].cloneNode(true))
    }
}

function gec(endpointName) {
    // getEndpointContent
    const index = endpoints.indexOf(endpointName)
    return index === -1 ? '' : data.endpoints[index]
}

function getPartOfSuplovani(index) {
    return gec('suplovani').split(suplovaniDelimiter)[index] || ''
}
