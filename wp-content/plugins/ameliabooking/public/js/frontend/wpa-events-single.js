(function () {
    'use strict';

    function rafThrottle(fn) {
        var id = 0;
        return function () {
            if (id) {
                return;
            }
            id = requestAnimationFrame(function () {
                id = 0;
                fn();
            });
        };
    }

    /** One window resize listener; handlers run on the next animation frame. */
    function createResizeBus() {
        var handlers = [];
        var run = rafThrottle(function () {
            for (var i = 0; i < handlers.length; i += 1) {
                handlers[i]();
            }
        });

        return {
            add: function (handler) {
                handlers.push(handler);
            },
            attach: function () {
                if (handlers.length) {
                    window.addEventListener('resize', run);
                }
            }
        };
    }

    function initCarousel(root, resizeBus) {
        var carousel = root.querySelector('[data-amelia-wpa-carousel]');
        if (!carousel) {
            return;
        }

        var viewport = carousel.querySelector('.wpa-events__carousel-viewport');
        var track = carousel.querySelector('[data-carousel-track]');
        var slides = carousel.querySelectorAll('[data-carousel-slide]');
        var dots = carousel.querySelectorAll('[data-carousel-dot]');
        var prev = carousel.querySelector('[data-carousel-prev]');
        var next = carousel.querySelector('[data-carousel-next]');

        if (!viewport || !track || slides.length < 2) {
            return;
        }

        var index = 0;
        var slideCount = slides.length;

        function applyTransform() {
            var w = viewport.offsetWidth || 0;
            if (w > 0) {
                track.style.transform = 'translate3d(' + (-index * w) + 'px,0,0)';
            }
        }

        function syncDots() {
            for (var d = 0; d < dots.length; d += 1) {
                var dot = dots[d];
                var dotIndex = parseInt(dot.getAttribute('data-carousel-dot'), 10);
                dot.classList.toggle('is-active', dotIndex === index);
            }
        }

        function go(target) {
            index = (target % slideCount + slideCount) % slideCount;
            applyTransform();
            syncDots();
        }

        if (prev) {
            prev.addEventListener('click', function () {
                go(index - 1);
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                go(index + 1);
            });
        }

        for (var i = 0; i < dots.length; i += 1) {
            dots[i].addEventListener('click', function (ev) {
                var el = ev.currentTarget;
                go(parseInt(el.getAttribute('data-carousel-dot'), 10));
            });
        }

        applyTransform();
        resizeBus.add(applyTransform);
    }

    var root = document.getElementById('wpa-events-landing');
    if (!root) {
        return;
    }

    var resizeBus = createResizeBus();
    initCarousel(root, resizeBus);
    resizeBus.attach();
})();
