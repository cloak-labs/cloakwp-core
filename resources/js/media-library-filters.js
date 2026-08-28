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

  var toolbarCallbacks = [];
  var patchedBrowser = false;
  var boundAcfPopup = false;

  function onToolbar(callback) {
    if (typeof callback === 'function') {
      toolbarCallbacks.push(callback);
    }
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
      className: 'attachment-filters',
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
  });
})(window, jQuery);
