<?php

namespace mindplay\vite;

use RuntimeException;

/**
 * This class represents Vite's manifest of published files.
 */
class Manifest
{
    /**
     * @var array<string,Chunk> map where chunk names => `Chunk` instances.
     */
    private array $chunks;

    /**
     * @var array<string,array{type:string,as:string}> map where file extensions => preloaded MIME and content types.
     */
    private array $preload_types = [];

    public function __construct(
        /**
         * Indicates whether the application is running in development mode.
         *
         * In production mode, the `manifest.json` file will be read to generate
         * preload links for all dependencies, and CSS and JS tags for all entries.
         *
         * In development mode, Vite will dynamically inject CSS and JS tags.
         */
        private bool $dev,

        /**
         * Absolute path to the `manifest.json` file.
         *
         * This is only used and required in production mode.
         */
        private string $manifest_path,

        /**
         * Public base path from which Vite's published assets are served.
         *
         * For example `/dist/` if your assets are served from `http://example.com/dist/`.
         *
         * Should match the `base` option in your Vite configuration, but could also point
         * to a CDN or other asset server, if you are serving assets from a different domain.
         */
        private string $base_path,
    ) {
        if ($this->dev) {
            // In development mode, we don't need the `manifest.json` file:

            $this->chunks = [];
        } else {
            // In production, read Vite's `manifest.json` file:

            if (!is_readable($this->manifest_path)) {
                throw new RuntimeException(
                    file_exists($this->manifest_path)
                    ? "Manifest file is not readable: {$this->manifest_path}"
                    : "Manifest file not found: {$this->manifest_path}"
                );
            }

            $this->chunks = array_map(
                fn(array $chunk) => Chunk::create($chunk),
                json_decode(file_get_contents($this->manifest_path), true)
            );
        }
    }

    /**
     * Register a MIME type for preloading assets with a specific file extension.
     *
     * @param string $ext the file extension (without the leading dot)
     * @param string $mime_type the MIME type to preload
     * @param string $preload_as the `as` attribute value (content type) for the preload tag
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/rel/preload#what_types_of_content_can_be_preloaded
     */
    public function preload(string $ext, string $mime_type, string $preload_as): void
    {
        $this->preload_types[$ext] = ['type' => $mime_type, 'as' => $preload_as];
    }

    /**
     * Register MIME types for preloading all common web image formats.
     */
    public function preloadImages(): void
    {
        $this->preload_types = [
            ...$this->preload_types,
            'apng' => ['type' => 'image/apng', 'as' => 'image'],
            'avif' => ['type' => 'image/avif', 'as' => 'image'],
            'bmp' => ['type' => 'image/bmp', 'as' => 'image'],
            'cur' => ['type' => 'image/x-icon', 'as' => 'image'],
            'gif' => ['type' => 'image/gif', 'as' => 'image'],
            'ico' => ['type' => 'image/x-icon', 'as' => 'image'],
            'jpeg' => ['type' => 'image/jpeg', 'as' => 'image'],
            'jpg' => ['type' => 'image/jpeg', 'as' => 'image'],
            'png' => ['type' => 'image/png', 'as' => 'image'],
            'svg' => ['type' => 'image/svg+xml', 'as' => 'image'],
            'tif' => ['type' => 'image/tiff', 'as' => 'image'],
            'tiff' => ['type' => 'image/tiff', 'as' => 'image'],
            'webp' => ['type' => 'image/webp', 'as' => 'image'],
        ];
    }

    /**
     * Register MIME types for preloading common web font formats.
     */
    public function preloadFonts(): void
    {
        $this->preload_types = [
            ...$this->preload_types,
            'ttf' => ['type' => 'font/ttf', 'as' => 'font'],
            'otf' => ['type' => 'font/otf', 'as' => 'font'],
            'woff' => ['type' => 'font/woff', 'as' => 'font'],
            'woff2' => ['type' => 'font/woff2', 'as' => 'font'],
        ];
    }

