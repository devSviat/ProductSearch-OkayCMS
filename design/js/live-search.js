/* global okay, ut_tracker */
(function ($) {
    'use strict';

    var ROUTE = 'ProductSearch.suggestions';
    var UNSAFE_SCHEME = /^(javascript|data|vbscript)\s*:/i;

    function escapeAttr(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;');
    }

    function safeUrl(u) {
        var s = String(u || '').trim();
        return UNSAFE_SCHEME.test(s) || s.indexOf('//') === 0 ? '#' : s;
    }

    function highlight(text, query) {
        var raw = String(text);
        var q = String(query || '').trim();
        if (!q) {
            return escapeAttr(raw);
        }
        var esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return escapeAttr(raw).replace(new RegExp('(' + esc + ')', 'gi'), '<strong>$1</strong>');
    }

    function formatPrice(item) {
        return escapeAttr(
            (String(item.price != null ? item.price : '') + ' ' + String(item.currency != null ? item.currency : '')).trim()
        );
    }

    function thumbMarkup(imageSrc) {
        if (imageSrc) {
            return '<span class="fn-ps-thumbFrame"><img src="' + escapeAttr(safeUrl(imageSrc)) + '" alt="" decoding="async"></span>';
        }
        if (typeof okay !== 'undefined' && okay.product_search_no_image_svg) {
            return '<span class="fn-ps-thumbFrame fn-ps-thumbFrame--empty">' + okay.product_search_no_image_svg + '</span>';
        }
        return '<span class="fn-ps-thumbFrame"></span>';
    }

    function minChars() {
        if (typeof okay !== 'undefined' && okay.product_search_min_chars != null) {
            var n = parseInt(okay.product_search_min_chars, 10);
            if (!isNaN(n) && n > 0) {
                return n;
            }
        }
        return 2;
    }

    function popularList() {
        if (typeof okay === 'undefined' || !Array.isArray(okay.product_search_popular_queries)) {
            return [];
        }
        return okay.product_search_popular_queries;
    }

    function popularTitle() {
        if (typeof okay !== 'undefined' && okay.product_search_popular_title) {
            return String(okay.product_search_popular_title);
        }
        return 'Популярні запити';
    }

    $(function () {
        var $form = $('#fn_search');
        var $in = $form.find('.fn_live_product_search').first();
        if (!$in.length || !$form.length || typeof okay === 'undefined' || !okay.router || !okay.router[ROUTE]) {
            return;
        }
        if (typeof $in.devbridgeAutocomplete !== 'function') {
            return;
        }

        var mc = minChars();
        var popular = popularList();
        var blurTimer;
        var track = typeof ut_tracker !== 'undefined' ? ut_tracker : null;

        $form.children('.autocomplete-suggestions').remove();

        var ddId = 'fn_ps_dd_' + ($form.attr('id') || 'search').replace(/[^a-z0-9_-]/gi, '_');
        var $host = $('<div>', { id: ddId, class: 'fn-ps-dropdown' }).appendTo($form);

        var $popular = $();

        function acInstance() {
            return $in.data('autocomplete');
        }

        function cancelAutocompleteBlurHide() {
            var ac = acInstance();
            if (ac && ac.blurTimeoutId) {
                clearTimeout(ac.blurTimeoutId);
                ac.blurTimeoutId = null;
            }
        }

        function buildPopularPanel() {
            if (!popular.length) {
                return $();
            }
            var $p = $('<div>', { class: 'fn-ps-popular' });
            $p.append($('<div>', { class: 'fn-ps-popular__title', text: popularTitle() }));
            var $ul = $('<ul>', { class: 'fn-ps-popular__list' }).appendTo($p);
            popular.forEach(function (q) {
                if (!q) {
                    return;
                }
                var $li = $('<li>').appendTo($ul);
                $('<button>', { type: 'button', class: 'fn-ps-popular__item', text: String(q) })
                    .on('click', function () {
                        clearTimeout(blurTimer);
                        cancelAutocompleteBlurHide();
                        $in.val(q).trigger('input').trigger('keyup').focus();
                        setTimeout(function () {
                            openDropdown();
                            placePopularBelowSuggestions();
                            var ac = acInstance();
                            var len = $in.val().trim().length;
                            if (ac && len >= mc) {
                                ac.onValueChange();
                            } else if (ac) {
                                ac.hide();
                            }
                        }, 0);
                    })
                    .appendTo($li);
            });
            return $p;
        }

        function placePopularBelowSuggestions() {
            if (!popular.length || !$host.length) {
                return;
            }
            var $sug = $host.children('.autocomplete-suggestions').first();
            if (!$sug.length) {
                return;
            }
            $popular = $host.children('.fn-ps-popular').first();
            if (!$popular.length) {
                $popular = buildPopularPanel();
            }
            $popular.insertAfter($sug);
        }

        function openDropdown() {
            $host.addClass('fn-ps-dropdown--open');
        }

        function closeDropdown() {
            $host.removeClass('fn-ps-dropdown--open');
        }

        function scheduleClose() {
            clearTimeout(blurTimer);
            blurTimer = setTimeout(function () {
                var ae = document.activeElement;
                if (ae === $in[0] || (ae && $host[0] && $.contains($host[0], ae))) {
                    return;
                }
                closeDropdown();
            }, 220);
        }

        $in.devbridgeAutocomplete({
            serviceUrl: okay.router[ROUTE],
            minChars: mc,
            deferRequestBy: 180,
            appendTo: '#' + ddId,
            maxHeight: 280,
            noCache: false,
            crossDomain: true,
            onSearchStart: function () {
                openDropdown();
                placePopularBelowSuggestions();
                if (track && track.start) {
                    track.start('search_products');
                }
            },
            onSearchComplete: function () {
                openDropdown();
                placePopularBelowSuggestions();
                if (track && track.end) {
                    track.end('search_products');
                }
            },
            onSelect: function () {
                closeDropdown();
                $form.trigger('submit');
            },
            transformResult: function (response) {
                var parsed;
                try {
                    parsed = JSON.parse(response);
                } catch (e) {
                    parsed = null;
                }
                if (!parsed || typeof parsed !== 'object' || !Array.isArray(parsed.suggestions)) {
                    parsed = { suggestions: [] };
                }
                $in.devbridgeAutocomplete('setOptions', {
                    triggerSelectOnValidInput: parsed.suggestions.length === 1
                });
                return parsed;
            },
            formatResult: function (item, q) {
                return (
                    '<div class="fn-ps-thumb">' + thumbMarkup(item.image) + '</div>' +
                    '<a class="fn-ps-title" href="' + escapeAttr(safeUrl(item.url || '#')) + '">' + highlight(item.value || '', q) + '</a>' +
                    '<span class="fn-ps-price">' + formatPrice(item) + '</span>'
                );
            }
        });

        (function bindDropdownPointerGuards() {
            var hostEl = $host[0];
            if (!hostEl) {
                return;
            }
            var insideGuard = '.fn-ps-popular, .autocomplete-suggestions, .autocomplete-suggestion';
            hostEl.addEventListener(
                'mousedown',
                function (e) {
                    if (!$(e.target).closest(insideGuard).length) {
                        return;
                    }
                    e.preventDefault();
                    cancelAutocompleteBlurHide();
                },
                true
            );
            // Не використовувати preventDefault на touch* — тоді на мобільних не спрацьовує click по кнопках.
            hostEl.addEventListener(
                'touchend',
                function (e) {
                    if (!$(e.target).closest('.fn-ps-popular, .autocomplete-suggestion').length) {
                        return;
                    }
                    cancelAutocompleteBlurHide();
                },
                { passive: true }
            );
        })();

        placePopularBelowSuggestions();

        $in.on('focus', function () {
            clearTimeout(blurTimer);
            cancelAutocompleteBlurHide();
            placePopularBelowSuggestions();
            var t = $in.val().trim();
            if (popular.length || t.length >= mc) {
                openDropdown();
            }
        });

        $in.on('input', function () {
            placePopularBelowSuggestions();
            var t = $in.val().trim();
            if (!popular.length && t.length < mc) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        $in.on('blur', scheduleClose);
    });
}(jQuery));
