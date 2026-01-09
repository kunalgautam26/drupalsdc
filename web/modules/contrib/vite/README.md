# Vite

Vite [backend integration](https://vitejs.dev/guide/backend-integration) for Drupal asset libraries.

* To submit bug reports and feature suggestions, or track changes:
   <https://www.drupal.org/project/issues/vite>

## Requirements

This module requires no modules outside of Drupal core.

It's designed to work with [Vite](https://vitejs.dev) 5 or newer.

## Installation

* Install as you would normally install a contributed Drupal module. Visit
   <https://www.drupal.org/node/1897420> for further information.

## Usage

* Install the module.
* Configure vite to:
  * generate `manifest.json`
     <https://vitejs.dev/config/build-options.html#build-manifest>
  * depending on your setup you may also need to configure dev server host
     and/or port <https://vitejs.dev/config/server-options.html#server-options>

```diff
 import { defineConfig } from "vite"

 export default defineConfig({
   build: {
+    manifest: true,
     rollupOptions: {
       input: [
         [...]
       ],
     },
   },
   [...]
 })
```

* Enable vite support for asset libraries you would like to use it with:
  * To enable for all libraries/components of the theme/module, in its
    `.info.yml` file, add `vite:` section with one or both of:
    * `enableInAllLibraries: true` to enable vite support for all libraries,
    * `enableInAllComponents: true` to enable vite support for all components.
  * To enable for single library, in the `<theme|module>.libraries.yml`, for
    the library you would like to use assets build by vite, add property
    `vite: true`.
* Replace paths to assets in library definition with paths to assets sources:

```diff
 # theme.info.yml
 name: My theme
 type: theme
 [...]
+ vite:
+   enableInAllLibraries: true
```

```diff
 # theme.libraries.yml
 global-styling:
   js:
-    dist/script.js: {}
+    src/script.ts: {}
   css:
     component:
-      dist/style.css: {}
+      src/scss/style.scss: {}
   dependencies:
     - core/drupalSettings
```

* The module will dynamically rewrite assets paths to dist and include
   their dependencies defined in manifest.json.

* To use hot module reload during development, just start vite dev server.
   If the server is accessible on the localhost under default port (5173)
   the module will automatically start using it instead of dist assets
   from manifest.json as soon as you clear the cache (library definitions
   are cached by default).

### SDC integration

To use Vite for processing [SDC](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components)
asset libraries:

* enable vite support for components in the theme/module by setting `vite.enableInAllComponents: true`
  in its `.info.yml` file
* add source assets in the components folders to entrypoints in the vite config of the theme/module components are in
* override component library to include paths to source assets

For example:

* in `<theme>.info.yml`:

```diff
 # theme.info.yml
 name: My theme
 type: theme
 [...]
+ vite:
+   enableInAllComponents: true
```

* in `<theme>/vite.config.ts`:

```diff
 import { defineConfig } from "vite"
 import multiInput from "rollup-plugin-multi-input"

 export default defineConfig({
   plugins: [multiInput.default()],
   build: {
     manifest: true,
     rollupOptions: {
       input: [
         [...]
+        "components/**/*.pcss.css",
+        "components/**/*.ts",
       ],
     },
   },
   [...]
 })
```

* in `<theme>/components/button/button.component.yml`:

```diff
name: Button
+libraryOverrides:
+  css:
+    component:
+      button.pcss.css: {}
+  js:
+    button.ts: {}
props:
  [...]
```

### Get a chunk path outside libraries

It is possible to get a path to a chunk programmatically, outside the library definition.
This can be useful for example when you need to get the path to an image or other
asset that is not part of a library definition, but is processed by Vite.
This requires the `vite` service to be injected and the `getChunk` method to be called
with three arguments; the name of the extension/theme that registered a library, the
name of the registered library to retrieve a chunk from, and the path to the chunk.
This path should be to the source file, just as how you would use it in the library definition.

```php
$logo = \Drupal::service('vite.vite')->getChunk('frontend', 'global', 'assets/logo.svg');
```

#### Usage in Twig

There's also a Twig function available to get a path to a chunk. This function accepts
the same arguments as the PHP method above.

```twig
<img src="{{ vite_get_chunk_path('frontend', 'global', 'assets/logo.svg') }}" />
```

## Configuration

In library definition instead of only enabling vite support by setting
`vite: true` theres also an option to provide some custom configurations.

```yaml
vite:
  # Determines if vite module should be enabled for this library.
  enabled: true
  # Path to vite root directory, by default theme/module directory.
  # If starts with `/` path is resolved relative to the drupal root
  # otherwise relative to the theme/module directory.
  # Setting it for example to `/..` would mean that the vite root directory
  # in project root outside of drupal app root.
  viteRoot: '/..'
  # Vite dist dir path relative to vite root, by default `dist`.
  # Should be set to the same value as in vite config.
  distDir: 'web/libraries/dist'
  # If set is used as base url for dist assets paths from manifest.json instead
  # of dynamically resolved path relative to drupal web root.
  baseUrl: 'https://cdn.example.com/dist/'
  # Vite dev server url, by default http://localhost:5173.
  devServerUrl: 'http://localhost:9999'
  # Library dependencies used in dev mode only.
  devDependencies:
    - mymodule/reactapp.devmode
```

These settings can also be overwritten in site settings.php:

```php
$settings['vite'] = [
  // By default ('auto') the module will automatically check if vite dev server
  // is running and if so, use it. Settings this to false will make sure that
  // vite dev server will not be used, which is recommended setting for
  // production environments.
  'useDevServer' => 'auto',
  // Global overrides.
  /* Make note that these are global so they will take effect for all drupal
   * asset libraries, so setting enabled => TRUE here is not really recommended.
   * Probably the only useful thing to set here would be devServerUrl to globally
   * override the default one.
   */
  'enabled' => TRUE,
  'viteRoot' => '/..',
  'distDir' => 'web/libraries/dist',
  'baseUrl' => '/some/custom/base/url/used/in/production/for/dist/assets/',
  'devServerUrl' => 'http://localhost:9999',
  'devDependencies' => [
    'mymodule/override.devmode'
  ]
  'overrides' => [
    // Per module/theme overrides.
    '<module|theme>' => [
      // ... settings like the global ones
    ]
    // Per library overrides.>
    '<module|theme>/<library>' => [
      // ... settings like the global ones
    ]
  ],
]

```

In `<theme/module>.libraries.yml` there is also an option to disable rewriting
of specific asset, to do that you need to set `vite: false` for specific asset,
for example:

```diff
 global-styling:
   vite: true
   js:
     src/script.ts: {}
-    some/static/script.js: {}
+    some/static/script.js: {vite: false}
   css:
     component:
       src/scss/style.scss: {}
     dependencies:
       - core/drupalSettings
```

If your vite loaded app needs to declare dependencies that are only used while
in dev mode you can use the `devDependencies` option. This is particularly
useful for React apps which need the `@react-refresh` preamble injected before
the app is loaded.

An example `@react-refresh` preamble loaded with `devDependencies` for React
might look like this:

```js
/* /modules/custom/mymodule/js/viteDevMode.js */

const thisScript = document.currentScript;
const devServerUrl = thisScript.dataset.viteDevServer;

import(`${devServerUrl}/@react-refresh`)
  .then((RefreshRuntime) => {
    const injectIntoGlobalHook = RefreshRuntime.default.injectIntoGlobalHook;
    injectIntoGlobalHook(window);
    window.$RefreshReg$ = () => {};
    window.$RefreashSig$ = () => (type) => type;
    window.__vite_plugin_react_preamble_installed__ = true;
  })
  .catch(() => {
    console.log('Could not load RefreshRuntime from the vite dev server');
  });
```

And loaded like this:

```yaml
# /modules/custom/mymodule/mymodule.libraries.yml

reactapp.devmode:
  js:
    js/viteDevMode.js: {}

reactapp:
  vite:
    distDir: app/dist
    devServerUrl: 'http://host.docker.internal:5173/modules/custom/mymodule/app'
    devDependencies:
      - mymodule/reactapp.devmode # This loads the viteDevMode.js when in dev mode only
  js:
    src/main.tsx: {}
  dependencies:
    - core/drupal
    - core/drupalSettings
    - locale/translations
```

## Drupal Translations

### 1. Handling Translatable Strings

Drupal dynamically extracts translatable strings from JavaScript files attached to asset libraries,
during the js_alter hook execution. More precisely, Drupal scans JavaScript files using regular expressions to find
occurrences of `Drupal.t()` and `Drupal.formatPlural()` and then extracts the string arguments inside these functions,
identifying them as translatable text.

Once the translatable strings are identified, Drupal looks up the corresponding translations for the detected strings
based on the active language, and then generates a JavaScript snippet containing the translations and injects
it into the page.

The extracted translations are stored in the global `window.drupalTranslations` object, an object that acts as a
lookup table that the `Drupal.t()` function references when performing translations.

### 2. Issues with Minification

Drupal relies on exact matches of `Drupal.t()` in the source code to extract translatable strings.
However, modern JavaScript minifiers (like Vite) may rename function parameters or alter code structure,
breaking this detection process.

For example:

```js
(function (Drupal) {
  console.log(Drupal.t("Hello"));
})(Drupal);
```

After minification:

```js
(function(i){console.log(i.t("Hello"))})(Drupal);
```

Since `Drupal.t` is now `i.t`, Drupal's scanner fails to detect "Hello" as translatable.

### 3. Workarounds for Minification Issues

To ensure Drupal can detect `Drupal.t()`:

#### 3.1 Use the global Drupal object directly instead of passing it as a function argument

```js
(function () {
    console.log(Drupal.t("Hello"));
})();
```

This way, even after minification, `Drupal.t("Hello")` remains unchanged and can be properly detected.

#### 3.2 Use vite-plugin-preserve-drupal-t Vite plugin

This plugin, is designed to handle Drupal's localization functions, such as `Drupal.t` and `Drupal.formatPlural`,
during the build process. It ensures that these functions are preserved in the final output by temporarily
replacing them with placeholders during the transformation phase and restoring them in the bundling phase.
The plugin takes an array of function names (defaulting to `['t', 'formatPlural']`) and dynamically generates
regular expressions to identify and replace instances of these in the source code. For example, it replaces
`Drupal.t()` with a placeholder like `__DRUPAL_T__()` to prevent them from being altered or optimized away
by the build process.

In the bundle generation phase, the plugin iterates through the generated bundle files and restores the original
function calls by replacing the placeholders back with their original forms (e.g., `__DRUPAL_T__()` back to
`Drupal.t()`). This ensures that the localization functions remain intact and functional in the final output.
By dynamically generating the regex patterns and replacements based on the provided function names, the plugin is
flexible and can be extended to handle additional Drupal localization functions if needed.

Basic usage in a Vite config file:

```js
import { defineConfig } from "vite"
import preserveDrupalT from "vite-plugin-preserve-drupal-t"

export default defineConfig({
  plugins: [
    preserveDrupalT(),
  ]
})
```

NPM Plugin page: <https://www.npmjs.com/package/vite-plugin-preserve-drupal-t>
