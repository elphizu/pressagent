=== PressAgent ===
Contributors: suryakantupadhyay
Tags: ai, agents, discovery, llms, rest-api
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish public discovery for AI agents: manifest, llms.txt, and public Abilities, with no custom auth or tables.

== Description ==

PressAgent publishes public discovery files for WordPress sites:

* `/.well-known/wordpress-agent.json`
* `/llms.txt`
* `/wp-json/pressagent/v1/manifest`
* Public abilities registered through the WordPress Abilities API
* Optional integration endpoints, including MCP endpoints contributed by adapter plugins

PressAgent does not provide an MCP server. It automatically detects the official WordPress MCP Adapter and advertises its enabled default HTTP server. Other adapters and custom-server deployments can contribute or replace an endpoint through the `pressagent_discovery` filter.

PressAgent deliberately does not include custom authentication, custom database tables, or AI SDK dependencies. Anonymous requests receive public discovery metadata only. Authenticated external clients reuse WordPress Application Passwords over HTTPS.

The generated `llms.txt` follows the llmstxt.org v2 structure: an H1 title, an optional blockquote summary, and Markdown file lists grouped under H2 headings.

== Installation ==

1. Upload the `pressagent` directory to `/wp-content/plugins/`.
2. Activate PressAgent through the Plugins screen.
3. Open Settings > PressAgent to inspect discovery and authentication diagnostics.
4. Open Tools > Site Health to review PressAgent health checks and debug information.

No permalink save is required after activation. When a server cannot route the canonical `/llms.txt` path through WordPress, `/?pressagent_llms=1` is the query-string fallback.

== Authentication and authorization ==

PressAgent does not introduce an authentication scheme.

* You can read the public manifest, LLMS document, PressAgent REST discovery, and public ability metadata without logging in.
* The WordPress Abilities API collection requires an authenticated user with the `read` capability, even when an individual ability has `meta.public` set to `true`.
* External authenticated clients should use WordPress Application Passwords over HTTPS.
* Cookie-authenticated WordPress clients continue to use the normal REST nonce behavior.
* Authentication identifies a user; it does not authorize an operation.
* Every sensitive, private, or write ability must provide an explicit `permission_callback` that checks an appropriate WordPress capability with `current_user_can()`.
* A nonce is not an authorization check.

PressAgent includes an ability in anonymous discovery only when its `meta.public` value is strictly `true`. Categories are limited to categories referenced by those public abilities.

The manifest includes an `authentication` object keyed by discovery endpoint. Its `abilities` entry records the required `read` capability and the WordPress authentication methods available on the site. Application Passwords are advertised only when WordPress reports them as available.

== Manifest contract ==

The bundled `docs/manifest-schema.json` file is the canonical JSON Schema. The REST route exposes the same contract through `OPTIONS /wp-json/pressagent/v1/manifest`.

The six required properties are `version`, `site`, `discovery`, `authentication`, `abilities`, and `categories`. The `pressagent_manifest` filter may modify their values using the documented types, but it cannot remove them or change their types. Additional top-level properties are allowed.

The version uses `major.minor`. Additive optional fields increment the minor version. Removing or redefining required fields increments the major version.

Synthetic ability entries are supported through `pressagent_manifest_abilities`. They must use the same label, description, and category shape as registered abilities and are treated as explicitly public. Their category should also be supplied through `pressagent_manifest_categories`.

Manifest and llms.txt output is cached as site-public, user-independent data. The cache lifetime is configurable from Settings > PressAgent. Site identity and plugin setting changes invalidate both artifacts. Extensions whose filtered discovery changes at runtime must call `do_action( 'pressagent_flush_cache' )`.

PressAgent relies on WordPress REST CORS handling and does not emit custom CORS headers.

== MCP integration ==

MCP transport belongs to a separate adapter plugin. When the official `WP\MCP\Core\McpAdapter` runtime is available and its default server is enabled, PressAgent automatically advertises `/wp-json/mcp/mcp-adapter-default-server`.

PressAgent abilities set `meta.mcp.public=true` and `meta.mcp.type=tool` so the official adapter lists them as tools. The adapter's default server exposes them through its discover, inspect, and execute gateway tools.

A custom adapter or custom-server deployment can override the endpoint as follows:

`add_filter( 'pressagent_discovery', function ( $discovery ) {`
`    $discovery['mcp'] = 'https://example.com/wp-json/mcp/v1';`
`    return $discovery;`
`} );`

The endpoint must be an absolute HTTP or HTTPS URL. PressAgent omits invalid, relative, and non-HTTP(S) values. If no valid `mcp` entry exists, PressAgent does not advertise MCP.

When a valid MCP endpoint is advertised, PressAgent also includes it in the generated `llms.txt` summary.

MCP exposure of individual abilities remains the adapter's projection decision. Ability permission callbacks remain authoritative regardless of transport.

== Extension API ==

= pressagent_discovery =

Filters the normalized endpoint map before it is included in the manifest or returned by the `pressagent/get-discovery` ability.

The value is an associative array of endpoint keys to absolute HTTP(S) URLs. Extensions may add, replace, or remove entries. If multiple callbacks write the same key, the callback running last wins according to normal WordPress filter priority and registration order.

Built-in keys are listed below. The `rest` and `pressagent` keys are required for complete PressAgent discovery; Site Health reports their removal as a critical issue.

* `rest`: WordPress REST API root.
* `pressagent`: PressAgent manifest REST endpoint.
* `abilities`: WordPress Abilities API collection endpoint, when available.
* `mcp`: Optional; contributed by an MCP adapter.

= pressagent_manifest =

Filters the complete public manifest after PressAgent builds it. This is a trusted-code extension point. Extensions are responsible for preserving the documented public-exposure boundary.

= pressagent_authentication =

Filters authentication metadata keyed by the same endpoint names used by `pressagent_discovery`. Extensions that add an authenticated endpoint should add its requirements through this filter. The second filter argument contains the normalized discovery map.

PressAgent supplies WordPress cookie and Application Password metadata only for the official same-site MCP Adapter endpoint. Custom MCP endpoints receive no assumed authentication metadata; their integration must provide the correct requirements through this filter.

= pressagent_llms =

Filters the complete multi-line `/llms.txt` document before it is served.

= pressagent_llms_enabled =

Filters whether PressAgent registers and serves its `/llms.txt` endpoint. Return `false` to disable ownership of the WordPress route.

= pressagent_manifest_abilities =

Filters the public ability metadata projection after PressAgent removes abilities that are not explicitly public. This is a trusted-code extension point and must not be used to expose private ability metadata.

= pressagent_manifest_categories =

Filters category metadata after PressAgent limits the collection to categories referenced by public abilities.

== Frequently Asked Questions ==

= Does PressAgent send site content to an AI provider? =

No. PressAgent publishes discovery metadata locally and has no AI SDK or model-provider dependency.

= Does PressAgent create database tables? =

No. The current plugin has no custom persistence layer.

= Does PressAgent provide an MCP server? =

No. Install the official WordPress MCP Adapter or another compatible adapter. PressAgent detects the official default server automatically and supports custom endpoints through `pressagent_discovery`.

= How should an external agent authenticate? =

Use WordPress Application Passwords over HTTPS. The requested REST endpoint or ability must still authorize the authenticated user with an appropriate capability check.

== Changelog ==

= 0.1.0 =

* Initial agent manifest, llms.txt, REST, Abilities API, diagnostics, and extension API implementation.
