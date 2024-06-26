/*
 * @copyright Copyright (C) 2024 Denis Ryabov / PageSpeed Ninja Team. All rights reserved.
 */
/*
<link rel="ress-css" media="..." href="...">
<noscript><link rel="stylesheet" media="..." href="..."></noscript>
 */
(function (document) {
    var events = ['click', 'keydown', 'mousemove', 'mousedown', 'wheel', 'touchstart', 'scroll'];
    var reveal_asap = false;
    var styles_loading = [];
    var styles_loaded = [];

    function loadStyles() {
        removeEventListener('DOMContentLoaded', loadStyles);
        styles_loading = [...document.querySelectorAll('link[rel="ress-css"]')];
        styles_loading.forEach((link) => {
            link.saved_media = link.media || 'all';
            if (!window.matchMedia || matchMedia(link.saved_media).matches) {
                link.onload = process_load.bind(link);
                link.onerror = process_any.bind(link);
                link.media = 'print';
                link.rel = "stylesheet";
            }
        });
    }

    function process_load() {
        if (reveal_asap) {
            revealLink(this);
        } else {
            styles_loaded.push(this);
        }
        process_any.call(this);
    }

    function process_any() {
        styles_loading.remove(this);
        if (styles_loading.length === 0) {
            revealAll();
            // remove critical-css element?
        }
    }

    function revealAll() {
        reveal_asap = true;
        events.forEach((eventName) => removeEventListener(eventName, revealAll));
        styles_loaded.forEach((link) => revealLink(link));
        styles_loaded = [];
        document.querySelectorAll('link[rel="ress-css"]').forEach((link) => {
            link.media = 'print';
            link.rel = "stylesheet";
            link.onload = process_load.bind(link);
        });
    }

    function revealLink(link) {
        link.onload = link.onerror = null;
        link.media = link.saved_media;
    }

    addEventListener('DOMContentLoaded', loadStyles);
    events.forEach((eventName) => addEventListener(eventName, revealAll, {passive: true}));
})(document);
