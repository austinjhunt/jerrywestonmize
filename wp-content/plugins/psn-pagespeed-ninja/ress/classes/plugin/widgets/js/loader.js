/*
 * @copyright Copyright (C) 2017-2024 Denis Ryabov / PageSpeed Ninja Team. All rights reserved.
 */
(function (window, document, deferjsList, addEventListener, jsnode) {

    function loadAll(i) {
        jsnode = document.getElementsByTagName('script')[i = 0];
        window.ress_js = loadJs;
        while (deferjsList[i]) {
            loadJs(deferjsList[i++]);
        }
        deferjsList = []; // to bypass setTimeout's call
        doEventListeners(removeEventListener);
    }

    function loadJs(src, js) {
        js = document.createElement('script');
        js.src = src;
        jsnode.parentNode.insertBefore(js, jsnode);
    }

    function doEventListeners(action) {
        action('scroll', loadAll);
        action('mouseover', loadAll);
        action('touchstart', loadAll, {passive: true});
    }

    window.ress_js = deferjsList.push.bind(deferjsList);

    if (addEventListener) {
        addEventListener('load', function () {
            setTimeout(loadAll, 5500);
        });
        doEventListeners(addEventListener);
    } else {
        loadAll();
    }

})(window, document, [], addEventListener);