=== PressAgent ===
Contributors: suryakantupadhyay
Tags: ai, agents, discovery, llms, rest-api
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish public discovery information for AI agents, including a manifest, llms.txt, and WordPress abilities.

== Description ==

PressAgent makes WordPress sites easier for AI agents and other tools to discover.

It provides:

* `/.well-known/wordpress-agent.json`
* `/llms.txt`
* `/wp-json/pressagent/v1/manifest`
* Public abilities registered with the WordPress Abilities API
* Optional discovery of MCP endpoints provided by other plugins

PressAgent does not provide an MCP server, custom authentication, database tables, or AI SDK integrations.

When the official WordPress MCP Adapter is active, PressAgent can automatically advertise its default HTTP endpoint. Other integrations can provide their own endpoint using the `pressagent_discovery` filter.

Authenticated clients use normal WordPress authentication, including Application Passwords over HTTPS.

== Installation ==

1. Upload the `pressagent` directory to `/wp-content/plugins/`.
2. Activate PressAgent through the Plugins screen.
3. Go to Settings > PressAgent to review discovery settings and diagnostics.
4. Use Tools > Site Health for additional PressAgent checks.

No permalink refresh is required.

If `/llms.txt` cannot be routed through WordPress, `/?pressagent_llms=1` is available as a fallback.

== Authentication ==

PressAgent does not add a new authentication system.

Public discovery information can be accessed without authentication.

Authenticated external clients should use WordPress Application Passwords over HTTPS. Cookie-authenticated requests continue to use normal WordPress REST API nonce handling.

Abilities that expose private data or perform sensitive operations must use an appropriate `permission_callback` and WordPress capability checks.

Only abilities explicitly marked with `meta.public` set to `true` are included in public discovery.

== MCP integration ==

PressAgent does not provide an MCP server.

When the official WordPress MCP Adapter is available and its default server is enabled, PressAgent advertises its HTTP endpoint automatically.

Other MCP integrations can provide an endpoint using the `pressagent_discovery` filter:

`add_filter( 'pressagent_discovery', function ( $discovery ) {`
`    $discovery['mcp'] = 'https://example.com/wp-json/mcp/v1';`
`    return $discovery;`
`} );`

Only absolute HTTP or HTTPS URLs are accepted.

Ability permissions continue to be enforced by WordPress regardless of how an ability is exposed.

== Extension API ==

PressAgent provides filters for plugins that need to extend its discovery output.

= pressagent_discovery =

Filters the discovery endpoint map.

Built-in endpoints may include:

* `rest` - WordPress REST API
* `pressagent` - PressAgent manifest
* `abilities` - WordPress Abilities API
* `mcp` - MCP endpoint, when available

= pressagent_manifest =

Filters the generated public manifest.

= pressagent_authentication =

Filters authentication information associated with discovery endpoints.

= pressagent_llms =

Filters the generated `/llms.txt` document.

= pressagent_llms_enabled =

Controls whether PressAgent serves `/llms.txt`.

= pressagent_manifest_abilities =

Filters abilities included in the public manifest.

= pressagent_manifest_categories =

Filters ability categories included in the public manifest.

== Frequently Asked Questions ==

= Does PressAgent send content to an AI provider? =

No. PressAgent only publishes discovery information from the WordPress site. It does not connect to an AI or model provider.

= Does PressAgent create database tables? =

No.

= Does PressAgent provide an MCP server? =

No. An MCP adapter, such as the official WordPress MCP Adapter, must provide the server.

= How should external agents authenticate? =

Use WordPress Application Passwords over HTTPS when authentication is required.

Normal WordPress capability checks still determine what an authenticated user is allowed to do.

= What is llms.txt? =

PressAgent generates an `/llms.txt` file containing useful public links and information about the site in a simple Markdown format.

== Changelog ==

= 0.1.0 =

* Initial release.
* Added agent manifest and `/llms.txt` discovery.
* Added REST and WordPress Abilities API integration.
* Added MCP Adapter discovery.
* Added settings and Site Health diagnostics.
* Added extension filters.
