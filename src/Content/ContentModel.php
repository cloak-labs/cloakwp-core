<?php

declare(strict_types=1);

namespace CloakWP\Core\Content;

use InvalidArgumentException;

class ContentModel
{
  /** @var array<int, self> */
  private static array $instances = [];

  protected array $types = [];
  protected array $typesBySlug = [];
  protected array $taxonomies = [];
  protected array $taxonomiesBySlug = [];
  protected array $typeExtensions = [];
  protected bool $registeredWithWordPress = false;

  /**
   * Return the shared content model registry instance.
   */
  public static function getInstance(): self
  {
    $siteId = function_exists('get_current_blog_id')
      ? (int) get_current_blog_id()
      : 1;

    return self::forSite($siteId);
  }

  public static function forSite(int $siteId): self
  {
    if ($siteId < 1) {
      throw new InvalidArgumentException('A positive WordPress site ID is required.');
    }

    return self::$instances[$siteId] ??= new self();
  }

  /**
   * @internal Reset registry state between tests or long-running jobs.
   */
  public static function resetInstances(?int $siteId = null): void
  {
    if ($siteId === null) {
      self::$instances = [];
      return;
    }

    unset(self::$instances[$siteId]);
  }

  /**
   * Register content type classes or instances with the model.
   *
   * Accepts ContentType class names, ContentType instances, or compatible objects
   * that expose a register method. Types are keyed by class and slug so callers
   * can fetch them either way.
   */
  public function registerTypes(array $types): static
  {
    foreach ($types as $type) {
      $type = $this->normalizeType($type);
      $slug = $this->getTypeSlug($type);
      $class = get_class($type);

      if (isset($this->typesBySlug[$slug]) && get_class($this->typesBySlug[$slug]) !== $class) {
        throw new InvalidArgumentException("A content type with the slug \"$slug\" is already registered.");
      }

      if (isset($this->types[$class])) {
        continue;
      }

      $this->types[$class] = $type;
      $this->typesBySlug[$slug] = $type;

      $this->applyTypeExtensions($type);

      if ($this->registeredWithWordPress) {
        $type->register();
      }
    }

    return $this;
  }

  /**
   * Register a callback that can modify content types before WordPress registration.
   *
   * The callback receives the content type instance and this ContentModel. It is
   * applied immediately to existing registered types and automatically to any
   * types registered later. Extensions must be registered before the content
   * model is booted so WordPress and ACF receive the final type definitions.
   */
  public function extendTypes(callable $callback): static
  {
    if ($this->registeredWithWordPress) {
      throw new InvalidArgumentException('Content type extensions must be registered before the content model is registered with WordPress.');
    }

    $this->typeExtensions[] = $callback;

    foreach ($this->types as $type) {
      $callback($type, $this);
    }

    return $this;
  }

  /**
   * Register taxonomy classes or instances with the model.
   *
   * Taxonomies are keyed by class and slug, and are registered with WordPress
   * immediately if the model has already been booted.
   */
  public function registerTaxonomies(array $taxonomies): static
  {
    foreach ($taxonomies as $taxonomy) {
      $taxonomy = $this->normalizeTaxonomy($taxonomy);
      $slug = $taxonomy->getSlug();
      $class = get_class($taxonomy);

      if (isset($this->taxonomiesBySlug[$slug]) && get_class($this->taxonomiesBySlug[$slug]) !== $class) {
        throw new InvalidArgumentException("A taxonomy with the slug \"$slug\" is already registered.");
      }

      if (isset($this->taxonomies[$class])) {
        continue;
      }

      $this->taxonomies[$class] = $taxonomy;
      $this->taxonomiesBySlug[$slug] = $taxonomy;

      if ($this->registeredWithWordPress) {
        $taxonomy->register($this);
      }
    }

    return $this;
  }

  /**
   * Register all known content types and taxonomies with WordPress.
   *
   * This is idempotent; subsequent calls do nothing unless new types or
   * taxonomies are registered afterward, in which case those are registered
   * immediately by registerTypes/registerTaxonomies.
   */
  public function registerWithWordPress(): static
  {
    if ($this->registeredWithWordPress) {
      return $this;
    }

    foreach ($this->types as $type) {
      $type->register();
    }

    foreach ($this->taxonomies as $taxonomy) {
      $taxonomy->register($this);
    }

    $this->registeredWithWordPress = true;

    return $this;
  }

  /**
   * Boot the content model into WordPress.
   *
   * Alias for registerWithWordPress to make theme startup code read naturally.
   */
  public function boot(): static
  {
    return $this->registerWithWordPress();
  }

  /**
   * Return all registered content type instances.
   */
  public function getTypes(): array
  {
    return array_values($this->types);
  }

