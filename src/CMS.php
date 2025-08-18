<?php

namespace CloakWP\Core;

use CloakWP\Core\Enqueue\Script;
use CloakWP\Core\Enqueue\Stylesheet;
use Snicco\Component\BetterWPAPI\BetterWPAPI;
use Inpsyde\WpContext;

use InvalidArgumentException;
use WP_Block_Type_Registry;

/**
 * A class that provides a simpler API around some core WordPress functions
 */
class CMS extends BetterWPAPI
{
  /**
   * Stores the CMS Singleton instance.
   */
  private static $instance;

  /**
   * Stores one or more PostType instances.
   */
  protected array $postTypes = [];

  public static WpContext $context;

  /**
   * Initialize the class and set its properties.
   */
  public function __construct()
  {
    self::$context = WpContext::determine();
  }

  /**
   * Returns the CMS Singleton instance.
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Enqueue a single Stylesheet or Script
   */
  public function enqueueAsset(Stylesheet|Script $asset): static
  {
    $asset->enqueue();
    return $this;
  }

  /**
   * Provide an array of Stylesheets and/or Scripts to enqueue
   */
  public function assets(array $assets): static
  {
    foreach ($assets as $asset) {
      $this->enqueueAsset($asset);
    }
    return $this;
  }

  /**
   * Provide an array of PostType class instances, defining your Custom Post Types and their configurations.
   */
  public function postTypes(array $postTypes): static
  {
    $validPostTypes = [];

    // validate & register each post type
    foreach ($postTypes as $postType) {
      if (!is_object($postType) || !method_exists($postType, 'register'))
        continue; // invalid post type

      $validPostTypes[] = $postType;
      $postType->register();
    }

    // save all valid PostType objects into the CMS singleton's state, so anyone can access/process them
    $this->postTypes = array_merge($this->postTypes, $validPostTypes); // todo: might need a custom merge method here to handle duplicates?

    return $this;
  }

  public function getPostType(string $postTypeSlug)
  {
    return array_filter($this->postTypes, fn($postType) => $postType->slug == $postTypeSlug);
  }
  public function getPostTypeByPostId(int $postId)
  {
    return $this->getPostType(get_post_type($postId));
  }

  /**
   * Define which core blocks to enable in Gutenberg (an array of block names). Any that aren't defined will be excluded from use.
   * You can also specify post type rules, so that certain blocks are only allowed on certain post types -- for example:
   * 
   * enabledCoreBlocks([
   *  'core/paragraph' => [
   *    'postTypes' => ['post', 'page'] // will only be available on posts of type 'post' and 'page'
   *  ],
   *  'core/heading', // will be available to all post types
   *  ...
   * ]) 
   */
  public function enabledCoreBlocks(array|bool $blocksToInclude): static
  {
    add_filter('allowed_block_types_all', function ($allowed_block_types, $editor_context) use ($blocksToInclude) {
      return $this->getAllowedBlocks($editor_context, $blocksToInclude);
    }, 10, 2);

    return $this;
  }

  private function getAllowedBlocks(object $editorContext, array|bool $blocks): bool|array
  {
    $registeredBlockTypes = WP_Block_Type_Registry::get_instance()->get_all_registered();
    $registeredBlockTypeKeys = array_keys($registeredBlockTypes);

    $currentPostType = $editorContext->post->post_type;
    $finalAllowedBlocks = array_filter($registeredBlockTypeKeys, fn($b) => !str_starts_with($b, 'core/')); // start with all non-core blocks, then we'll add user-provided core blocks to this list

    // Always include core/block (reusable blocks) -- this ensures we can still create patterns
    $finalAllowedBlocks[] = 'core/block';

    if (is_array($blocks)) {
      foreach ($blocks as $key => $value) {
        if (is_string($value)) {
          $finalAllowedBlocks[] = $value;
        } else if (is_array($value)) {
          $blockName = $key;
          if (isset($value['postTypes'])) {
            if (is_array($value['postTypes'])) {
              foreach ($value['postTypes'] as $postType) {
                if ($currentPostType == $postType) {
                  $finalAllowedBlocks[] = $blockName;
                }
              }
            } else {
              throw new InvalidArgumentException("postTypes argument must be an array of post type slugs");
            }
          } else {
            $finalAllowedBlocks[] = $blockName;
          }
        } else {
          continue; // current $block is invalid, move on to next one.
        }
      }
    } else if (is_bool($blocks)) {
      return $blocks;
    } else {
      throw new InvalidArgumentException("Invalid argument type passed to coreBlocks() -- must be of type array or boolean.");
    }

    return array_values($finalAllowedBlocks);
  }

