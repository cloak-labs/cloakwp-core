<?php

declare(strict_types=1);

namespace CloakWP\Core\Content;

use Extended\ACF\Location;
use InvalidArgumentException;

class ContentType
{
  public string $slug = '';
  protected array $settings = [];
  protected array $labels = [];
  protected array|null $fieldGroups = null;
  protected array|null $virtualFields = null;
  protected bool $singularPagesEnabled = false;
  private bool $isConfigured = false;

  /**
   * @var callable|null $afterChangeCallback
   */
  protected $afterChangeCallback;

  /**
   * @var callable|null $afterReadCallback
   */
  protected $afterReadCallback;

  /**
   * @var callable|null $filterValueCallback
   */
  protected $filterValueCallback;

  public function __construct(string|null $slug = null)
  {
    if ($slug !== null) {
      $this->slug = sanitize_key($slug);
      return;
    }

    if ($this->slug) {
      $this->slug = sanitize_key($this->slug);
    }
  }

  public static function make(string|null $slug = null): static
  {
    return (new static($slug))->configureOnce();
  }

  /**
   * Override this in child classes to define the content type's settings.
   */
  protected function configure(): void
  {
    //
  }

  public function configureOnce(): static
  {
    if ($this->isConfigured) {
      return $this;
    }

    if (!$this->slug) {
      throw new InvalidArgumentException(static::class . ' must define a content type slug.');
    }

    $this->configure();
    $this->isConfigured = true;

    return $this;
  }

  public function getSlug(): string
  {
    return $this->slug;
  }

  public static function slug(): string
  {
    return (new static())->getSlug();
  }

  /**
   * The URL to the icon to be used for this menu. Pass a base64-encoded SVG using a data URI, which will 
   * be colored to match the color scheme -- this should begin with 'data:image/svg+xml;base64,'. Pass the 
   * name of a Dashicons helper class to use a font icon, e.g.'dashicons-chart-pie'. Pass 'none' to leave 
   * div.wp-menu-image empty so an icon can be added via CSS. Defaults to use the posts icon.
   */
  public function menuIcon(string $dashiconName): static
  {
    $this->settings['menu_icon'] = $dashiconName;
    return $this;
  }

  /**
   * The position in the menu order the post type should appear. To work, $show_in_menu must be true. 
   * Default null (at the bottom).
   */
  public function menuPosition(int $menuPosition): static
  {
    $this->settings['menu_position'] = $menuPosition;
    return $this;
  }

  /**
   * The string to use to build the read, edit, and delete capabilities. May be passed as 
   * an array to allow for alternative plurals when using this argument as a base to 
   * construct the capabilities, e.g. array('story', 'stories'). Default 'post'.
   */
  public function capabilityType(string|array $capabilityType): static
  {
    $this->settings['capability_type'] = $capabilityType;
    return $this;
  }

  /**
   * Array of capabilities for this post type. $capability_type is used as a base to construct 
   * capabilities by default. See get_post_type_capabilities() --> https://developer.wordpress.org/reference/functions/get_post_type_capabilities/
   */
  public function capabilities(array $capabilities): static
  {
    $this->settings['capabilities'] = $capabilities;
    return $this;
  }

  /**
   * Whether to use the internal default meta capability handling. Default false.
   */
  public function mapMetaCap(bool $mapMetaCap): static
  {
    $this->settings['map_meta_cap'] = $mapMetaCap;
    return $this;
  }

  /**
   * Core feature(s) the post type supports. Serves as an alias for calling add_post_type_support() directly.
   * Core features include 'title', 'editor', 'comments', 'revisions', 'trackbacks', 'author', 'excerpt', 
   * 'page-attributes', 'thumbnail', 'custom-fields', and 'post-formats'. Additionally, the 'revisions' 
   * feature dictates whether the post type will store revisions, and the 'comments' feature dictates 
   * whether the comments count will show on the edit screen. A feature can also be specified as an 
   * array of arguments to provide additional information about supporting that feature.
   */
  public function supports(array $features): static
  {
    return $this->addFeature($features);
  }

