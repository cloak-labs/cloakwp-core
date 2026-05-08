<?php

declare(strict_types=1);

namespace CloakWP\Core\Content;

use InvalidArgumentException;

class Taxonomy
{
  public string $slug = '';
  protected array $settings = [];
  protected array $labels = [];
  protected array $types = [];
  private bool $isConfigured = false;

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
      throw new InvalidArgumentException(static::class . ' must define a taxonomy slug.');
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

  public function forTypes(array $types): static
  {
    $this->types = $types;
    return $this;
  }

  public function getTypes(): array
  {
    return $this->types;
  }

  public function labels(array $labels): static
  {
    $this->labels = $labels;
    return $this;
  }

  public function showInRest(bool $showInRest): static
  {
    $this->settings['show_in_rest'] = $showInRest;
    return $this;
  }

  public function public(bool $isPublic): static
  {
    $this->settings['public'] = $isPublic;
    return $this;
  }

  public function hierarchical(bool $isHierarchical): static
  {
    $this->settings['hierarchical'] = $isHierarchical;
    return $this;
  }

  public function rewrite(array|bool $rewrite): static
  {
    $this->settings['rewrite'] = $rewrite;
    return $this;
  }

  public function withSettings(array $settings): static
  {
    $this->settings = array_merge($this->settings, $settings);
    return $this;
  }

  public function register(ContentModel|null $contentModel = null): static
  {
    $this->configureOnce();
    $contentModel ??= ContentModel::getInstance();
    $typeSlugs = $contentModel->resolveTypeReferences($this->types);

    add_action('init', function () use ($typeSlugs) {
      register_extended_taxonomy($this->slug, $typeSlugs, $this->settings, $this->labels);
    }, 4);

    return $this;
  }
}