  /**
   * Check if the current admin page is a block editor. Should only call this method as early is "init" action with priority 4 (i.e. after post types are registered).
   * 
   * @return bool
   */
  public function isBlockEditor(): bool
  {
    if (!is_admin()) {
      return false;
    }

    // Early detection using $_GET and $_POST
    $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0);

    // If we have a post ID, check if that post supports the block editor
    if ($post_id && function_exists('use_block_editor_for_post')) {
      $post = get_post($post_id);
      if ($post && use_block_editor_for_post($post))
        return true;
    }

    // Fallback to screen-based checks if available
    if (function_exists('get_current_screen')) {
      $current_screen = get_current_screen();
      if ($current_screen && $current_screen->is_block_editor()) {
        return true;
      }

      if ($current_screen && method_exists($current_screen, 'use_block_editor_for_post_type') && $current_screen->use_block_editor_for_post_type()) {
        return true;
      }
    }

    return false;
  }

  public function disableLegacyCustomizer(): static
  {
    add_action('init', function () {
      add_filter(
        'map_meta_cap',
        function ($caps = [], $cap = '', $user_id = 0, $args = []) {
          if ($cap === 'customize')
            return ['nope'];
          return $caps;
        },
        10,
        4
      );
    }, 10);

    add_action('admin_init', function () {
      remove_action(
        'plugins_loaded',
        '_wp_customize_include',
        10
      );

      remove_action(
        'admin_enqueue_scripts',
        '_wp_customize_loader_settings',
        11
      );

      add_action('load-customize.php', function () {
        wp_die(
          __('The Customizer is currently disabled.', 'cloakwp')
        );
      });
    }, 10);

    // remove "Customize" submenu from Appearance menu
    add_action('admin_head', function () {
      global $submenu;
      if (isset($submenu['themes.php'])) {
        foreach ($submenu['themes.php'] as $index => $menu_item) {
          foreach ($menu_item as $value) {
            if (strpos($value, 'customize') !== false) {
              unset($submenu['themes.php'][$index]);
            }
          }
        }
      }
    });

    return $this;
  }

  /**
   * Throttle the WP "heartbeat" to a lower interval (default is every 10s) on the edit post screen, 
   * reducing AJAX requests and therefore CPU load.
   * 
   * @param int $heartbeatInterval The interval in seconds to throttle the heartbeat to.
   * @return static
   */
  public function throttleHeartbeat(int $heartbeatInterval = 60): static
  {
    if ($heartbeatInterval < 10) {
      throw new InvalidArgumentException("Heartbeat interval should be at least 10 seconds.");
    }

    add_filter('heartbeat_settings', function ($settings) use ($heartbeatInterval) {
      $settings['interval'] = $heartbeatInterval;
      return $settings;
    }, 9999, 1);

    if (self::$context->isBackoffice()) {
      /**
       * Gutenberg hard-codes the heartbeat interval to 10s, ignoring the `heartbeat_settings` PHP filter. The following code
       * dequeues the core-injected heartbeat and re-registers it with the desired interval.
       */
      add_action('admin_enqueue_scripts', function () use ($heartbeatInterval) {
        // Dequeue core-injected heartbeat (which includes the inline 10s interval script)
        wp_deregister_script('heartbeat');

        // Re-register heartbeat without inline override
        wp_register_script(
          'heartbeat',
          includes_url('/js/heartbeat.min.js'),
          ['jquery'],
          false,
          true
        );

        $settings = apply_filters('heartbeat_settings', [
          'interval' => $heartbeatInterval,
          'transport' => 'long-polling',
        ]);

        // Localize settings manually
        wp_localize_script('heartbeat', 'heartbeatSettings', $settings);

        // Re-enqueue it
        wp_enqueue_script('heartbeat');
      }, 100);
    }

    return $this;
  }

  public function disableWidgets(): static
  {
    add_action('admin_head', function () {
      remove_submenu_page('themes.php', 'widgets.php');
    });

    return $this;
  }

  public function disableComments(): static
  {
    add_filter('comments_open', '__return_false');

    add_action('admin_menu', function () {
      remove_menu_page('edit-comments.php');
    });

    add_action('wp_before_admin_bar_render', function () {
      global $wp_admin_bar;
      $wp_admin_bar->remove_menu('comments');
    });

    return $this;
  }

  public function disableEmojis(): static
  {
    // remove emoji detection scripts and styles.
    add_action('init', function () {
      remove_action('wp_head', 'print_emoji_detection_script', 7);
      remove_action('admin_print_scripts', 'print_emoji_detection_script');
      remove_action('wp_print_styles', 'print_emoji_styles');
      remove_action('admin_print_styles', 'print_emoji_styles');
      remove_filter('the_content_feed', 'wp_staticize_emoji');
      remove_filter('comment_text_rss', 'wp_staticize_emoji');
      remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    });

    // remove TinyMCE emojis.
    add_filter('tiny_mce_plugins', function ($plugins) {
      if (is_array($plugins)) {
        return array_diff($plugins, ['wpemoji']);
      } else {
        return [];
      }
    });

    return $this;
  }

  public function disableSmilies(): static
  {
    remove_filter('the_content', 'convert_smilies', 20);
    remove_filter('the_excerpt', 'convert_smilies');
    remove_filter('the_post_thumbnail_caption', 'convert_smilies');
    remove_filter('comment_text', 'convert_smilies', 20);
    remove_filter('widget_text_content', 'convert_smilies', 20);

    return $this;
  }

  public function disableSvgFilters(): static
  {
    add_action('init', function () {
      remove_action('wp_body_open', 'gutenberg_global_styles_render_svg_filters');
      remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
    });

    return $this;
  }

  public function disableAssetUrlVersioning(): static
  {
    $callback = function (string $url) {
      // if (is_admin())
      //   return $url;

      if ($url)
        return esc_url(remove_query_arg('ver', $url));

      return $url;
    };

    add_filter('script_loader_src', $callback, 15, 1);
    add_filter('style_loader_src', $callback, 15, 1);

    return $this;
  }

  public function disableRoles($roles = ['author', 'contributor', 'subscriber']): static
  {
    add_action('init', function () use ($roles) {
      foreach ($roles as $role) {
        remove_role($role);
      }
    });

    return $this;
  }

  public function disableFontLibrary(): static
  {
    add_filter('block_editor_settings_all', function ($settings) {
      $settings['fontLibraryEnabled'] = false;
      return $settings;
    });

    return $this;
  }

  public function disableOpenVerse(): static
  {
    add_filter('block_editor_settings_all', function ($settings) {
      $settings['enableOpenverseMediaCategory'] = false;
      return $settings;
    });

    return $this;
  }

  public function replaceLoginLogoLink($url = null, $linkTitle = null): static
  {
    add_filter('login_headerurl', function () use ($url) {
      return $url ?? home_url();
    });

    add_filter('login_headertext', function () use ($linkTitle) {
      return $linkTitle ?? get_bloginfo('name');
    });

    return $this;
  }


  /**
   * By default, WordPress compresses uploaded JPEG images to reduce file size, using a quality of 90.
   * This method disables this compression, ensuring the original quality of the image is used. This is
   * particularly useful if you're applying your own compression at a later stage -- better not to stack
   * compression filters.
   */
  public function disableJpegCompression(): static
  {
    add_filter('jpeg_quality', function (): int {
      return 100;
    }, 10, 2);

    return $this;
  }

  public function disableDashboard(): static
  {
    add_action('admin_menu', function () {
      remove_menu_page('index.php');
    });

    return $this;
  }

  /**
   * By default, WordPress includes a set of default patterns in the block inserter, pulled from here: https://wordpress.org/patterns/
   * This method disables the display of these default patterns.
   */
  public function disableDefaultPatterns(): static
  {
    add_action('current_screen', function () {
      remove_theme_support('core-block-patterns');
    }, 20);

    return $this;
  }

  /**
   * By default, when you search for a block in the Gutenberg Block Inserter, recommendations for 
   * 3rd party block plugins come up, asking if you want to install them; it's annoying and creates
   * the possibility for plugin hell caused by non-developers; this method removes this feature. 
   */
  public function disableBlockPluginRecommendations(): static
  {
    remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
    return $this;
  }

  /**
   * Hides annoying wp-admin notices related to updating plugins, themes, and core
   */
  public function disableUpdateNotices(): static
  {
    // disable WP core, plugin, and theme update notices (because we manage these via Composer not wp-admin):
    $updateFilters = ['pre_site_transient_update_core', 'pre_site_transient_update_plugins', 'pre_site_transient_update_themes'];
    foreach ($updateFilters as $filter) {
      add_filter($filter, function () {
        global $wp_version;
        return (object) array('last_checked' => time(), 'version_checked' => $wp_version);
      });
    }

    remove_action('admin_notices', 'update_nag');

    return $this;
  }

  /**
   * There are many dashboard widgets that are annoying, rarely/never used, confusing for 
   * clients, and that add performance bloat via additional DB/external requests. This 
   * opinionated method removes them all.
   */
  public function disableDashboardWidgets(): static
  {
    add_action('admin_init', function () {
      remove_meta_box('dashboard_incoming_links', ['dashboard', 'dashboard-network'], 'normal'); // 'Incoming links'
      remove_meta_box('dashboard_plugins', ['dashboard', 'dashboard-network'], 'normal'); // 'Plugins'
      remove_meta_box('dashboard_primary', ['dashboard', 'dashboard-network'], 'normal'); // 'WordPress News'
      remove_meta_box('dashboard_secondary', ['dashboard', 'dashboard-network'], 'normal'); // 'Other WordPress News'
      remove_meta_box('dashboard_quick_press', ['dashboard', 'dashboard-network'], 'side'); // 'Quick Draft'
      remove_meta_box('dashboard_recent_drafts', ['dashboard', 'dashboard-network'], 'side'); // 'Recent Drafts'
      remove_meta_box('dashboard_recent_comments', ['dashboard', 'dashboard-network'], 'normal'); // 'Recent Comments'
      remove_meta_box('dashboard_right_now', ['dashboard', 'dashboard-network'], 'normal'); // 'At a Glance'
      remove_meta_box('dashboard_activity', ['dashboard', 'dashboard-network'], 'normal'); // 'Activity'
      remove_meta_box('dashboard_site_health', ['dashboard', 'dashboard-network'], 'normal'); // 'Site Health Status'
      remove_meta_box('wpseo-dashboard-overview', ['dashboard', 'dashboard-network'], 'normal'); // 'Yoast SEO metabox'
      remove_action('welcome_panel', 'wp_welcome_panel'); // 'Welcome to WordPress'
    });

    return $this;
  }

  public function enableFeaturedImages(): static
  {
    // We use a priority of 11 to load after the parent theme
    add_action('after_setup_theme', function () {
      add_theme_support('post-thumbnails');
    }, 11);

    return $this;
  }

  public function enableExcerpts(): static
  {
    // We use a priority of 11 to load after the parent theme
    add_action('after_setup_theme', function () {
      add_post_type_support('page', 'excerpt');
    }, 11);

    return $this;
  }

  public function disableToolsForEditors(): static
  {
    add_action('admin_menu', function () {
      if (!current_user_can('administrator')) {
        remove_menu_page('tools.php');
      }
    });

    return $this;
  }

  public function disableYoastForEditors(): static
  {
    add_action('admin_menu', function () {
      if (!current_user_can('administrator')) {
        remove_menu_page('wpseo_dashboard');
        remove_menu_page('wpseo_workouts');
      }
    });

    return $this;
  }

  public function disableYoastSitemap(): static
  {
    add_filter('wpseo_option_wpseo_defaults', function ($defaults) {
      $defaults['enable_xml_sitemap'] = false;
      return $defaults;
    }, 100, 1);

    return $this;
  }

  public function disableYoastBlocks(): static
  {
    add_filter('wpseo_enable_structured_data_blocks', '__return_false');
    return $this;
  }

  public function disableYoastSearchActionSchema(): static
  {
    add_filter('disable_wpseo_json_ld_search', '__return_true');
    return $this;
  }

  public function disableYoastBreadcrumbSchema(): static
  {
    add_filter(
      'wpseo_schema_graph_pieces',
      /**
       * Removes the breadcrumb graph pieces from the schema collector.
       *
       * @param array  $pieces  The current graph pieces.
       * @param string $context The current context.
       *
       * @return array The remaining graph pieces.
       */
      function ($pieces, $context) {
        return \array_filter($pieces, function ($piece) {
          return ! $piece instanceof \Yoast\WP\SEO\Generators\Schema\Breadcrumb;
        });
      },
      11,
      2
    );

    add_filter(
      'wpseo_schema_webpage',
      /**
       * Removes the breadcrumb property from the WebPage piece.
       *
       * @param array $data The WebPage's properties.
       *
       * @return array The modified WebPage properties.
       */
      function ($data) {
        if (array_key_exists('breadcrumb', $data)) {
          unset($data['breadcrumb']);
        }
        return $data;
      },
      11,
      1
    );

    return $this;
  }

  public function streamlineYoastInDevelopment(): static
  {
    if (WP_ENV == 'development') {
      add_filter('wpseo_option_wpseo_defaults', function ($defaults) {
        $defaults['content_analysis_active'] = false;
        $defaults['keyword_analysis_active'] = false;
        $defaults['inclusive_language_analysis_active'] = false;
        $defaults['enable_admin_bar_menu'] = false;
        $defaults['enable_text_link_counter'] = false;
        $defaults['enable_metabox_insights'] = false;
        $defaults['enable_enhanced_slack_sharing'] = false;
        $defaults['enable_index_now'] = false;
        $defaults['semrush_integration_active'] = false;
        $defaults['wincher_integration_active'] = false;
        $defaults['ignore_search_engines_discouraged_notice'] = true;
        return $defaults;
      }, 100, 1);
    }

    return $this;
  }

  public function disablePostsArchiveToolbarMenu(): static
  {
    add_action('wp_before_admin_bar_render', function () {
      global $wp_admin_bar;
      $wp_admin_bar->remove_menu('archive');
    });

    return $this;
  }

  public function disableWpTexturize(): static
  {
    add_action('after_setup_theme', function () {
      add_filter('run_wptexturize', '__return_false', 9999);
    }, 11);

    // although the above filter is enough, we remove these filters for good measure:
    $filters = ['the_content', 'the_title', 'the_excerpt', 'the_post_thumbnail_caption', 'single_post_title', 'single_cat_title', 'single_tag_title', 'single_month_title', 'nav_menu_attr_title', 'nav_menu_description', 'term_description', 'get_the_post_type_description', 'list_cats'];
    foreach ($filters as $filter) {
      remove_filter($filter, 'wptexturize');
    }

    return $this;
  }

  /**
   * By default, WordPress runs a pointlessly aggressive filter on the_content, the_title, wp_title, etc. to replace lowercase "p" with "P" for any instances of "Wordpress".
   * It's a dumb branding enforcement that needlessly impacts performance. This method disables these filters.
   */
  public function disableCapitalPDangit(): static
  {
    $filters = ['the_content', 'the_title', 'wp_title', 'document_title', 'widget_text_content'];
    foreach ($filters as $filter) {
      remove_filter($filter, 'capital_P_dangit', 11);
    }
    remove_filter('comment_text', 'capital_P_dangit', 31);

    return $this;
  }

  public function disableLazyLoading(): static
  {
    add_filter('wp_lazy_loading_enabled', '__return_false');
    return $this;
  }

  public function disableYoastToolbarMenu(): static
  {
    add_action('wp_before_admin_bar_render', function () {
      global $wp_admin_bar;
      $wp_admin_bar->remove_menu('wpseo-menu');
    });

    return $this;
  }


  /**
   * By default, Yoast SEO's metabox gets displayed above ACF Field Groups when editing a post (not ideal).
   * This method pushes it below any ACF Field Groups.
   */
  public function deprioritizeYoastMetabox(): static
  {
    add_action('wpseo_metabox_prio', function () {
      return 'low';
    });

    return $this;
  }

  public function enableSvgUploads(): static
  {
    add_filter('upload_mimes', function ($mimes) {
      $mimes['svg'] = 'image/svg+xml';
      return $mimes;
    });

    return $this;
  }

  /**
   * By default, SVGs are not allowed to be rendered alongside ACF fields in the admin for 
   * security purposes. This method ensures that SVGs are allowed (this was initially added to enable 
   * compatibility with the `VerticalAlignment` field from the CloakWP ACF Abstractions package,
   * and is considered useful enough to be a core method).
   */
  public function enableSVGsForACF(): static
  {
    add_filter('wp_kses_allowed_html', function ($tags, $context) {
      if ($context === 'acf') {
        $tags['svg'] = [
          'xmlns' => true,
          'fill' => true,
          'viewbox' => true,
          'role' => true,
          'aria-hidden' => true,
          'focusable' => true,
          'style' => true,
          'class' => true,
        ];

        $tags['path'] = [
          'd' => true,
          'fill' => true,
          'style' => true,
          'class' => true,
        ];

        $tags['g'] = [
          'transform' => true,
        ];

        $tags['rect'] = [
          'x' => true,
          'y' => true,
          'width' => true,
          'height' => true,
          'style' => true,
        ];
      }

      return $tags;
    }, 10, 2);

    return $this;
  }


  /**
   * This is required in order for WP Admin > Appearance > Menus page to be visible for new Block themes. 
   */
  public function enableLegacyMenuEditor(): static
  {
    add_action('init', function () {
      add_theme_support('menus');
    });
    return $this;
  }

  /**
   * Editor users by default can't edit Menus, but it's common for them to desire to. Unfortunately WordPress 
   * doesn't provide a way to allow users to edit menus without also granting them access to other theme-related 
   * things, including the ability to switch themes. This method allows Editors to edit Menus while hiding all 
   * other theme options under the `Appearance` menu.
   */
  public function enableMenusForEditors(): static
  {
    add_action('admin_head', function () {
      $role_object = get_role('editor');
      if (!$role_object->has_cap('edit_theme_options')) {
        $role_object->add_cap('edit_theme_options');
      }

      if (!is_admin() && current_user_can('editor')) {
        // for editors, hide all menu items enabled by the 'edit_theme_options' capability that isn't the "Menus" menu item:
        global $submenu;
        if (isset($submenu['themes.php'])) {
          foreach ($submenu['themes.php'] as $index => $menu_item) {
            if ($menu_item[2] !== 'nav-menus.php')
              unset($submenu['themes.php'][$index]);
          }
        }
      }
    });

    return $this;
  }

  /**
   * Add a "Xdebug Info" page under the "Tools" menu that prints useful Xdebug dev info. 
   * Only gets added for Admin users. 
   */
  public function enableXdebugInfoPage(): static
  {
    if (WP_ENV !== 'production') {
      add_action('admin_menu', function () {
        add_submenu_page(
          'tools.php',           // Parent page
          'Xdebug Info',         // Menu title
          'Xdebug Info',         // Page title
          'manage_options',      // user "role"
          'php-info-page',       // page slug
          function () {
            if (function_exists('xdebug_info')) {
              /** @disregard */
              xdebug_info();
            } else {
              echo '<h2>No Xdebug enabled</h2>';
            }
          }
        );
      });
    }

    return $this;
  }

  /**
   * Adds BrowserSync script to wp-admin <head> to enable live reloading upon saving theme files.
   * Only gets added in local development environments, and it's up to you to ensure that BrowserSync is 
   * actually running on http://localhost:3000.
   */
  public function enableBrowserSync(): static
  {
    if (WP_ENV == 'development') {
      add_action('admin_head', function () {
        echo '<script id="__bs_script__">//<![CDATA[
          (function() {
            try {
              console.log("adding BrowserSync script");
              var script = document.createElement("script");
              if ("async") {
                script.async = true;
              }
              script.src = "http://localhost:3000/browser-sync/browser-sync-client.js?v=2.29.3";
              if (document.body) {
                document.body.appendChild(script);
              } else if (document.head) {
                document.head.appendChild(script);
              }
            } catch (e) {
              console.error("Browsersync: could not append script tag", e);
            }
          })()
        //]]></script>';
      });
    }

    return $this;
  }
}
