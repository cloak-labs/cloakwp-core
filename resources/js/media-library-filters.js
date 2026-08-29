/**
 * CloakWP — inject LibraryFilter dropdowns into Media Library grid / modal toolbars.
 *
 * Select filters become wp.media.view.AttachmentFilters. Custom filters fire
 * cloakwpMediaLibrary.onToolbar callbacks (and a jQuery event) so plugins can
 * attach their own views. Also covers ACF Image / Gallery / File pickers.
 */
(function (window, $) {
  'use strict';

  var filters = window.cloakwpMediaLibraryFilters || [];
  if (!Array.isArray(filters)) {
    filters = [];
  }

  var l10n = window.cloakwpMediaLibraryFilterL10n || {};
  var toolbarCallbacks = [];
  var clearCallbacks = [];
  var patchedBrowser = false;
  var boundAcfPopup = false;
  var listClearBound = false;

  function onToolbar(callback) {
    if (typeof callback === 'function') {
      toolbarCallbacks.push(callback);
    }
  }

  function onClear(callback) {
    if (typeof callback === 'function') {
      clearCallbacks.push(callback);
    }
  }

  function clearLabel() {
    return l10n.clear || 'Clear';
  }

  function ensureMediaViews() {
    return !!(window.wp && wp.media && wp.media.view && wp.media.view.AttachmentFilters);
  }

  function injectSelectFilter(browser, filter) {
    if (!browser.toolbar || !browser.collection || !filter || !filter.queryVar) {
      return;
    }

    var key = 'cloakwpFilter_' + filter.id;
    if (browser.toolbar.get(key)) {
      return;
    }

    var FilterView = wp.media.view.AttachmentFilters.extend({
      id: 'media-attachment-filter-' + filter.queryVar,
      className: 'attachment-filters cloakwp-media-library-filter',
      createFilters: function () {
        var built = {};
        var allProps = {};
        allProps[filter.queryVar] = null;
        built.all = {
          text: filter.allLabel || 'All',
          props: allProps,
          priority: 10,
        };
        $.each(filter.options || {}, function (value, text) {
          var props = {};
          props[filter.queryVar] = value;
          built[value] = {
            text: text,
            props: props,
          };
        });
        this.filters = built;
      },
    });

    var view = new FilterView({
      controller: browser.controller,
      model: browser.collection.props,
      priority: filter.priority,
    }).render();

    if (wp.media.view.Label) {
      browser.toolbar.set(
        key + 'Label',
        new wp.media.view.Label({
          value: filter.label || '',
          attributes: {
            for: view.id,
          },
          priority: filter.priority,
        }).render()
      );
    }

    browser.toolbar.set(key, view);
  }

  function isToolbarChrome($el) {
    return (
      $el.hasClass('spinner') ||
      $el.hasClass('cloakwp-media-library-filter-track') ||
      $el.hasClass('cloakwp-media-library-filters-clear') ||
      $el.hasClass('media-attachments-filter-heading') ||
      $el.is('.button, a.button, button, .search, .search-form, input[type="search"]')
    );
  }

  function ensureFilterTrack(browser) {
    if (!browser.toolbar || !browser.toolbar.$el) {
      return;
    }

    var $toolbar = browser.toolbar.$el;
    $toolbar.addClass('cloakwp-media-library-toolbar');

    var $secondary = $toolbar.find('.media-toolbar-secondary').first();
    if (!$secondary.length) {
      return;
    }

    var $spinner = $secondary.children('.spinner').first();
    var $track = $secondary.children('.cloakwp-media-library-filter-track');
    if (!$track.length) {
      $track = $('<div class="cloakwp-media-library-filter-track" role="group" />');
      if ($spinner.length) {
        $spinner.after($track);
      } else {
        $secondary.prepend($track);
      }
    }

    $track.children('.media-attachments-filter-heading').insertBefore($track);

    $secondary.children().each(function () {
      var $el = $(this);
      if (isToolbarChrome($el)) {
        return;
      }
      $el.addClass('cloakwp-media-library-filter');
      $track.append($el);
    });

    ensureClearButton($secondary, $track, browser);
    bindFilterActivity(browser);
    syncClearVisibility($toolbar, browser);
  }

  function ensureClearButton($secondary, $track, browser) {
    var $btn = $secondary.children('.cloakwp-media-library-filters-clear');
    if (!$btn.length) {
      $btn = $(
        '<button type="button" class="button-link cloakwp-media-library-filters-clear" hidden />'
      );
      if ($track && $track.length) {
        $track.after($btn);
      } else {
        $secondary.append($btn);
      }
    }
    $btn.text(clearLabel());
    $btn.off('click.cloakwpClear').on('click.cloakwpClear', function (e) {
      e.preventDefault();
      clearGridFilters(browser);
    });
  }

  function bindFilterActivity(browser) {
    if (!browser || browser._cloakwpClearActivityBound) {
      return;
    }
    browser._cloakwpClearActivityBound = true;
    if (browser.collection && browser.collection.props && typeof browser.collection.props.on === 'function') {
      browser.collection.props.on('change', function () {
        window.setTimeout(function () {
          syncClearVisibility(scopeFor(browser), browser);
        }, 0);
      });
    }
    if (browser.toolbar && browser.toolbar.$el) {
      browser.toolbar.$el.on('change.cloakwpClear', 'select.attachment-filters', function () {
        window.setTimeout(function () {
          syncClearVisibility(scopeFor(browser), browser);
        }, 0);
      });
    }
  }

  function scopeFor(browser) {
    if (browser && browser.toolbar && browser.toolbar.$el && browser.toolbar.$el.length) {
      return browser.toolbar.$el;
    }
    return $(document);
  }

  function filterModelKeys(filter) {
    var keys = [];
    function add(key) {
      if (key && keys.indexOf(key) === -1) {
        keys.push(key);
      }
    }
    if (!filter) {
      return keys;
    }
    add(filter.queryVar);
    if (Array.isArray(filter.modelKeys)) {
      filter.modelKeys.forEach(add);
    }
    if (filter.settings && Array.isArray(filter.settings.modelKeys)) {
      filter.settings.modelKeys.forEach(add);
    }
    return keys;
  }

  function filtersAreActive($scope, browser) {
    var active = false;
    $scope.find('select.attachment-filters').each(function () {
      if (this.options.length && this.selectedIndex > 0) {
        active = true;
        return false;
      }
    });
    if (active) {
      return true;
    }
    if (browser && browser.collection && browser.collection.props) {
      var props = browser.collection.props;
      var year = typeof props.get === 'function' ? props.get('year') : null;
      var month = typeof props.get === 'function' ? props.get('monthnum') : null;
      if ((year != null && year !== false && year !== '') || (month != null && month !== false && month !== '')) {
        return true;
      }
      filters.some(function (filter) {
        return filterModelKeys(filter).some(function (key) {
          var value = props.get(key);
          if (value != null && value !== '' && value !== false) {
            active = true;
            return true;
          }
          return false;
        });
      });
    }
    return active;
  }

  function syncClearVisibility($scope, browser) {
    var $root = $scope && $scope.length ? $scope : $(document);
    $root.find('.cloakwp-media-library-filters-clear').addBack('.cloakwp-media-library-filters-clear').each(function () {
      var $btn = $(this);
      var $context = $btn.closest('.media-toolbar, .wp-filter, .tablenav, .media-frame');
      if (!$context.length) {
        $context = $root;
      }
      this.hidden = !filtersAreActive($context, browser);
    });
  }

  function firstFilterProps(view) {
    if (!view || !view.filters) {
      return null;
    }
    if (view.filters.all && view.filters.all.props) {
      return { key: 'all', props: view.filters.all.props };
    }
    if (!view.$el || !view.$el.length) {
      return null;
    }
    var firstVal = view.$el.find('option').first().attr('value');
    if (firstVal != null && view.filters[firstVal] && view.filters[firstVal].props) {
      return { key: firstVal, props: view.filters[firstVal].props };
    }
    return null;
  }

  function eachToolbarView(toolbar, callback) {
    if (!toolbar) {
      return;
    }
    var seen = [];
    function visit(view) {
      if (!view || seen.indexOf(view) !== -1) {
        return;
      }
      seen.push(view);
      callback(view);
    }
    if (typeof toolbar.each === 'function') {
      toolbar.each(visit);
    }
    if (toolbar._views) {
      Object.keys(toolbar._views).forEach(function (id) {
        visit(toolbar._views[id]);
      });
    }
    if (toolbar.secondary && toolbar.secondary._views) {
      Object.keys(toolbar.secondary._views).forEach(function (id) {
        visit(toolbar.secondary._views[id]);
      });
    }
    if (toolbar.views && toolbar.views._views) {
      Object.keys(toolbar.views._views).forEach(function (selector) {
        var list = toolbar.views._views[selector];
        (Array.isArray(list) ? list : [list]).forEach(visit);
      });
    }
  }

  function collectIdleProps(browser) {
    var idle = {};
    eachToolbarView(browser && browser.toolbar, function (view) {
      var chosen = firstFilterProps(view);
      if (!chosen || !chosen.props) {
        return;
      }
      $.extend(idle, chosen.props);
    });
    return idle;
  }

  function clearGridFilters(browser) {
    var model = browser && browser.collection ? browser.collection.props : null;
    var $root = scopeFor(browser);
    var api = window.cloakwpMediaLibraryState;

    if (api && typeof api.beginClear === 'function') {
      api.beginClear();
    }

    // Custom UIs first so their model keys are gone before a new Query is built.
    clearCallbacks.forEach(function (callback) {
      callback(browser);
    });
    $(document).trigger('cloakwp.mediaLibrary.clearFilters', [browser]);

    filters.forEach(function (filter) {
      if (!model || typeof model.unset !== 'function') {
        return;
      }
      filterModelKeys(filter).forEach(function (key) {
        model.unset(key, { silent: true });
      });
    });

    // One set() of every AttachmentFilters idle props (type, date, custom
    // selects). Triggering each <select> change() lets the type filter's
    // model.set re-run Date.select() while year is still set, which puts
    // the month back. Date's "All" is year/monthnum: false, not unset.
    if (model && typeof model.set === 'function') {
      var idle = collectIdleProps(browser);
      if (model.get('year') != null || model.get('monthnum') != null) {
        if (!Object.prototype.hasOwnProperty.call(idle, 'year')) {
          idle.year = false;
        }
        if (!Object.prototype.hasOwnProperty.call(idle, 'monthnum')) {
          idle.monthnum = false;
        }
      }
      idle.ignore = +new Date();
      model.set(idle);
    }

    persistClearedFilters(model || {});
    syncClearVisibility($root, browser);
  }

  function persistClearedFilters(props) {
    var api = window.cloakwpMediaLibraryState;
    if (!api || typeof api.clearFilters !== 'function') {
      return;
    }
    api.clearFilters(props);
  }

  function bindListClear() {
    if (listClearBound) {
      return;
    }
    listClearBound = true;
    $(document).on('click.cloakwpClear', '.wp-filter .cloakwp-media-library-filters-clear, .tablenav .cloakwp-media-library-filters-clear', function () {
      clearCallbacks.forEach(function (callback) {
        callback(null);
      });
      $(document).trigger('cloakwp.mediaLibrary.clearFilters', [null]);
      persistClearedFilters({});
    });
    $(document).on(
      'change.cloakwpClear',
      '.wp-filter select.attachment-filters, .tablenav select.attachment-filters',
      function () {
        syncClearVisibility($(this).closest('.wp-filter, .tablenav'), null);
      }
    );
    syncClearVisibility($('.wp-filter, .tablenav'), null);
  }

  function injectFilters(browser) {
    if (!ensureMediaViews() || !browser || !browser.toolbar || !browser.collection) {
      return;
    }

    filters.forEach(function (filter) {
      if (filter.grid === 'select') {
        injectSelectFilter(browser, filter);
      }
    });

    toolbarCallbacks.forEach(function (callback) {
      callback(browser, filters);
    });

    $(document).trigger('cloakwp.mediaLibrary.toolbar', [browser, filters]);
    ensureFilterTrack(browser);
    if (typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(function () {
        ensureFilterTrack(browser);
      });
    }
  }

  function bindMediaFrame(frame) {
    if (!frame || frame._cloakwpMediaLibraryBound || typeof frame.on !== 'function') {
      return;
    }
    frame._cloakwpMediaLibraryBound = true;
    frame.on('content:activate:browse', function () {
      var browser = null;
      try {
        browser = frame.content.get();
      } catch (e) {
        return;
      }
      injectFilters(browser);
    });
  }

  function bindAcfMediaPopups() {
    if (boundAcfPopup) {
      return true;
    }
    if (!window.acf || typeof acf.addAction !== 'function') {
      return false;
    }
    acf.addAction('new_media_popup', function (popup) {
      patchMediaBrowser();
      if (popup && popup.frame) {
        bindMediaFrame(popup.frame);
      }
    });
    boundAcfPopup = true;
    return true;
  }

  function patchMediaBrowser() {
    if (!ensureMediaViews() || !wp.media.view.AttachmentsBrowser) {
      return false;
    }

    bindAcfMediaPopups();

    if (patchedBrowser) {
      return true;
    }

    var proto = wp.media.view.AttachmentsBrowser.prototype;
    var originalCreateToolbar = proto.createToolbar;
    proto.createToolbar = function () {
      originalCreateToolbar.apply(this, arguments);
      injectFilters(this);
    };

    patchedBrowser = true;
    return true;
  }

  window.cloakwpMediaLibrary = {
    filters: filters,
    onToolbar: onToolbar,
    onClear: onClear,
    clear: clearGridFilters,
    inject: injectFilters,
    patch: patchMediaBrowser,
  };

  if (!patchMediaBrowser()) {
    $(function () {
      patchMediaBrowser();
    });
  }

  $(function () {
    patchMediaBrowser();
    bindAcfMediaPopups();
    bindListClear();
  });
})(window, jQuery);
