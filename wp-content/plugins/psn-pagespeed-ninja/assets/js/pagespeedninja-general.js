/* PageSpeed Ninja 1.4.5 | pagespeed.ninja/license.html */
(function () {
    'use strict';

    var pagespeed_api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    var newUrl = null,
        newAjax = false,
        origScore = {},
        psnScore = {};

    var stored_items = [
        'pagespeed_desktop_orig',
        'pagespeed_mobile_orig',
        'pagespeed_desktop',
        'pagespeed_mobile'
    ];

    function updatePSIScore(score, span) {
        var className;
        if (score === '') {
            className = 'gps_unknown';
        } else if (score < 50) {
            className = 'gps_error';
        } else if (score < 90) {
            className = 'gps_warning';
        } else {
            className = 'gps_excellent';
        }
        span.innerHTML = score;
        jQuery(span)
            .removeClass('gps_loading gps_unknown gps_error gps_warning gps_excellent')
            .addClass(className);
    }

    function rearrangeItems(ruleResults, prefix) {
        var passedList = [],
            considerList = [],
            shouldFixList = [],
            rule;

        for (rule in ruleResults) {
            var div = document.getElementById(prefix + '_' + rule);
            if (div) {
                var score = ruleResults[rule].score;
                div.setAttribute('data-score', score);
                if (rule === 'server-response-time' && score === null && ruleResults[rule].numericValue > 200) {
                    // recommend to enable caching if TTFB>200ms
                    score = 0.995;
                }
                if (score === 1.0 || score === null) {
                    // passed
                    passedList.push(div);
                } else if (score >= 0.5) {
                    // consider
                    considerList.push(div);
                } else {
                    // should
                    shouldFixList.push(div);
                }

                if (origScore[prefix]) {
                    var orig = origScore[prefix][rule].score,
                        warnLevel = 1;
                    if (score === 1.0 || score === null || (score > orig && score > 0.9)) {
                        warnLevel = 0;
                    } else if (score <= orig - 0.01 && score < 0.9) {
                        warnLevel = 2;
                    }
                    jQuery(div).find('input:checked')
                        .toggleClass('psiwarn', warnLevel === 1)
                        .toggleClass('psierror', warnLevel === 2)
                        .parent().attr('data-html-tooltip-disable', warnLevel ? null : '');
                }
            }
        }

        function comparator(a, b) {
            var a_score = parseFloat(a.getAttribute('data-score')),
                b_score = parseFloat(b.getAttribute('data-score'));
            return ((a_score > b_score) ? 1 : ((a_score < b_score) ? -1 : 0));
        }

        considerList.sort(comparator);
        shouldFixList.sort(comparator);

        jQuery('#' + prefix + '-passed').append(passedList).toggleClass('hide', !passedList.length);
        jQuery('#' + prefix + '-consider-fixing').append(considerList).toggleClass('hide', !considerList.length);
        jQuery('#' + prefix + '-should-fix').append(shouldFixList).toggleClass('hide', !shouldFixList.length);
    }

    function loadPageSpeedCached() {
        var result = true,
            psn_cache_stamp = window.psn_cache_timestamp || 'x',
            prev_stamp = parseInt(window.localStorage.getItem('psn_cache_timestamp')),
            prev_time = parseInt(window.localStorage.getItem('psn_result_time')),
            cached_scores;

        origScore = JSON.parse(window.localStorage.getItem('psn_result_origscores')) || {};
        cached_scores = JSON.parse(window.localStorage.getItem('psn_result_psnscores'));

        if (prev_stamp !== psn_cache_stamp || prev_time < new Date().getTime() - 15 * 60 * 1000) {
            result = false;
        }

        for (var i = 0; i < stored_items.length; i++) {
            var key = stored_items[i],
                score = window.localStorage.getItem(key);
            if (score === null || score === '') {
                result = false;
            } else {
                updatePSIScore(score, document.getElementById(key));
            }
        }

        if (cached_scores !== null) {
            if ('desktop' in cached_scores) {
                rearrangeItems(cached_scores['desktop'], 'desktop');
            } else {
                result = false;
            }
            if ('mobile' in cached_scores) {
                rearrangeItems(cached_scores['mobile'], 'mobile');
            } else {
                result = false;
            }
        } else {
            result = false;
        }

        return result;
    }

    function savePageSpeedCached() {
        if (!window.localStorage) {
            return;
        }

        window.localStorage.setItem('psn_cache_timestamp', window.psn_cache_timestamp);
        window.localStorage.setItem('psn_result_time', new Date().getTime().toString());
        window.localStorage.setItem('psn_result_psnscores', JSON.stringify(psnScore));
        window.localStorage.setItem('psn_result_origscores', JSON.stringify(origScore));

        var scores = {
            action: 'pagespeedninja_save_psi_scores',
            _ajax_nonce: pagespeedninja_nonce
        };
        for (var i = 0; i < stored_items.length; i++) {
            var key = stored_items[i],
                score = document.getElementById(key).innerText;
            scores[key] = score;
            window.localStorage.setItem(key, score);
        }
        jQuery.post(ajaxurl, scores);
    }

    function showReviewBanner() {
        if (!window.localStorage || window.localStorage.getItem('psn_review_timestamp') !== null) {
            return;
        }
        window.localStorage.setItem('psn_review_timestamp', new Date().getTime().toString());

        var desktop_orig = parseInt(document.getElementById('pagespeed_desktop_orig').innerText),
            mobile_orig = parseInt(document.getElementById('pagespeed_mobile_orig').innerText),
            desktop = parseInt(document.getElementById('pagespeed_desktop').innerText),
            mobile = parseInt(document.getElementById('pagespeed_mobile').innerText),
            alpha = 0.5;
        if (
            (desktop > desktop_orig || mobile > mobile_orig) &&
            (desktop >= alpha * desktop_orig + (1 - alpha) * 100) &&
            (mobile  >= alpha * mobile_orig  + (1 - alpha) * 100)
        ) {
            jQuery('#psn-review-banner-wrapper').addClass('show');
        }
    }

    function loadPageSpeed() {
        var url = location.href.split('/').slice(0, -2).join('/') + '/';
        var url_orig = url + '?pagespeedninja=no';
        var url_random = url + '?pagespeedninja=' + Math.random();

        jQuery('#pagespeed_desktop_orig,#pagespeed_mobile_orig,' +
            '#pagespeed_desktop,#pagespeed_mobile').addClass('gps_loading');

        jQuery.when(
            jQuery.get(pagespeed_api, {strategy: 'desktop', url: url_orig}).done(function (response) {
                var score = '';
                try {
                    score = Math.round(100 * response.lighthouseResult.categories.performance.score);
                    origScore['desktop'] = response.lighthouseResult.audits;
                } catch (e) {
                    console.log(e);
                    origScore['desktop'] = null;
                }
                updatePSIScore(score, document.getElementById('pagespeed_desktop_orig'));
            }).fail(function () {
                updatePSIScore('', document.getElementById('pagespeed_desktop_orig'));
            }),

            jQuery.get(pagespeed_api, {strategy: 'mobile', url: url_orig}).done(function (response) {
                var score = '';
                try {
                    score = Math.round(100 * response.lighthouseResult.categories.performance.score);
                    origScore['mobile'] = response.lighthouseResult.audits;
                } catch (e) {
                    console.log(e);
                    origScore['mobile'] = null;
                }
                updatePSIScore(score, document.getElementById('pagespeed_mobile_orig'));
            }).fail(function () {
                updatePSIScore('', document.getElementById('pagespeed_mobile_orig'));
            }),

            // generate optimized assets
            jQuery.get(url, {pagespeedninja: 'desktop'}).then(function () {
                return jQuery.get(url, {pagespeedninja: 'mobile'});
            })
        ).always(function () {

            jQuery.get(pagespeed_api, {strategy: 'desktop', url: url_random}).done(function (response) {
                var score = '';
                try {
                    score = Math.round(100 * response.lighthouseResult.categories.performance.score);
                    psnScore['desktop'] = response.lighthouseResult.audits;
                    rearrangeItems(response.lighthouseResult.audits, 'desktop');
                } catch (e) {
                    console.log(e);
                }
                updatePSIScore(score, document.getElementById('pagespeed_desktop'));
            }).fail(function () {
                updatePSIScore('', document.getElementById('pagespeed_desktop'));
            }).always(function () {

                jQuery.get(pagespeed_api, {strategy: 'mobile', url: url_random}).done(function (response) {
                    var score = '';
                    try {
                        score = Math.round(100 * response.lighthouseResult.categories.performance.score);
                        psnScore['mobile'] = response.lighthouseResult.audits;
                        rearrangeItems(response.lighthouseResult.audits, 'mobile');
                    } catch (e) {
                        console.log(e);
                    }
                    updatePSIScore(score, document.getElementById('pagespeed_mobile'));
                }).fail(function () {
                    updatePSIScore('', document.getElementById('pagespeed_mobile'));
                }).always(function () {
                    showReviewBanner();
                    savePageSpeedCached();
                });

            });
        });
    }

    function populateCheckboxes() {
        jQuery('#pagespeedninja_form').children('input[type=hidden]').each(function () {
            var id = this.id.split('_');
            if (id.length === 4) {
                var section = id[3];
                var checked = this.value === '1';
                var prefixes = ['mobile', 'desktop'];
                this.initstate = checked;
                for (var i = 0; i < prefixes.length; i++) {
                    var prefix = prefixes[i];
                    var element = document.getElementById('pagespeedninja_config_' + prefix + '_' + section);
                    if (element) {
                        element.checked = checked;
                        if (checked) {
                            element.parentNode.className += ' show';
                        }
                    }
                }
            }
        });
    }

    function basicLoadATFCSS() {
        var $enabled = jQuery('#pagespeedninja_config_psi_unused-css-rules');
        if ($enabled.length && $enabled.val() === '1') {
            var $css_abovethefoldstyle = jQuery('#pagespeedninja_config_css_abovethefoldstyle');
            if ($css_abovethefoldstyle.length && $css_abovethefoldstyle.val() === '') {
                var local = (document.getElementById('pagespeedninja_config_css_abovethefoldlocal').value === '1');
                autoGenerateATF('pagespeedninja_config_css_abovethefoldstyle', local);
            }
        }
    }

    function getNewScores() {
        if (newUrl === null || newAjax) {
            return;
        }

        // loading of new scores
        var url = newUrl;

        newAjax = true;
        newUrl = null;

        jQuery
            .get(url)
            .then(function () {
                if (newUrl !== null) {
                    return;
                }

                jQuery.get(pagespeed_api, {strategy: 'desktop', url: url}).done(function (response) {
                    var score = '';
                    try {
                        score = Math.round(100 * response.lighthouseResult.categories.performance.score);
                    } catch (e) {
                        console.log(e);
                    }
                    updatePSIScore(score, document.getElementById('pagespeed_desktop_new'));
                }).fail(function () {
                    updatePSIScore('', document.getElementById('pagespeed_desktop_new'));
                });

                jQuery.get(pagespeed_api, {strategy: 'mobile', url: url}).done(function (response) {
                    var score = '';
                    try {
                        score = Math.round(100 * response.lighthouseResult.categories.performance.score);
                    } catch (e) {
                        console.log(e);
                    }
                    updatePSIScore(score, document.getElementById('pagespeed_mobile_new'));
                }).fail(function () {
                    updatePSIScore('', document.getElementById('pagespeed_mobile_new'));
                });
            })
            .always(function () {
                newAjax = false;
            });
    }

    jQuery(document).ready(function () {
        populateCheckboxes();

        var $psn = jQuery('#pagespeedninja-content'),
            $form = jQuery('#pagespeedninja_form'),
            $thickbox_desktop = jQuery('.pagespeedninja #desktop .gps_result_new > a.thickbox'),
            $thickbox_mobile = jQuery('.pagespeedninja #mobile .gps_result_new > a.thickbox'),
            base_url = location.href.split('/').slice(0, -2).join('/') + '/';

        $psn.on('change', 'input[type=checkbox]', function () {
            var id = this.id.split('_');
            if (id.length === 4) {
                var thisprefix = id[2];
                var section = id[3];
                var checked = this.checked;

                var element = document.getElementById('pagespeedninja_config_psi_' + section);
                if (element) {
                    element.value = checked ? '1' : '0';
                }

                var prefixes = ['mobile', 'desktop'];
                for (var i = 0; i < prefixes.length; i++) {
                    var prefix = prefixes[i];
                    if (prefix !== thisprefix) {
                        element = document.getElementById('pagespeedninja_config_' + prefix + '_' + section);
                        if (element) {
                            element.checked = checked;
                        }
                    }
                }

                if (section === 'render-blocking-resources') {
                    basicLoadATFCSS();
                }
                $form.trigger('checkform.areYouSure');

                var data = $form.serialize();
                data += '&action=pagespeedninja_key&_ajax_nonce=' + pagespeedninja_nonce;
                jQuery.post(ajaxurl, data, function (key) {
                    var url = base_url + '?pagespeedninja=test&pagespeedninjakey=' + key;
                    newUrl = url;
                    $thickbox_desktop.attr('href', url + '&TB_iframe&width=971&height=588');
                    $thickbox_mobile.attr('href', url + '&TB_iframe&width=320&height=588');
                    jQuery('.gps_result_new').removeClass('hide');
                    jQuery('#pagespeed_desktop_new, #pagespeed_mobile_new')
                        .html('&nbsp;')
                        .removeClass('gps_error gps_warning gps_success')
                        .addClass('gps_loading');
                });
            }
        });
    });

    jQuery(window).on('load', function () {
        if (window.localStorage && loadPageSpeedCached()) {
            showReviewBanner();
        } else {
            loadPageSpeed();
        }
        setInterval(getNewScores, 200);

        basicLoadATFCSS();
    });
})();
