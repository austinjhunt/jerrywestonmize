/*
 * @copyright Copyright (C) 2024 Denis Ryabov / PageSpeed Ninja Team. All rights reserved.
 */
addEventListener('load', function () {
    setTimeout(function () {
        [...document.querySelectorAll('link[rel=prefetch-delayed]')].forEach(function (el) {
            el.rel = "prefetch";
        });
    });
});
