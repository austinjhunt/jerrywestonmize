/* PageSpeed Ninja 1.4.5 | pagespeed.ninja/license.html */
(function () {
    'use strict';

    function streamoptimizer_check() {
        if (jQuery('.streamoptimizer:checked').val() === 'stream') {
            jQuery('.streamdisabled').attr('disabled', true);
        } else {
            jQuery('.streamdisabled').removeAttr('disabled');
        }
    }

    jQuery(document).ready(function () {
        streamoptimizer_check();
        jQuery('.streamoptimizer').on('change', streamoptimizer_check);

        var $psn = jQuery('#pagespeedninja-content'),
            $form = jQuery('#pagespeedninja_form');

        $psn.on('change', 'input[type=checkbox]', function () {
            var id = this.id.split('_');
            if (id.length === 4) {
                var section = id[3];
                var checked = this.checked;

                // Advanced
                var element = document.getElementById('pagespeedninja_config_psi_' + section);
                if (element) {
                    element.value = checked ? '1' : '0';
                    jQuery(element).closest('.header').next().toggleClass('disabled', !checked);
                }

                $form.trigger('checkform.areYouSure');
            }
        });

        function toggleSection($section) {
            $section.toggleClass('open');
            var opened = $section.hasClass('open'),
                $section_content = $section.parent().next(),
                section = $section_content.get(0),
                sectionHeight = section.scrollHeight + 48 + 8; // + padding of expanded section
            $section_content.toggleClass('show', opened);
            if (opened) {
                section.style.height = sectionHeight + 'px';
                section.addEventListener('transitionend', function resetHeight() {
                    section.removeEventListener('transitionend', resetHeight);
                    section.style.height = null;
                });
            } else {
                var elementTransition = section.style.transition;
                section.style.transition = '';
                requestAnimationFrame(function () {
                    section.style.height = sectionHeight + 'px';
                    section.style.transition = elementTransition;
                    requestAnimationFrame(function () {
                        section.style.height = 0 + 'px';
                    });
                });
            }
        }

        $psn.on('click', '.expando', function () {
            toggleSection(jQuery(this));
        });

        $psn.on('click', '.expando+.title', function () {
            toggleSection(jQuery(this).prev());
        });

        function updateCachesize(type) {
            jQuery.post(ajaxurl, {action: 'pagespeedninja_get_cache_size', _ajax_nonce: pagespeedninja_nonce, type: type}, function (response) {
                jQuery('#psn_cachesize_' + type + '_size').text(response.size);
                jQuery('#psn_cachesize_' + type + '_files').text(response.files);
            });
        }

        jQuery('#do_clear_images').on('click', function () {
            var $el = jQuery(this);
            $el.attr('disabled', 'disabled');
            jQuery.post(ajaxurl, {action: 'pagespeedninja_clear_images', _ajax_nonce: pagespeedninja_nonce}, function () {
                $el.removeAttr('disabled');
                updateCachesize('image');
            });
        });
        jQuery('#do_clear_image_errors').on('click', function () {
            var $el = jQuery(this);
            $el.attr('disabled', 'disabled');
            jQuery.post(ajaxurl, {action: 'pagespeedninja_clear_image_errors', _ajax_nonce: pagespeedninja_nonce}, function () {
                $el.removeAttr('disabled');
            });
        });
        jQuery('#do_clear_loaded').on('click', function () {
            var $el = jQuery(this);
            $el.attr('disabled', 'disabled');
            jQuery.post(ajaxurl, {action: 'pagespeedninja_clear_loaded', _ajax_nonce: pagespeedninja_nonce}, function () {
                $el.removeAttr('disabled');
                updateCachesize('loaded');
            });
        });
        jQuery('#do_clear_cache_expired').on('click', function () {
            var $el = jQuery(this);
            $el.attr('disabled', 'disabled');
            jQuery.post(ajaxurl, {action: 'pagespeedninja_clear_cache_expired', _ajax_nonce: pagespeedninja_nonce}, function () {
                $el.removeAttr('disabled');
                updateCachesize('static');
                updateCachesize('ress');
            });
        });
        jQuery('#do_clear_cache_all').on('click', function () {
            var $el = jQuery(this);
            $el.attr('disabled', 'disabled');
            jQuery.post(ajaxurl, {action: 'pagespeedninja_clear_cache_all', _ajax_nonce: pagespeedninja_nonce}, function () {
                $el.removeAttr('disabled');
                updateCachesize('static');
                updateCachesize('ress');
            });
        });
        jQuery('#do_clear_pagecache_expired').on('click', function () {
            var $el = jQuery(this);
            $el.attr('disabled', 'disabled');
            jQuery.post(ajaxurl, {action: 'pagespeedninja_clear_pagecache_expired', _ajax_nonce: pagespeedninja_nonce}, function () {
                $el.removeAttr('disabled');
                updateCachesize('page');
            });
        });
        jQuery('#do_clear_pagecache_all').on('click', function () {
            var $el = jQuery(this);
            $el.attr('disabled', 'disabled');
            jQuery.post(ajaxurl, {action: 'pagespeedninja_clear_pagecache_all', _ajax_nonce: pagespeedninja_nonce}, function () {
                $el.removeAttr('disabled');
                updateCachesize('page');
            });
        });

        updateCachesize('image');
        updateCachesize('loaded');
        updateCachesize('static');
        updateCachesize('ress');
        updateCachesize('page');

        detectPreset();

        jQuery('table.rules-list').each(function () {
            updateExcludeRulesTable(this.getAttribute('data-rules-id'));
        });

        jQuery('#pagespeedninja_filter')
            .on('change', applyFilter)
            .on('keyup', applyFilter);
    });

    function detectPreset() {
        if (!window.pagespeedninja_presets) {
            return;
        }
        for (var preset in pagespeedninja_presets) {
            var match = true;
            for (var option in pagespeedninja_presets[preset]) {
                var $els = jQuery('input[name="pagespeedninja_config[' + option + ']"]:not([type=hidden]),select[name="pagespeedninja_config[' + option + ']"]');
                if ($els.length) {
                    $els.each(function () {
                        var $el = jQuery(this),
                            value = $el.val();
                        switch (this.nodeName) {
                            case 'INPUT':
                                if ($el.attr('type') === 'checkbox') {
                                    value = +$el.prop('checked'); // convert boolean to integer
                                } else if ($el.attr('type') === 'radio' && !$el.prop('checked')) {
                                    return;
                                }
                                break;
                            case 'SELECT':
                                break;
                            default:
                                console.log('Unknown node: ' + this.nodeName);
                                return;
                        }
                        if (value != pagespeedninja_presets[preset][option]) {
                            match = false;
                            return false;
                        }
                    });
                } else {
                    console.log('Option not found: ' + option);
                }
            }
            if (match === true) {
                jQuery('#pagespeedninja_preset_' + preset).prop('checked', true);
                return;
            }
        }
        jQuery('#pagespeedninja_preset_custom').prop('checked', true);
    }

    window.pagespeedninjaLoadPreset = function (preset) {
        if (preset === '') {
            document.getElementById('pagespeedninja_form').reset();
            return;
        }
        if (!(preset in pagespeedninja_presets)) {
            return;
        }
        for (var option in pagespeedninja_presets[preset]) {
            jQuery('input[name="pagespeedninja_config[' + option + ']"]:not([type=hidden]),select[name="pagespeedninja_config[' + option + ']"]').each(function () {
                var $el = jQuery(this);
                switch (this.nodeName) {
                    case 'INPUT':
                        if ($el.attr('type') === 'checkbox') {
                            $el.prop('checked', !!pagespeedninja_presets[preset][option]);
                        } else if ($el.attr('type') === 'radio') {
                            $el.prop('checked', pagespeedninja_presets[preset][option] == $el.val());
                        } else {
                            $el.val(pagespeedninja_presets[preset][option]);
                        }
                        break;
                    case 'SELECT':
                        $el.val(pagespeedninja_presets[preset][option]);
                        break;
                }
            });
        }
        jQuery('#pagespeedninja_form').trigger('checkform.areYouSure');
    };

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function updateExcludeRulesTable(id) {
        var value = document.getElementById('pagespeedninja_config_' + id).value,
            table = document.querySelector('table.rules-list[data-rules-id=' + id + ']'),
            innerHtml;
        if (value === '') {
            innerHtml = '<tr><td><i>' + table.getAttribute('data-notset-text') + '</i></tr></td>';
        } else {
            innerHtml = '';
            var lines = value.split('\n');
            for (var i = 0; i < lines.length; i++) {
                var line = lines[i].trim();
                if (line !== '') {
                    var match = /^(.*?)([~*^$]?=)(.*)$/.exec(line);
                    if (match === null) {
                        innerHtml += '<tr><td colspan="3" class="rules-error">'
                            + escapeHtml(line)
                            + '</td></tr>';
                    } else {
                        var attr = match[1],
                            cond = match[2],
                            expr = match[3];
                        innerHtml += '<tr><td>'
                            + (attr === '' ? '<i>content</i>' : attr)
                            + '</td><td>'
                            + cond
                            + '</td><td>'
                            + escapeHtml(expr)
                            + '</td></tr>';
                    }
                }
            }
        }
        table.innerHTML = innerHtml;
    }

    function getExcludeRuleTitle(id) {
        return jQuery('#pagespeedninja_config_' + id).parents('.line').children('.title').first().text();
    }

    var current_tb_exclude_id;

    window.showExcludeListPopup = function (id) {
        current_tb_exclude_id = id;
        var isJs = id.startsWith('js_');
        jQuery('#rules-urls-list')
            .removeClass('js css').addClass(isJs ? 'js' : 'css')
            .find('input[type=checkbox]').prop('checked', false);
        var regex = new RegExp('^' + (isJs ? 'src' : 'href') + '=(.*)$');
        var urls = [];
        var rules = document.getElementById('pagespeedninja_config_' + id).value.split('\n');
        for (var i = 0; i < rules.length; i++) {
            var match = regex.exec(rules[i].trim());
            if (match !== null) {
                urls.push(match[1]);
            }
        }
        jQuery('#rules-urls-list tr').each(function () {
            var $row = jQuery(this);
            if (urls.includes($row.children('td').last().text())) {
                $row.find('input').prop('checked', true);
            }
        });
        tb_show(getExcludeRuleTitle(id), '?TB_inline&inlineId=psn-ruleslist-popup&width=600&height=405');
    };

    window.showExcludeListPopup_apply = function () {
        var id = current_tb_exclude_id,
            isJs = id.startsWith('js_'),
            attr = isJs ? 'src' : 'href',
            allURLs = [],
            checkedURLs = [],
            regex = new RegExp('^' + attr + '=(.*)$'),
            configInput = document.getElementById('pagespeedninja_config_' + id),
            origRules = configInput.value.split('\n'),
            newRules = [],
            i;
        jQuery('#rules-urls-list input[type=checkbox]').each(function () {
            var url = jQuery(this).parents('tr').children('td:last-child').text();
            allURLs.push(url);
            if (this.checked) {
                checkedURLs.push(url);
            }
        });
        for (i = 0; i < origRules.length; i++) {
            var rule = origRules[i].trim();
            if (rule !== '') {
                var match = regex.exec(rule);
                if (match === null || !allURLs.includes(match[1])) {
                    newRules.push(rule);
                }
            }
        }
        for (i = 0; i < checkedURLs.length; i++) {
            newRules.push(attr + '=' + checkedURLs[i]);
        }
        configInput.value = newRules.join('\n');
        updateExcludeRulesTable(id);
        tb_remove();
        jQuery('#pagespeedninja_form').trigger('checkform.areYouSure');
    };

    window.showExcludeRulesPopup = function (id) {
        current_tb_exclude_id = id;
        document.getElementById('psn-rules-popup-textarea').value =
            document.getElementById('pagespeedninja_config_' + id).value;
        tb_show(getExcludeRuleTitle(id), '?TB_inline&inlineId=psn-rules-popup&width=600&height=405');
    };

    window.showExcludeRulesPopup_apply = function () {
        var id = current_tb_exclude_id;
        document.getElementById('pagespeedninja_config_' + id).value =
            document.getElementById('psn-rules-popup-textarea').value;
        updateExcludeRulesTable(id);
        tb_remove();
        jQuery('#pagespeedninja_form').trigger('checkform.areYouSure');
    };

    function isMatched(text, query) {
        return text.toLowerCase().indexOf(query) >= 0;
    }

    function applyFilter () {
        var query = jQuery('#pagespeedninja_filter').val().toLowerCase();

        jQuery('#pagespeedninja_form div.line').each(function () {
            var $line = jQuery(this),
                match = false;
            if (query === '') {
                match = true;
            } else {
                var $title = $line.children('div.title');
                if ($title.length) {
                    match = isMatched($title.text(), query) || isMatched($title.data('tooltip'), query);
                    if (!match) {
                        var $field = $line.children('.field');
                        match =
                            $field.children('input[type=text], input[type=button], textarea').is(function () {
                                return isMatched(jQuery(this).val(), query);
                            }) ||
                            $field.children('label, table.rules-list').is(function () {
                                return isMatched(jQuery(this).text(), query);
                            }) ||
                            $field.find('select > option').is(function () {
                                return isMatched(jQuery(this).text(), query);
                            });
                    }
                }
            }
            $line.toggleClass('unmatched', !match);
        });

        var nSections = 0;
        jQuery('#pagespeedninja_form div.content').each(function () {
            var $section = jQuery(this),
                match = $section.children('div.line:not(.unmatched)').length > 0;
            $section.parent().toggleClass('hidden', !match);
            if (match) {
                nSections++;
            }
        });
        if (nSections <= 2) {
            jQuery('#pagespeedninja_form > div:not(.hidden) > div.content')
                .addClass('show')
                .prev('.header').children('.expando').addClass('open');
        }
    }

})();
