(function (window, document, script_list, unsupportedModules, requestAnimationFrame, destination, writeBuffer) {
    'use strict';

    if (document.readyState === 'complete') {
        loadAll();
    } else if (window.addEventListener) {
        window.addEventListener('load', loadAll);
    } else { // IE
        window.attachEvent('onload', loadAll);
    }

    function loadAll(i, source, collection) {
        // LOAD JAVASCRIPTS
        collection = document.getElementsByTagName('script');
        for (i = 0; source = collection[i++];) {
            if (source.type === 'text/ress') {
                script_list.push(source);
            }
        }

        try {
            unsupportedModules = !(new Function('import("")'));
        } catch (e) {
        }

        writeBuffer = '';
        document.write = function (str) {
            writeBuffer += str;
        };
        document.writeln = function (str) {
            writeBuffer += str + '\n';
        };
        loadNextJavascript();
    }

    function loadNextJavascript(source, src, parent, p, child, forceNext) {

        if (writeBuffer) {
            p = document.createElement('p');
            p.innerHTML = writeBuffer;
            source = destination.nextSibling;
            while ((child = p.firstChild)) {
                destination.parentNode.insertBefore(child, source);
            }
            writeBuffer = '';
        }

        if ((source = script_list.shift())) {
            destination = document.createElement('script');
            for (p = 0; child = source.attributes[p++];) {
                destination.setAttribute(child.nodeName, child.nodeValue);
            }
            destination.type = 'text/javascript';

            switch (src = source.getAttribute('ress-type')) {
                case 'module':
                    destination.type = src;
                    forceNext = unsupportedModules;
                    break;
                case 'nomodule':
                    destination.noModule = true;
                    forceNext = !unsupportedModules;
            }

            if ((src = source.getAttribute('ress-src'))) {
                destination.onload = destination.onerror = destination.onreadystatechange = function () {
                    if (destination.onload && (!destination.readyState || destination.readyState === 'loaded' || destination.readyState === 'complete')) {
                        destination.onload = destination.onerror = destination.onreadystatechange = null;
                        setTimeout(loadNextJavascript);
                    }
                };
                destination.src = src;
            } else {
                src = source.text || source.textContent || source.innerHTML;
                if (destination.text === '') { // HTML5 property
                    destination.text = src;
                } else { // Legacy browsers
                    destination.appendChild(document.createTextNode(src));
                }
                forceNext = true;
            }

            if (forceNext) {
                setTimeout(loadNextJavascript);
            }

            parent = source.parentNode;
            parent.insertBefore(destination, source);
            parent.removeChild(source);
        } else {
            // DOMContentLoaded event
            p = {bubbles: true, cancelable: true};
            document.dispatchEvent(new Event('DOMContentLoaded', p));
            // load event
            document.dispatchEvent(new Event('load', p));
        }
    }

})(window, document, [], true);
