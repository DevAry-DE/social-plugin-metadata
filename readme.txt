=== Social Plugin - Metadata ===
Contributors: ole1986
Tags: facebook, show, page info, meta data
Requires at least: 5.0
Tested up to: 5.7
Requires PHP: 7.0
Stable tag: 1.0.3
License: GPLv3

Display meta information from the social network "Facebook" containing Business Hours, About details, Last public post, etc...

== Description ==

Display meta information from the social network "Facebook" using either a widget or as shortcode.
Currently supported meta information which can be gathered are:

* Business hours
* Page about text
* Last posted entry (incl. text, link and date)

Check out the Installation instruction for further details

== Installation ==

Add it through wordpress or unpack the downloaded zip file into your wp-content/plugins directory

**Quick Guide**

To sychronize and output meta information (E.g. Business hours, About Us, last posts) from facebook pages.

1. Use the button Login and Sync (left side) to connect your facebook account with the Cloud 86 / Link Page application
2. Once successfully logged into your facebook account, choose the pages you wish to output metadata for
3. Is your account properly connected and the syncronization completed, you can switch to the Appearance -> Widget page
4. To display the content on your front page, move the widget Facebook page info Widget into a desired widget area
5. Finally save the widget settings and check the output on the front page

**Shortcodes**

If you prefer to use Shortcodes, the below options are available

[fb-pageinfo-businesshours page_id="..." empty_message=""]
[fb-pageinfo-about page_id="..." empty_message=""]
[fb-pageinfo-lastpost page_id="..." limit="..." max_age="..." empty_message=""]

== Screenshots ==

1. The settings page
2. The widget located in a side bar
3. Output of the widget configured to display business hours

== Changelog ==

Changelog can be found on [Github project page](https://github.com/Cloud-86/social-plugin-metadata/releases) 