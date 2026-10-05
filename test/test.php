<?php

use mindplay\vite\Manifest;

use function mindplay\testies\{ configure, eq, expect, run, test };

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
        $vite->preloadStyles();

        $tags = $vite->createTags("main.js", "consent-banner.js", "public/scss/themes/admin/admin.scss", "public/css/plus.css", "public/img/favicon.ico");

        eq(
            explode("\n", $tags->preload),
            [
                // Preload for main entry point script:
                '<link rel="modulepreload" href="/dist/assets/main.4889e940.js" />',
                '<link rel="preload" as="image" type="image/png" href="/dist/assets/asset.0ab0f9cd.png" />',
                // Preload module shared by both entry points, no duplicates:
                '<link rel="modulepreload" href="/dist/assets/shared.83069a53.js" />',
                // Preload `views/foo.js` entry point script:
                '<link rel="modulepreload" href="/dist/assets/consent-banner.0e3b3b7b.js" />',
                '<link rel="preload" as="style" type="text/css" href="/dist/assets/admin-B8_LVhy3.css" />',
                '<link rel="preload" as="style" type="text/css" href="/dist/assets/plus-DwWFnKP0.css" />',
                '<link rel="preload" as="image" type="image/x-icon" href="/dist/assets/favicon-zR_S-YMI.ico" />',
            ],
        );

        eq(
            explode("\n", $tags->css),
            [
                // CSS imported by main entry point script:
                '<link rel="stylesheet" href="/dist/assets/main.b82dbe22.css" />',
                // CSS shared by both entry points, no duplicates:
                '<link rel="stylesheet" href="/dist/assets/shared.a834bfc3.css" />',
                // CSS imported by the consent-banner entry point script:
                '<link rel="stylesheet" href="/dist/assets/consent-banner.8ba40300.css" />',
                '<link rel="stylesheet" href="/dist/assets/admin-B8_LVhy3.css" />',
                '<link rel="stylesheet" href="/dist/assets/plus-DwWFnKP0.css" />',
            ]
        );

        eq(
            explode("\n", $tags->js),
            [
                // Main entry point script:
                '<script type="module" src="/dist/assets/main.4889e940.js"></script>',
                // Consent-banner entry point script:
                '<script type="module" src="/dist/assets/consent-banner.0e3b3b7b.js"></script>',
            ]
        );
    }
);

test(
    "should throw an exception when an entry is not found, or has the wrong type",
    function () {
        $vite = new Manifest(
            dev: false,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/'
        );

        $vite->preloadImages();

        expect(
            RuntimeException::class,
            "`does-not-exist.js` does... not exist :-)",
            function () use ($vite) {
                $tags = $vite->createTags("main.js", "does-not-exist.js");
            },
            "/Entry not found in manifest\\: does-not-exist\\.js/"
        );

        expect(
            RuntimeException::class,
            "`does-not-exist.js` does... not exist :-)",
            function () use ($vite) {
                $tags = $vite->createTags("views/foo.js");
            },
            "/Chunk is not an entry point\\: views\\/foo\\.js/"
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

test(
    "can create an import map tag in production mode",
    function () {
        $vite = new Manifest(
            dev: false,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/',
            import_map_path: __DIR__.'/fixtures/importmap.json'
        );

        $tags = $vite->createTags("main.js");

        eq(
            $tags->importmap,
            '<script type="importmap">{"imports":{"/dist/assets/shared.stable01.js":"/dist/assets/shared.83069a53.js","/dist/assets/foo.stable02.js":"/dist/assets/foo.869aea0d.js"}}</script>',
            "production mode: emits the import map inline"
        );

        eq(
            explode("\n", $tags->js),
            [
                '<script type="module" src="/dist/assets/main.4889e940.js"></script>',
            ],
            "production mode: other tags are unaffected by the import map"
        );
    }
);

test(
    "creates no import map tag unless configured, or in dev mode",
    function () {
        $vite = new Manifest(
            dev: false,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/'
        );

        eq($vite->createTags("main.js")->importmap, "", "production mode: no import map configured");

        $vite = new Manifest(
            dev: true,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/',
            import_map_path: __DIR__.'/fixtures/does-not-exist.json'
        );

        eq($vite->createTags("main.js")->importmap, "", "dev mode: Vite's dev server needs no import map");
    }
);

test(
    "escapes `<` so the import map can't close its script tag",
    function () {
        $vite = new Manifest(
            dev: false,
            manifest_path: __DIR__.'/fixtures/manifest.json',
            base_path: '/dist/',
            import_map_path: __DIR__.'/fixtures/importmap-markup.json'
        );

        eq(
            $vite->createTags("main.js")->importmap,
            '<script type="importmap">{"imports":{"/dist/a.js":"/dist/\u003C/script>.js"}}</script>',
            "production mode: `<` is unicode-escaped"
        );
    }
);

test(
    "should throw an exception when the import map is not found",
    function () {
        expect(
            RuntimeException::class,
            "a configured import map must exist",
            function () {
                new Manifest(
                    dev: false,
                    manifest_path: __DIR__.'/fixtures/manifest.json',
                    base_path: '/dist/',
                    import_map_path: __DIR__.'/fixtures/does-not-exist.json'
                );
            },
            "/Import map file not found\\: .*does-not-exist\\.json/"
        );
    }
);

exit(run());
