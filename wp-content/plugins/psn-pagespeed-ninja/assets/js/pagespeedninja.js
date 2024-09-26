/* PageSpeed Ninja 1.4.5 | pagespeed.ninja/license.html */
(function () {
    'use strict';

    var tab_name,
        psn_apikey,
        tooltipsContainer;

    function getQueryParameterByName(name) {
        var match = (new RegExp('[?&]' + name + '=([^&]*)')).exec(location.search);
        return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
    }

    function adjustTooltips() {
        var container = tooltipsContainer.getBoundingClientRect();
        [...document.querySelectorAll('#pagespeedninja-content [data-tooltip]')].forEach(function (el) {
            var pos = el.getBoundingClientRect(),
                tooltipWidth = Math.max(300, Math.min(420, window.innerWidth)),
                offsetLeft = (container.left + 5) - 0.5 * (pos.left + pos.right - tooltipWidth),
                offsetRight = 0.5 * (pos.left + pos.right + tooltipWidth) - (container.right - 5),
                offset = 0;
            if (offsetLeft > 0) {
                offset = Math.round(offsetLeft);
            } else if (offsetRight > 0) {
                offset = -Math.round(offsetRight);
            }
            el.style.setProperty('--tooltip-offset', offset + 'px');
        });
    }

    jQuery(document).ready(function () {
        var $psn = jQuery('#pagespeedninja-content'),
            $form = jQuery('#pagespeedninja_form'),
            plugin_name = 'pagespeedninja';

        tab_name = getQueryParameterByName('page') === plugin_name ? 'general' : 'advanced';
        psn_apikey = document.getElementById('pagespeedninja_config_apikey').value;

        if (psn_apikey === '') {
            jQuery('#do_subscription_getapikey').css('display', 'block');
            jQuery('#do_subscription_title').removeClass('loading').html('<b><i>no</i></b>');
            jQuery('#do_subscription_limit').text('0');
        } else if (jQuery('#do_subscription_title').length > 0) {
            jQuery.post({
                url: 'https://api.pagespeed.ninja/v1/checkapikey',
                cache: false,
                data: {
                    apikey: psn_apikey,
                    app: 'wp-psn',
                    version: pagespeedninja_version,
                    domain: location.hostname
                },
                success: function (response) {
                    var $title = jQuery('#do_subscription_title').removeClass('loading');
                    if (response.status === 'invalid') {
                        jQuery('#do_subscription_getapikey').css('display', 'block');
                        $title.text('Incorrect API key');
                        jQuery('#do_subscription_limit').text('0');
                    } else {
                        var title = '<b>' + response.plan_title + '</b>';
                        if (response.status === 'expired') {
                            $title.html(title + ' (expired)');
                        } else if (response.days_left >= 0 && response.days_left <= 30) {
                            $title.html(title + ' (' + response.days_left + ' days left)');
                        } else {
                            $title.html(title);
                        }
                        if (response.plan_title === 'Free') {
                            jQuery('#do_subscription_upgrade').css('display', 'inline-block');
                        }
                        jQuery('#do_subscription_limit').text(response.weekly_limit);
                    }
                },
                error: function () {
                    jQuery('#do_subscription_title')
                        .removeClass('loading')
                        .text('connection error');
                    jQuery('#do_subscription_limit').text('0');
                }
            });
        }

        $form.areYouSure({
            fieldSelector: 'input:not([type=submit]):not([type=button]):not([type=file]),select,textarea'
        }).on('dirty.areYouSure', function () {
            $psn.find('a.save').removeClass('disabled');
        }).on('clean.areYouSure', function () {
            $psn.find('a.save').addClass('disabled');
        });
        $psn.find('a.save').on('click', function () {
            if (!jQuery(this).hasClass('disabled')) {
                jQuery('#pagespeedninja_form').removeClass('dirty').submit();
                //document.getElementById('pagespeedninja_form').submit();
            }
            return false;
        });

        $psn.find('a.general').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            location.href = '?page=' + plugin_name;
        });
        $psn.find('a.advanced').on('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            location.href = '?page=' + plugin_name + '_advanced';
        });

        var $email_popup = jQuery('#psn-licensekey-notice-wrap');
        if ($email_popup.length) {
            var $inputEmail = $email_popup.find('input[type=email]'),
                btnRegister = $email_popup.find('input[type=submit]')[0],
                emailRegex = /^(?:[!#$%&'*+-/0-9=?^_`a-z{|}~]+(?:\.[!#$%&'*+-/0-9=?^_`a-z{|}~]+)*|"(?:[!#-\[\]-~]|\\[!-~])*")@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i;

            function validateEmailForm() {
                btnRegister.disabled = !emailRegex.test($inputEmail.val().trim());
            }

            $inputEmail.on('input', validateEmailForm);
            validateEmailForm();

            $email_popup.find('form').on('submit', function () {
                $email_popup.hide();
                var email = $inputEmail.val();
                jQuery('#pagespeedninja_config_email').val(email);
            });
        }

        tooltipsContainer = document.querySelector('#pagespeedninja-content .tooltip-container');
        adjustTooltips();
        if (window.ResizeObserver) {
            new ResizeObserver(adjustTooltips).observe(tooltipsContainer);
        } else {
            jQuery(document).on('resize wp-collapse-menu', adjustTooltips);
        }
    });

    function setATFText(id, content) {
        jQuery('#' + id).removeAttr('disabled').val(content);
        jQuery('#pagespeedninja_form').trigger('checkform.areYouSure');
        if (tab_name === 'general') {
            jQuery('#pagespeedninja_atfcss_notice').removeClass('hidden');
        }
    }

    function updateATFExternal(id, url) {
        jQuery.post({
            url: 'https://api.pagespeed.ninja/v1/getcss',
            cache: false,
            data: {
                url: url,
                apikey: psn_apikey
            },
            success: function (content) {
                setATFText(id, content);
            }
        });
    }

    function updateATFInternal(id, url) {
        getATFCSS(url, function (content) {
            setATFText(id, content);
        });
    }

    window.autoGenerateATF = function (id, local) {
        jQuery('#' + id).attr('disabled', 'disabled');
        var url = location.href.split('/wp-admin/')[0] + '/?pagespeedninja=no';
        // get current locality state
        if (local) {
            updateATFInternal(id, url);
        } else {
            updateATFExternal(id, url);
        }
    };

})();