  private function addFeature(array|string $features): static
  {
    if (!isset($this->settings['supports'])) {
      $this->settings['supports'] = [];
    }

    $addFeature = function (array|string $feature) {
      if (is_array($feature)) {
        array_push($this->settings['supports'], $feature);
      } elseif (!in_array($feature, $this->settings['supports'])) {
        $this->settings['supports'][] = $feature;
      }
    };

    if (is_array($features)) {
      foreach ($features as $key => $value) {
        if (is_array($value)) {
          // Recursively handle nested arrays
          $this->addFeature($value);
        } elseif (is_string($key) && ($value === true)) {

          // Handles a directly associative array like ['feature' => true]
          $addFeature($key);
        } else {
          if (is_string($key)) $addFeature([$key => $value]);
          else $addFeature($value);
        }
      }
    } else {
      $addFeature($features);
    }

    return $this;
  }

  /**
   * Provide a callback function that sets up the meta boxes for the edit form.
   */
  public function registerMetaBoxCallback(callable $callback): static
  {
    $this->settings['register_meta_box_cb'] = $callback;
    return $this;
  }

  public function hasArchive(bool $hasArchive): static
  {
    $this->settings['has_archive'] = $hasArchive;
    return $this;
  }

  /**
   * Customize the archive page behaviour.
   */
  public function archive(array $archive): static
  {
    $this->settings['archive'] = $archive;
    return $this;
  }

  /**
   * An array of taxonomy identifiers that will be registered for the post type.
   */
  public function taxonomies(array $taxonomies): static
  {
    $this->settings['taxonomies'] = $taxonomies;
    return $this;
  }

  /**
   * Sets the query_var key for this post type. Defaults to $post_type key.
   */
  public function queryVar(string|bool $queryVar): static
  {
    $this->settings['query_var'] = $queryVar;
    return $this;
  }

  /**
   * Whether to allow this post type to be exported. Default true.
   */
  public function canExport(bool $canExport): static
  {
    $this->settings['can_export'] = $canExport;
    return $this;
  }

  /**
   * Whether to delete posts of this type when deleting a user.
   */
  public function deleteWithUser(bool $deleteWithUser): static
  {
    $this->settings['delete_with_user'] = $deleteWithUser;
    return $this;
  }

  /**
   * Array of blocks to use as the default initial state for a Gutenberg editor session.
   */
  public function template(array $blocks): static
  {
    $this->settings['template'] = $blocks;
    return $this;
  }

  /**
   * Whether the block template should be locked if $template is set.
   */
  public function templateLock(string|false $templateLock): static
  {
    $this->settings['template_lock'] = $templateLock;
    return $this;
  }

  /**
   * Whether a content type is intended for use publicly either via the admin interface or by 
   * front-end users.
   */
  public function public(bool $isPublic): static
  {
    $this->settings['public'] = $isPublic;
    return $this;
  }

  /**
   * Whether to exclude posts with this content type from front end search results.
   */
  public function excludeFromSearch(bool $excludeFromSearch): static
  {
    $this->settings['exclude_from_search'] = $excludeFromSearch;
    return $this;
  }

  /**
   * Whether queries can be performed on the front end for the content type as part of parse_request().
   */
  public function publiclyQueryable(bool $isPubliclyQueryable): static
  {
    $this->settings['publicly_queryable'] = $isPubliclyQueryable;
    return $this;
  }

  /**
   * Whether the post type is hierarchical. Default false.
   */
  public function hierarchical(bool $isHierarchical): static
  {
    $this->settings['hierarchical'] = $isHierarchical;
    return $this;
  }

  /**
   * A short descriptive summary of what the content type is.
   */
  public function description(string $description): static
  {
    $this->settings['description'] = $description;
    return $this;
  }

  /**
   * Whether to expose this content type to the REST API.
   */
  public function showInRest(bool $showInRest = true): static
  {
    $this->settings['show_in_rest'] = $showInRest;
    return $this;
  }

