/*! Lazy Load XT v3.0.3 2024-02-07
 * http://ressio.github.io/lazy-load-xt
 * (C) 2013-2024 RESSIO
 * Licensed under MIT */

(function (addEventListener, video, source, lazyBackgroundObserver, url) {
    function init() {
        lazyBackgroundObserver = lazyBackgroundObserver || new IntersectionObserver(function (entries) {
            for (video of entries) {
                if (video.isIntersecting) {
                    lazyBackgroundObserver.unobserve(video = video.target);
                    video.poster = video.dataset.poster || video.poster;
                    if ((url = video.dataset.src)) {
                        video.src = url;
                    }
                    for (source of video.children) {
                        if (source.tagName === "SOURCE" && (url = source.dataset.src)) {
                            source.src = url;
                        }
                    }
                    video.load();
                }
            }
        }, {rootMargin: (window.lazyLoadXT && lazyLoadXT.edgeY) || ''});
        for (video of document.querySelectorAll('video.lazy-video')) {
            lazyBackgroundObserver.observe(video);
        }
    }

    addEventListener('DOMContentLoaded', init);
    addEventListener('DOMUpdated', init);
})(addEventListener);