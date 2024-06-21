=== Social Plugin - Metadata ===
Contributors: ole1986
Tags: facebook, show, page info, meta data
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 1.1.2
License: GPLv3

Display meta information from the social network "Facebook" containing Business Hours, About details, Last public post, etc...

== Description ==

Display meta information from the social network "Facebook" using either a widget or shortcode.
Currently supported meta information which can be gathered are:

* Business hours
* Page about text
* Last posted entry (incl. text, link and date)
* Show events

Check out the Installation instruction for further details

== Installation ==

Add it through wordpress or unpack the downloaded zip file into your wp-content/plugins directory

**Quick Guide**

To sychronize and output meta information (E.g. Business hours, About Us, last posts) from facebook pages, follow the below steps:

1. Register as Facebook Developer and create a new [Facebook App](https://developers.facebook.com/apps/)
2. Fill in the Facebook App ID and App secret from the app you just created into the Social Plugin (Menu "Tool" -> "Social Plugin - Metadata")
3. Use the "Login and Sync" button to connect your facebook account with your Facebook App
4. Switch to the Appearance -> Widget page once successfully logged in and pick the "Social Plugin - Metadata"
5. Setup the widget for the page and content you want to display on frontend

**Shortcodes**

If you prefer to use Shortcodes, the below options are available

[social-businesshours page_id="..." empty_message=""]
[social-about page_id="..." empty_message=""]
[social-lastpost page_id="..." limit="..." max_age="..." empty_message=""]

== Screenshots ==

1. The settings page
2. The widget located in a side bar
3. Output of the widget configured to display business hours

== Changelog ==

Changelog can be found on [Github project page](https://github.com/Cloud-86/social-plugin-metadata/releases) 