  /**
   * Whether this content type has corresponding frontend pages for individual documents.
   */
  public function singularPages(bool $enabled = true): static
  {
    $this->singularPagesEnabled = $enabled;

    if ($enabled) {
      return $this
        ->public(true)
        ->showInRest(true);
    }

    return $this
      ->public(false)
      ->showUi(true)
      ->showInRest(true);
  }

  public function hasSingularPages(): bool
  {
    return $this->singularPagesEnabled;
  }

  /**
   * Whether to add the post type to the site's main RSS feed.
   */
  public function showInFeed(bool $showInFeed): static
  {
    $this->settings['show_in_feed'] = $showInFeed;
    return $this;
  }

  /**
   * Where to show the post type in the admin menu.
   */
  public function showInMenu(bool|string $showInMenu): static
  {
    $this->settings['show_in_menu'] = $showInMenu;
    return $this;
  }

  /**
   * Makes this post type available for selection in navigation menus.
   */
  public function showInNavMenus(bool $showInNavMenus): static
  {
    $this->settings['show_in_nav_menus'] = $showInNavMenus;
    return $this;
  }

  /**
   * Makes this post type available via the admin bar.
   */
  public function showInAdminBar(bool $showInAdminBar): static
  {
    $this->settings['show_in_admin_bar'] = $showInAdminBar;
    return $this;
  }

  /**
   * Whether to generate and allow a UI for managing this post type in the admin.
   */
  public function showUi(bool $showUi = true): static
  {
    $this->settings['show_ui'] = $showUi;
    return $this;
  }

  /**
   * To change the base URL of REST API route.
   */
  public function restBase(string $restBase): static
  {
    $this->settings['rest_base'] = $restBase;
    return $this;
  }

  /**
   * To change the namespace URL of REST API route.
   */
  public function restNamespace(string $restNamespace): static
  {
    $this->settings['rest_namespace'] = $restNamespace;
    return $this;
  }

  /**
   * Customize the REST API controller class name.
   */
  public function restControllerClass(string $restControllerClass): static
  {
    $this->settings['rest_controller_class'] = $restControllerClass;
    return $this;
  }

  /**
   * Use the blockEditor method to forcefully enable or disable the block editor for post type.
   */
  public function blockEditor(bool $hasBlockEditor = true): static
  {
    $this->settings['block_editor'] = $hasBlockEditor;
    return $this->addFeature(['editor' => $hasBlockEditor]);
  }

  /**
   * Enable the classic editor for this content type.
   */
  public function classicEditor(bool $useClassicEditor = true): static
  {
    if ($useClassicEditor) $this->settings['block_editor'] = false;
    return $this->addFeature(['editor' => $useClassicEditor]);
  }

  /**
   * Override the "Enter title here" placeholder text When creating/editing a post of this type.
   */
  public function titlePlaceholder(string $title): static
  {
    $this->settings['enter_title_here'] = $title;
    return $this;
  }

  /**
   * Override the "Featured Image" label when selecting a featured image for a post of this type.
   */
  public function featuredImageLabel(string $label): static
  {
    $this->settings['featured_image'] = $label;
    return $this;
  }

  /**
   * Define a custom permalink structure for posts of this type.
   */
  public function rewrite(array $permastructs): static
  {
    $this->settings['rewrite'] = $permastructs;
    return $this;
  }

  /**
   * Add some custom columns to the Post Type admin listing page.
   */
  public function adminCols(array $adminCols): static
  {
    $this->settings['admin_cols'] = $adminCols;
    return $this;
  }

  /**
   * Add a dropdown filter to the Post Type admin listing page.
   */
  public function adminFilters(array $adminCols): static
  {
    $this->settings['admin_cols'] = $adminCols;
    return $this;
  }

  /**
   * Quick Edit functionality is enabled for all post types by default.
   */
  public function quickEdit(bool $enableQuickEdit): static
  {
    $this->settings['quick_edit'] = $enableQuickEdit;
    return $this;
  }

