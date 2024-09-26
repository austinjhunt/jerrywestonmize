/* PageSpeed Ninja 1.4.5 | pagespeed.ninja/license.html */
(function ($) {
    'use strict';
    $(document).ready(function () {
        var shiftLeft = 80,
            baselineHeight = 24,
            arrowSkip = 12;

        function resolveTooltipRef(el) {
            return document.getElementById(el.getAttribute('data-html-tooltip-ref'));
        }

        function getOffsetParent(el) {
            if (el.offsetParent !== null) {
                return el.offsetParent;
            }
            var parent = el.parentElement;
            while (parent !== document.body && getComputedStyle(parent).position === 'static') {
                parent = parent.parentElement;
            }
            return parent;
        }

        function removeTimer(banner) {
            var timerID = banner.psnTimerID;
            if (timerID) {
                clearTimeout(timerID);
                banner.psnTimerID = 0;
            }
        }

        function showBanner(el) {
            if (el.hasAttribute('data-html-tooltip-disable')) {
                return;
            }
            var banner = resolveTooltipRef(el),
                container = getOffsetParent(banner),
                container_rect = container.getBoundingClientRect(),
                container_styles = getComputedStyle(container),
                element_rect = el.getBoundingClientRect(),
                container_left = container_rect.left + parseInt(container_styles.borderLeftWidth),
                left = Math.max(0, Math.ceil((element_rect.left - shiftLeft) - container_left));
            removeTimer(banner);
            switch (banner.getAttribute('data-html-tooltip-pos')) {
                case 'top':
                    var container_bottom = container_rect.bottom - parseInt(container_styles.borderBottomWidth),
                        bottom = Math.max(0, Math.ceil(container_bottom - (element_rect.top - arrowSkip)));
                    $(banner).css({left: left, bottom: bottom, display: 'block'});
                    break;
                case 'baseline':
                    var container_top = container_rect.top + parseInt(container_styles.borderTopWidth),
                        element_styles = getComputedStyle(el),
                        baseline = element_rect.top + parseInt(element_styles.borderTopWidth) + parseInt(element_styles.paddingTop) + baselineHeight,
                        top = Math.max(0, Math.ceil((baseline + arrowSkip) - container_top));
                    $(banner).css({left: left, top: top, display: 'block'});
                    break;
                default:
                    var container_top = container_rect.top + parseInt(container_styles.borderTopWidth),
                        top = Math.max(0, Math.ceil((element_rect.bottom + arrowSkip) - container_top));
                    $(banner).css({left: left, top: top, display: 'block'});
            }
        }

        function hideBanner(banner) {
            if (banner.psnTimerID) {
                return;
            }
            banner.psnTimerID = setTimeout(function () {
                banner.psnTimerID = 0;
                $(banner).css("display", "none");
            }, 200);
        }

        $(".pagespeedninja [data-html-tooltip-ref]")
            .on("mouseenter", function (e) {
                showBanner(e.currentTarget);
            })
            .on("mouseleave", function (e) {
                hideBanner(resolveTooltipRef(e.currentTarget));
            });
        $(".pagespeedninja [data-html-tooltip-active]")
            .on("mouseenter", function (e) {
                removeTimer(e.currentTarget);
            })
            .on("mouseleave", function (e) {
                hideBanner(e.currentTarget);
            });
    });
})(jQuery);
