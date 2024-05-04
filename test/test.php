<?php

use mindplay\vite\Manifest;

use function mindplay\testies\{ configure, eq, run, test };

require __DIR__ . '/../vendor/autoload.php';

configure()->enableCodeCoverage(__DIR__ . '/coverage.xml', dirname(__DIR__) . '/src');

test(
    "can create tags in dev mode",
    function () {
        $vite = new Manifest(
            dev: true,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/'
        );

        $tags = $vite->createTags("main.js");

        eq(
            $tags->preload,
            "",
            "dev mode: nothing is preloaded"
        );

        eq(
            $tags->css,
            "",
            "dev mode: CSS is dynamically injected by Vite"
        );

        eq(
            $tags->js,
            implode("\n", [
                // Vite client script handles loading of CSS and JS with HMR support:
                '<script type="module" src="/dist/@vite/client"></script>',
                // Main entry point script is dynamically built by Vite:
                '<script type="module" src="/dist/main.js"></script>',
            ])
        );
    }
);

test(
    "can create tags in production mode",
    function () {
        $vite = new Manifest(
            dev: false,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/'
        );

        $vite->preloadImages();

        $tags = $vite->createTags("main.js");

        eq(
            explode("\n", $tags->preload),
            [
                // Preload main entry point script:
                '<link rel="modulepreload" href="/dist/assets/main.4889e940.js" />',
                // Preload images imported by main entry point script:
                '<link rel="preload" as="image" type="image/png" href="/dist/assets/asset.0ab0f9cd.png" />',
                // Preload static imports:
                '<link rel="modulepreload" href="/dist/assets/shared.83069a53.js" />',
            ]
        );

        eq(
            explode("\n", $tags->css),
            [
                // CSS imported by main entry point script:
                '<link rel="stylesheet" href="/dist/assets/main.b82dbe22.css" />',
                // CSS imported by static imports:
                '<link rel="stylesheet" href="/dist/assets/shared.a834bfc3.css" />',
            ]
        );

        eq(
            explode("\n", $tags->js),
            [
                // Main entry point script:
                '<script type="module" src="/dist/assets/main.4889e940.js"></script>',
            ]
        );
    }
);

test(
    "can create tags for multiple entry points in production mode",
    function () {
        $vite = new Manifest(
            dev: false,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/'
        );

        $vite->preloadImages();

        $tags = $vite->createTags("main.js", "views/foo.js");

        eq(
            explode("\n", $tags->preload),
            [
                // Preload for main entry point script:
                '<link rel="modulepreload" href="/dist/assets/main.4889e940.js" />',
                '<link rel="preload" as="image" type="image/png" href="/dist/assets/asset.0ab0f9cd.png" />',
                // Preload module shared by both entry points, no duplicates:
                '<link rel="modulepreload" href="/dist/assets/shared.83069a53.js" />',
                // Preload `views/foo.js` entry point script:
                '<link rel="modulepreload" href="/dist/assets/foo.869aea0d.js" />',
            ],
        );

        eq(
            explode("\n", $tags->css),
            [
                // CSS imported by main entry point script:
                '<link rel="stylesheet" href="/dist/assets/main.b82dbe22.css" />',
                // CSS shared by both entry points, no duplicates:
                '<link rel="stylesheet" href="/dist/assets/shared.a834bfc3.css" />',
            ]
        );

        eq(
            explode("\n", $tags->js),
            [
                // Main entry point script:
                '<script type="module" src="/dist/assets/main.4889e940.js"></script>',
                // TODO open an issue to verify Vite's documentation:
                //      https://vitejs.dev/guide/backend-integration
                //      In their example, a `<script>` tag is generated for the `views/foo.js` script,
                //      but this seems wrong, as the script is already preloaded as a module - in their example,
                //      `views/foo.js` is a dynamic entry point, and should be loaded dynamically, not eagerly. (?)
            ]
        );
    }
);

test(
    "can create asset URLs",
    function () {
        $vite = new Manifest(
            dev: false,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/'
        );

        eq(
            $vite->getURL("views/foo.js"),
            "/dist/assets/foo.869aea0d.js",
            "production mode: generates production URL according to manifest"
        );

        $vite = new Manifest(
            dev: true,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/'
        );

        eq(
            $vite->getURL("views/foo.js"),
            "/dist/views/foo.js",
            "dev mode: generates URL for Vite's dev server"
        );
    }
);

exit(run());