    /**
     * Create preload, CSS and JS tags for the specified entry point script(s).
     *
     * Entry points are defined in Vite's `build.rollupOptions` using RollUp's `input` setting.
     *
     * The expected typical usage in an HTML template is as follows:
     *
     * ```html
     * <!DOCTYPE html>
     * <html>
     *   <head>
     *     <title>My App</title>
     *     <?= $tags->preload ?>
     *     <?= $tags->css ?>
     *   </head>
     *   <body>
     *     <h1>My App</h1>
     *     <?= $tags->js ?>
     *   </body>
     * </html>
     * ```
     *
     * @link https://vitejs.dev/config/build-options#build-rollupoptions
     * @link https://rollupjs.org/configuration-options/#input
     */
    public function createTags(string ...$entries): Tags
    {
        if ($this->dev) {
            // In development mode, Vite will dynamically inject CSS and JS tags:

            $js = ["<script type=\"module\" src=\"{$this->base_path}@vite/client\"></script>"];

            foreach ($entries as $entry) {
                $js[] = "<script type=\"module\" src=\"{$this->base_path}{$entry}\"></script>";
            }

            return new Tags(js: implode("\n", $js));
        } else {
            // In production mode, we generate CSS/JS and preload tags for all entries and their dependencies:

            $chunks = $this->findImportedChunks($entries);

            return new Tags(
                preload: $this->createPreloadTags($chunks),
                css: $this->createStyleTags($chunks),
                js: $this->createScriptTags($chunks)
            );
        }
    }

    /**
     * Get the URL for an asset published by Vite.
     *
     * You can use this method to get the URL for an asset, for example, if you need
     * to create custom preload tags with media queries, or if you need to load an
     * asset dynamically, based on user interaction, and so on.
     */
    public function getURL(string $entry): string
    {
        if ($this->dev) {
            return $this->base_path . $entry;
        }

        $chunk = $this->chunks[$entry] ?? null;

        if ($chunk === null) {
            throw new RuntimeException("Entry not found in manifest: {$entry}");
        }

        return $this->base_path . $chunk->file;
    }

    /**
     * @param Chunk[] $chunks
     */
    private function createPreloadTags(array $chunks): string
    {
        $tags = [];

        foreach ($chunks as $chunk) {
            // Preload module:

            if (str_ends_with($chunk->file, '.js')) {
                $tags[] = "<link rel=\"modulepreload\" href=\"{$this->base_path}{$chunk->file}\" />";
            }

            // Preload assets:

            foreach ($chunk->assets as $asset) {
                $type = substr($asset, strrpos($asset, '.') + 1);

                if (isset($this->preload_types[$type])) {
                    $preload = $this->preload_types[$type];
                    $type = $preload['type'];
                    $as = $preload['as'];

                    $tags[] = "<link rel=\"preload\" as=\"{$as}\" type=\"{$type}\" href=\"{$this->base_path}{$asset}\" />";
                }
            }
        }

        return implode("\n", $tags);
    }

    /**
     * @param Chunk[] $chunks
     */
    private function createStyleTags(array $chunks): string
    {
        $tags = [];

        foreach ($chunks as $chunk) {
            if ($chunk->isEntry && str_ends_with($chunk->file, '.css')) {
                $tags[] = "<link rel=\"stylesheet\" href=\"{$this->base_path}{$chunk->file}\" />";
                continue;
            }

            foreach ($chunk->css as $css) {
                $tags[] = "<link rel=\"stylesheet\" href=\"{$this->base_path}{$css}\" />";
            }
        }

        return implode("\n", $tags);
    }

    /**
     * @param Chunk[] $chunks
     */
    private function createScriptTags(array $chunks): string
    {
        $tags = [];

        foreach ($chunks as $chunk) {
            if ($chunk->isEntry && str_ends_with($chunk->file, '.js')) {
                $tags[] = "<script type=\"module\" src=\"{$this->base_path}{$chunk->file}\"></script>";
            }
        }

        return implode("\n", $tags);
    }

    private function findImportedChunks(array $entries): array
    {
        $chunks = [];

        foreach ($entries as $entry) {
            $chunk = $this->chunks[$entry] ?? null;

            if ($chunk === null) {
                throw new RuntimeException("Entry not found in manifest: {$entry}");
            }

            if (! $chunk->isEntry) {
                throw new RuntimeException("Chunk is not an entry point: {$entry}");
            }

            $chunks[$entry] = $chunk;

            // Recursively find all statically imported chunks:

            $imports = $chunk->imports;

            while ($imports) {
                $import = array_shift($imports);

                if (!isset($chunks[$import])) {
                    $chunks[$import] = $this->chunks[$import];

                    $imports = [
                        ...$imports,
                        ...$chunks[$import]->imports
                    ];
                }
            }
        }

        return $chunks;
    }
}