  /**
   * Fetch a registered content type by class name or slug.
   */
  public function getType(string $classOrSlug): object|null
  {
    if (isset($this->types[$classOrSlug])) {
      return $this->types[$classOrSlug];
    }

    return $this->typesBySlug[$classOrSlug] ?? null;
  }

  /**
   * Determine whether a content type class name or slug is registered.
   */
  public function hasType(string $classOrSlug): bool
  {
    return $this->getType($classOrSlug) !== null;
  }

  /**
   * Return content types configured to have corresponding frontend pages.
   */
  public function getTypesWithPages(): array
  {
    return array_values(array_filter(
      $this->types,
      fn($type) => method_exists($type, 'hasSingularPages') && $type->hasSingularPages()
    ));
  }

  /**
   * Return all registered taxonomy instances.
   */
  public function getTaxonomies(): array
  {
    return array_values($this->taxonomies);
  }

  /**
   * Fetch a registered taxonomy by class name or slug.
   */
  public function getTaxonomy(string $classOrSlug): Taxonomy|null
  {
    if (isset($this->taxonomies[$classOrSlug])) {
      return $this->taxonomies[$classOrSlug];
    }

    return $this->taxonomiesBySlug[$classOrSlug] ?? null;
  }

  /**
   * Determine whether a taxonomy class name or slug is registered.
   */
  public function hasTaxonomy(string $classOrSlug): bool
  {
    return $this->getTaxonomy($classOrSlug) !== null;
  }

  /**
   * Return taxonomies associated with a registered content type.
   *
   * The content type can be referenced by class name or slug. Taxonomy type
   * dependencies are normalized to slugs before comparison.
   */
  public function getTaxonomiesForType(string $classOrSlug): array
  {
    $typeSlug = $this->resolveTypeReference($classOrSlug);

    return array_values(array_filter($this->taxonomies, function (Taxonomy $taxonomy) use ($typeSlug) {
      return in_array($typeSlug, $this->resolveTypeReferences($taxonomy->getTypes()), true);
    }));
  }

  /**
   * Convert a list of content type references into content type slugs.
   */
  public function resolveTypeReferences(array $types): array
  {
    return array_map(fn($type) => $this->resolveTypeReference($type), $types);
  }

  /**
   * Resolve a content type reference to its slug.
   *
   * Accepts a content type object, a registered class name, a registered slug,
   * or a raw slug. Throws when a class exists but has not been registered,
   * because that usually means a dependency was omitted from the content model.
   */
  public function resolveTypeReference(string|object $type): string
  {
    if (is_object($type)) {
      return $this->getTypeSlug($type);
    }

    $registeredType = $this->getType($type);
    if ($registeredType) {
      return $this->getTypeSlug($registeredType);
    }

    if (class_exists($type)) {
      throw new InvalidArgumentException("The content type dependency \"$type\" has not been registered.");
    }

    return $type;
  }

  /**
   * Convert a content type class name or object into a configured instance.
   */
  protected function normalizeType(string|object $type): object
  {
    if (is_string($type)) {
      if (!class_exists($type)) {
        throw new InvalidArgumentException("Content type class \"$type\" does not exist.");
      }

      $type = new $type();
    }

    if ($type instanceof ContentType) {
      return $type->configureOnce();
    }

    if (is_object($type) && method_exists($type, 'register')) {
      return $type;
    }

    throw new InvalidArgumentException('Content types must be class names or objects with a register method.');
  }

  /**
   * Apply all registered type extension callbacks to a content type.
   */
  protected function applyTypeExtensions(object $type): void
  {
    foreach ($this->typeExtensions as $callback) {
      $callback($type, $this);
    }
  }

  /**
   * Convert a taxonomy class name or object into a configured Taxonomy instance.
   */
  protected function normalizeTaxonomy(string|object $taxonomy): Taxonomy
  {
    if (is_string($taxonomy)) {
      if (!class_exists($taxonomy)) {
        throw new InvalidArgumentException("Taxonomy class \"$taxonomy\" does not exist.");
      }

      $taxonomy = new $taxonomy();
    }

    if (!$taxonomy instanceof Taxonomy) {
      throw new InvalidArgumentException('Taxonomies must extend ' . Taxonomy::class . '.');
    }

    return $taxonomy->configureOnce();
  }

  /**
   * Read a content type slug from the supported ContentType-style APIs.
   */
  protected function getTypeSlug(object $type): string
  {
    if (method_exists($type, 'getSlug')) {
      return $type->getSlug();
    }

    if (isset($type->slug)) {
      return $type->slug;
    }

    throw new InvalidArgumentException(get_class($type) . ' must expose a content type slug.');
  }
}
