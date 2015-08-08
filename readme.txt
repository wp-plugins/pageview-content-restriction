=== Pageview content restriction ===
Contributors: veganist
Tags: content restriction, restrict, access, restricted access, member only, subscriber only, registration, pageviews
Requires at least: 3.8
Tested up to: 4.2.4
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Restricts access to your Wordpress site after a certain number of pageviews.

== Description ==

This WordPress plugin restricts non-authenticated users to a number of pages to view. You can define how many pages an unauthenticated user may view. If the limit is reached, the user is redirected to the WordPress login page (default) or a page of your choice.

This plugin uses cookies, but not only. People can not just empty their cookies and start watching your page again. In this plugin i use a technique in which I combine a users’ IP address  with the user agent of this particular user, and create a more or less unique hash out of these two informations. This hash is stored as a file in the wp-content/uploads directory.

A site administrator can choose to reinitialize all sessions and empty the hash files.

Furthermore, robots might be blocked. However, important bots like GoogleBot, BingBot and YahooBot are excluded from the restriction.

This technique might be problematic for users using Tor / TorBrowserBundle as lots of people share a same originating IP address and also the same user agent (check here https://panopticlick.eff.org/). I will certainly test this aspect a little better in a later version of this plugin.

== Installation ==

1. Upload `very-basic-content-restriction` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin in Options => Pageview Content Restriction.

== Changelog ==

= 1.0 =
* Initial release