  /**
   * An entry is added to the "At a Glance" dashboard widget for your post type by default.
   */
  public function dashboardGlance(bool $enableDashboardGlance): static
  {
    $this->settings['dashboard_glance'] = $enableDashboardGlance;
    return $this;
  }

  /**
   * Include this post type in the "Recently Published" section of the dashboard activity widget.
   */
  public function dashboardActivity(bool $enableDashboardActivity): static
  {
    $this->settings['dashboard_activity'] = $enableDashboardActivity;
    return $this;
  }

  /**
   * A catch-all method allowing you to specify settings made available by Extended CPTs
   * and the default register_post_type function.
   */
  public function withSettings(array $settings): static
  {
    $this->settings = array_merge($this->settings, $settings);
    return $this;
  }

  /**
   * Content Type labels are auto-generated based on the content type slug, but you can customize these labels.
   */
  public function labels(array $labels): static
  {
    $this->labels = $labels;
    return $this;
  }

  /**
   * Provide an array of CloakWP `FieldGroup` class instances to attach groups of ACF Fields to this content type.
   */
  public function fieldGroups(array $fieldGroups): static
  {
    $this->fieldGroups = $fieldGroups;
    return $this;
  }

  /**
   * Append one CloakWP `FieldGroup` class instance to this content type.
   */
  public function addFieldGroup(object $fieldGroup): static
  {
    return $this->addFieldGroups([$fieldGroup]);
  }

  /**
   * Append CloakWP `FieldGroup` class instances without replacing existing groups.
   */
  public function addFieldGroups(array $fieldGroups): static
  {
    $this->fieldGroups = [
      ...$this->getFieldGroups(),
      ...$fieldGroups,
    ];

    return $this;
  }

  public function getFieldGroups(): array
  {
    return $this->fieldGroups ?? [];
  }

  /**
   * Run some code before a post of this type is saved.
   */
  public function afterChange(callable|null $callback): static
  {
    $this->afterChangeCallback = $callback;
    return $this;
  }

  /**
   * Run some code after a post of this type is fetched from the database.
   */
  public function afterRead(callable $callback): static
  {
    $this->afterReadCallback = $callback;
    return $this;
  }

  /**
   * Attach some extra "virtual" fields to all post response objects for this content type.
   */
  public function virtualFields(array $fields): static
  {
    $this->virtualFields = $fields;
    return $this;
  }

  /**
   * Customize the REST API response for posts of this type.
   */
  public function value(callable $filterCallback): static
  {
    $this->filterValueCallback = $filterCallback;
    return $this;
  }

  /**
   * Finally, register the Content Type and, if necessary, its ACF Field Groups.
   */
  public function register(): static
  {
    $this->configureOnce();

    add_action('init', function () {
      register_extended_post_type($this->slug, $this->settings, $this->labels);
    }, 3);

    if ($this->fieldGroups) {
      foreach ($this->fieldGroups as $fieldGroup) {
        $fieldGroup
          ->location([
            Location::where('post_type', '==', $this->slug)
          ])
          ->register();
      }
    }

    if (is_callable($this->afterChangeCallback)) {
      $callback = $this->afterChangeCallback;
      add_action("save_post_$this->slug", function ($post_id, $post, $update) use ($callback) {
        if (wp_is_post_autosave($post_id)) {
          return;
        }

        if (!$update) {
          return;
        }

        $callback($post_id, $post, $update);
      }, 10, 3);
    }

    if ($this->virtualFields) {
      register_virtual_fields($this->slug, $this->virtualFields);
    }

    if ($this->filterValueCallback) {
      $callback = $this->filterValueCallback;
      add_filter("rest_prepare_$this->slug", function ($response, $post, $context) use ($callback) {
        if (is_wp_error($response)) {
          return $response;
        }

        return $callback($response, $post, $context);
      }, 50, 3);
    }

    return $this;
  }
}
