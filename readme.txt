=== Plugin Name ===
Contributors: antoniohilario
Donate link: http://avahilarion.net 
Tags: phlegiscope, legislative, monitoring, crowdsource
Requires at least: 3.3.1 
Tested up to: 3.3.1 

This plugin enables a Wordpress installation to act as a PHLegiscope aggregator. 

== Description ==

PHlegiscope enables Web users to build a crowdsourced, citizen-curated database of laws, and for them (and others) to see changes in the content of draft bills over time.  PHlegiscope allows these users to attach tags to these texts that are of significance to them, as well as links to news items on the Web which relate to the draft law.  This information is aggregated by a Wordpress blog server of their choice - say, one that is run by a class, a media outfit, a campaign organization, or a research group.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

= What does this plugin do? =

Whenever a Phlegiscope user browses the Senate and Congress websites at

    http://senate.gov.ph, and
    http://www.congress.gov.ph,

a browser extension detects this, and enables the user to:

    1. Fetch a document, say, a PDF, posted on a page there, and forward it to a Wordpress site;
    1. Automatically record the title, scope (national/local), and status of a piece of pending legislation at a given date,
    1. Attach links to Internet-published news items that are related to the law,
    1. Attach tags to the item of legislation, and
    1. Notify the user whether or not this piece of legislation is being watched by other users of Phlegiscope.

These pieces of information are collected by volunteers over a period of time, and are forwarded to a Wordpress blog of their choice, which in turn aggregates this information.  The Wordpress blog (running the Phlegiscope plugin) is able to display a "catalog" of draft bills being watched by volunteers who submit information to it.  This catalog contains tags, links, and, most importantly, time stamps that indicate when the item was seen, as well as other time-related information. This can include

    1. differences between two draft versions published at different times
    1. schedules of hearings
    1. dates of effectivity
    1. the date when an issue related to the draft law was first recorded
    1. heads of committees that are currently overseeing preparation of the draft law

It also allows Wordpress articles to be attached to particular Phlegiscope catalog entries, and for an RSS feed to be generated from the Phlegiscope database (or parts of it).

The Phlegiscope code is work in progress, and will be published as soon as the bare-bones components are published to Github, so that others can also begin to hack at the code and beat it into working shape.


== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the directory of the stable readme.txt, so in this case, `/tags/4.3/screenshot-1.png` (or jpg, jpeg, gif)
2. This is the second screen shot

== Changelog ==

= 1.0 =
1. First stable release able to display catalog of tracked targets. 
1. Full subscriber and client login class functionality 

= 0.1 =
* Initial plugin feature set implementation

== Upgrade Notice ==

= 1.0 =
This is the first release that has been vetted for public release.

= 0.1 =
This version implements the first action hooks that extend core PHP post creation functionality.

== Arbitrary section ==


