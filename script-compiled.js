"use strict";

var proxyLocation = 'proxy.php?get=';
var suplovaniDelimiter = ';!;';
var refreshMillis = 30 * 1000;
var endpoints = ['rss', 'suplovani', 'owm', 'nameday', 'images'];
var elements = [document.getElementById('left'), document.getElementById('right'), document.getElementById('statusbar')];
var data = {
  endpoints: ['', '', '', '', ''],
  elements: ['', '', '']
};
var formatFunctions = [function left() {
  var imageHtml = gec('images').split('\n').map(function (url) {
    return url ? `<img src="${url}" />` : '';
  }).join('');
  var htmlContent = `<div class="rss">${gec('rss')}</div><div class="images">${imageHtml}</div>`;
  return gec('rss') || imageHtml ? htmlContent : '';
}, function right() {
  return getPartOfSuplovani(2);
}, function statusbar() {
  var wrap = function wrap(html) {
    return html ? `<span>${html}</span>` : '';
  };

  var logo = '<img src="goh.svg" />';
  var suplovaniDate = '';

  if (getPartOfSuplovani(0) !== getPartOfSuplovani(1)) {
    suplovaniDate = getPartOfSuplovani(1) ? `zobrazuje se suplování pro ${getPartOfSuplovani(1)}` : 'suplování není dostupné';
  }

  return logo + wrap(getPartOfSuplovani(0)) + wrap(gec('owm')) + wrap(gec('nameday')) + wrap(suplovaniDate);
}];
var scroll = {
  numberOfElements: 2,
  rate: 30,
  interval: []
};
getData();
setInterval(getData, refreshMillis);
scrollInit();

function getData() {
  var _loop = function _loop(i) {
    var xhttp = new XMLHttpRequest();

    xhttp.onload = function () {
      checkAndSetDownloaded(i, this.responseText);
    };

    xhttp.open('GET', proxyLocation + endpoints[i]);
    xhttp.send();
  };

  for (var i = 0; i < endpoints.length; i++) {
    _loop(i);
  }
}

function checkAndSetDownloaded(endpointId, content) {
  if (content !== data.endpoints[endpointId]) {
    data.endpoints[endpointId] = content;
    updateAllElements();
  }
}

function updateAllElements() {
  for (var i = 0; i < elements.length; i++) {
    checkAndSetElement(i);
  }
}

function checkAndSetElement(elementId) {
  var html = formatFunctions[elementId]();

  if (html && html !== data.elements[elementId]) {
    data.elements[elementId] = html;
    elements[elementId].innerHTML = html;
    elements[elementId].scrollTop = 0;
  }
}

function scrollInit() {
  for (var i = 0; i < scroll.numberOfElements; i++) {
    elements[i].scrollTop = 0;
    scroll.interval[i] = setInterval('scrollDiv(' + i + ')', scroll.rate);
  }
}

function scrollDiv(i) {
  elements[i].scrollTop++;
  var reachedMax = elements[i].scrollTop >= elements[i].scrollHeight - elements[i].offsetHeight;

  if (reachedMax && data.elements[i]) {
    elements[i].innerHTML += data.elements[i];
  }
}

function gec(endpointName) {
  // getEndpointContent
  var index = endpoints.indexOf(endpointName);
  return index === -1 ? '' : data.endpoints[index];
}

function getPartOfSuplovani(index) {
  return gec('suplovani').split(suplovaniDelimiter)[index] || '';
}