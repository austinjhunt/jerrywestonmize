/*
 * @copyright Copyright (C) 2017-2024 Denis Ryabov / PageSpeed Ninja Team. All rights reserved.
 */
(function (document) {
    'use strict';

    var documentWrite = document.write,
        documentWriteLn = document.writeln,
        writeBuffer = '',
        readyState = 'loading',
        script_list = [],
        unsupportedModules = true,
        startOnEvents = ['click', 'keydown', 'mousemove', 'mousedown', 'wheel', 'touchstart', 'scroll'],
        delayToLoad = 5005, /* 5 seconds */
        prefetch_done = false,
        destination,
        lastClickEvent,
        timerId;

    function enableReadyStateEmulation() {
        Object.defineProperty(document, 'readyState', {
            configurable: true,
            get() {
                return readyState;
            }
        });
    }

    function disableReadyStateEmulation() {
        delete document.readyState;
    }

    function enableDocumentWriteEmulation() {
        document.write = function (str) {
            writeBuffer += str;
        };
        document.writeln = function (str) {
            writeBuffer += str + '\n';
        };
    }

    function disableDocumentWriteEmulation() {
        document.write = documentWrite;
        document.writeln = documentWriteLn;
    }

    function runPrefetch(i, source, collection, script_list_defer, src) {
        if (prefetch_done) {
            return;
        }

        prefetch_done = true;

        // LOAD JAVASCRIPTS
        collection = document.getElementsByTagName('script');
        script_list_defer = [];
        for (i = 0; source = collection[i++];) {
            if (source.type === 'text/ress') {
                (source.defer || source.getAttribute('ress-type') === 'module' ? script_list_defer : script_list).push(source);
            }
        }
        script_list.push.apply(script_list, script_list_defer);

        for (i = 0; source = script_list[i++];) {
            src = source.getAttribute('ress-type');
            if (
                (src === 'module' && unsupportedModules) ||
                (src === 'nomodule' && !unsupportedModules)
            ) {
                continue;
            }
            src = source.getAttribute('ress-src');
            if (src && !src.startsWith('data:')) {
                destination = document.createElement('link');
                destination.rel = 'prefetch';
                destination.href = src;
                document.head.appendChild(destination);
            }
        }
    }

    function loadTimeouted() {
        removeEventListener('load', loadTimeouted);

        runPrefetch();

        startOnEvents.forEach((eventName) => {
            addEventListener(eventName, loadAll, {capture: true, passive: true});
        });

        timerId = setTimeout(loadAll, delayToLoad);
    }

    function prefetchScripts() {
        removeEventListener('DOMContentLoaded', prefetchScripts);
        setTimeout(runPrefetch);
    }

    function saveClickEvent(event) {
        lastClickEvent = event;
        event.stopPropagation();
        event.preventDefault();
    }

    function loadAll() {
        clearTimeout(timerId);
        startOnEvents.forEach((eventName) => {
            removeEventListener(eventName, loadAll, true);
        });
        runPrefetch();
        enableReadyStateEmulation();

        enableDocumentWriteEmulation();
        loadNextJavascript();
    }

    function loadNextJavascript(source, src, parent, div, child, forceNext) {
        if (writeBuffer) {
            div = document.createElement('div');
            div.innerHTML = writeBuffer;
            source = destination.nextSibling;
            while ((child = div.firstChild)) {
                destination.parentNode.insertBefore(child, source);
            }
            writeBuffer = '';
        }

        if ((source = script_list.shift())) {
            destination = document.createElement('script');
            for (div = 0; child = source.attributes[div++];) {
                destination.setAttribute(child.nodeName, child.nodeValue);
            }
            destination.type = '';

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
                // external script
                forceNext |= source.hasAttribute('async');
                if (!forceNext) {
                    destination.onload = destination.onerror = destination.onreadystatechange = function () {
                        if (destination.onload && (!destination.readyState || destination.readyState === 'loaded' || destination.readyState === 'complete')) {
                            destination.onload = destination.onerror = destination.onreadystatechange = null;
                            setTimeout(loadNextJavascript);
                        }
                    };
                }
                destination.src = src;
            } else {
                // inlined script
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
            disableDocumentWriteEmulation();

            div = {bubbles: true, cancelable: true};

            readyState = "interactive";
            document.dispatchEvent(new Event('readystatechange', div));

            // DOMContentLoaded event
            document.dispatchEvent(new Event('DOMContentLoaded', div));

            removeEventListener('click', saveClickEvent, true);
            if (lastClickEvent) {
                document.elementFromPoint(lastClickEvent.clientX, lastClickEvent.clientY).dispatchEvent(new MouseEvent('click', lastClickEvent));
            }

            readyState = "completed";
            document.dispatchEvent(new Event('readystatechange', div));

            disableReadyStateEmulation();

            dispatchEvent(new Event('load'));
            dispatchEvent(new PageTransitionEvent('pageshow'));
        }
    }


    try {
        unsupportedModules = !(new Function('import("")'));
    } catch (e) {
    }

    if (document.readyState === 'complete') {
        loadTimeouted();
    } else {
        addEventListener('load', loadTimeouted);
    }

    addEventListener('DOMContentLoaded', prefetchScripts);

    addEventListener('click', saveClickEvent, true);

})(document);