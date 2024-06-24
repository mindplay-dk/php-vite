# PHP-Vite

[![PHP Version](https://img.shields.io/badge/php-8.1%2B-blue.svg)](https://packagist.org/packages/mindplay/php-vite)
[![Build Status](https://github.com/mindplay-dk/php-vite/actions/workflows/ci.yml/badge.svg)](https://github.com/mindplay-dk/php-vite/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MS--RL-green)](https://opensource.org/license/ms-rl-html)

This library provides a lightweight [backend integration](https://vitejs.dev/guide/backend-integration.html)
for your PHP-based MPA, SPA, or PWA based on [Vite](https://vitejs.dev/).

It parses the [build manifest](https://vitejs.dev/config/build-options#build-manifest) (the `.vite/manifest.json` file)
and produces the required `<script>` and `<link>` tags to load (and preload) scripts, CSS files, and other assets.

## Basic Usage

A commented MPA example is available [here](https://github.com/mindplay-dk/php-vite-mpa) -
please refer to this for examples of configuring Vite, NPM, TypeScript, and Composer.

In the following steps, we'll cover usage of the library API only.

#### 1. Load the `manifest.json` file created by Vite:

```php
$vite = new Manifest(
    manifest_path: $your_root_dir . '/public/dist/.vite/manifest.json',
    base_path: '/dist/',
    dev: false
);
```

The `manifest_path` points to the Vite `manifest.json` file created for the production build.

In this example, `dev` is `false`, so we'll be creating tags for the production assets.

The `base_path` is relative to your public web root - it is the root folder from which Vite's production assets are served, and/or the root folder from which Vite serves assets dynamically in development mode.

Note that, in development mode (when `dev` is set to `true`) the `manifest.json` file is unused, and not required.

> 💡 *For a detailed description of the constructor arguments, please refer to the `Manifest` constructor argument doc-blocks.*

#### 2. Create the `Tags` for an entry point script:

```php
$tags = $vite->createTags("index.ts");
```

Your entry point scripts are defined in Vite's [`build.rollupOptions`](https://vitejs.dev/config/build-options#build-rollupoptions) using RollUp's [`input`](https://rollupjs.org/configuration-options/#input) setting.

Note that, if you have **multiple entry point scripts** on **the same page**, you should pass them in a *single* call - for example:

```php
$tags = $vite->createTags("index.ts", "consent-banner.ts");
```

Making multiple calls for different entry points *may* result in duplicate tags for any shared static imports - you will most likely need just *one* instance of `Tags` on a single page.

#### 3. Emit from `Tags` in your HTML template:

Your `Tags` instance contains the preload and CSS tags, which should be emitted in
your `<head>` tag, as well as the `js` tags, which should be emitted immediately before
the `</body>` end tag.

For example:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Vite App</title>
    <link rel="icon" href="<?= $vite->getURL("php.svg") ?>" />
    <?= $tags->preload ?>
    <?= $tags->css ?>
</head>
<body>
    <div id="app"></div>
    <?= $tags->js ?>
</body>
</html>
```

## Preloading Assets

The service preloads any statically imported scripts and CSS files by default.

In addition, you can configure it to preload other statically imported assets as well -
for convenience, there are two methods to automatically configure preloading of all
common image and font asset types:

```php
$manifest->preloadImages();
$manifest->preloadFonts();
```

You can also configure it to preload any other asset types - for example, to configure
preloading of `.json` assets, you could add the following:

```php
$manifest->preload(
    ext: "json",
    mime_type: "application/json",
    preload_as: "fetch"
);
```

Then create your tags as covered in the documentation above.

## Creating URLs

For advanced use cases, you can also directly get the URL for an asset published by Vite:

```php
$my_url = $manifest->getURL("consent-banner.ts");
```

You can use this feature to, for example:

* Create your own custom preload tags (e.g. with media queries)
* Conditionally load a script based on user interactions or user state, etc.
