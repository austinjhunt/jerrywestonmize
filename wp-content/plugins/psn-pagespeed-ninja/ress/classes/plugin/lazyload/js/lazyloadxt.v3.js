/*! Lazy Load XT v3.0.3 2024-02-07
 * http://ressio.github.io/lazy-load-xt
 * (C) 2013-2024 RESSIO
 * Licensed under MIT */

/*
                       IE     Edge     Firefox  Chrome      Safari     Opera       iOS Safari       OperaMini  Android  OperaMobile   ChromeAndroid  FirefoxAndroid  UCBrowser  Samsung       QQ    Baidu  KaiOS
 IntersectionObserver         15*,16+  55+      51-57*,58+  12.1+      38-43*,45+  12.2+                       76+      46+           79+            68+             12.12*     5-6.4*,7.2+
 srcset                       16+      38+      34-37*,38+  7.1-8*,9+  21-24*,25+  8-8.4*,9+                   76+      46+           79+            68+             12.12+     4+            1.2+  7.12+  2.5+
 querySelectorAll      8*,9+  12+      3.5+     4+          6+         10+         3.2-13.1?,13.2+  +          ?,76+    12-12.1?,46+  79+            68+             12.12?     4-9.2?,10.1+  1.2?  7.12?  2.5?
 vh unit               9+     12+      19+      20+         6+         20+         6+               +          76+      46+           79+            68+             12.12?     4+            1.2?  7.12?  2.5?
 */

(function (window, document, addEventListener, IntersectionObserver, dataSrc, lazyHidden, observer) {
    'use strict';

    /**
     * Load element
     * @param {Element} el
     */
    function load(el) {
        if (observer) {
            observer.unobserve(el);
        }
        el.onload = function () {
            this.classList.remove(lazyHidden);
        };
        el[el.tagName === 'IMG' ? 'srcset' : 'src'] = el.getAttribute(dataSrc);
    }

    /**
     * Initialization
     */
    function ready(i, el, classes, elems) {
        if (IntersectionObserver && !observer) {
            observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        load(entry.target);
                    }
                });
            }, {rootMargin: (window.lazyLoadXT && lazyLoadXT.edgeY) || ''});
        }
        elems = document.querySelectorAll('[' + dataSrc + ']');
        for (i = 0; i < elems.length; i++) {
            el = elems[i];
            classes = el.classList;
            classes.remove('lazy');
            classes.add(lazyHidden);
            observer ? observer.observe(el) : load(el);
        }
    }

    if (document.readyState !== 'loading') {
        // ready();
        setTimeout(ready); // to wait for new parameters
    } else {
        addEventListener('DOMContentLoaded', ready);
    }
    addEventListener('DOMUpdated', ready);

})(window, document, addEventListener, window.IntersectionObserver, 'data-src', 'lazy-hidden');