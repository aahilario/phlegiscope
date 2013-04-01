phlegiscope
===========

A politically aware, reform-oriented, literate electorate can make good
use of open sources of legislation-related information.  Legiscope is
an attempt to consolidate information about legislation - especially
legislative work-in-progress - from all relevant national sources. The
key goals of this project are:

1. To enable search engine content indexing of ALL legislative
documents - including ones that are currently wrapped in proprietary
file container formats (spec. Adobe PDFs), or which are contained in
ill-structured websites - by automatically fetching, converting, and
storing these in vendor-neutral form, for later use in CMSs.

2. Provide a mechanism to dynamically scrape content from various open
databases (Senate, Congress, and department websites, down to province
and city level), and provide a unified database schema that relates
policies, authorship, and changes in this legislation.

3. Enable mechanisms for push notification whenever the state of a
piece of information in the database has changed.

4. To enable end users of the legislation repository to attach
arbitrary metadata tags to any piece of legislation, and define
arbitrary graph edges between any two (or more) pieces of information
in the repository. Think, for example, of relationships between
committee members (when were these persons members of a given
committee?  What items of legislation have reached Senate committees
that were driven by party list sponsorship?)  Or perhaps attaching
geolocation tags to specific House Bills, identifying the locations of
new schools and roads, so that relationships can be made between
legislation and other geotagged databases - for example, databases of
average household income by administrative area.

This initial revision implements basic site scraping capabilities.  The
framework is pretty rough-hewn, and is intended to evolve with a plugin
architecture, to allow site-specific parsers, data pull protocols, etc.

I'm at the point where other folks can still reverse bad architectural
decisions, and make improvements to the code, I think. I'll also make
sure to broadcast an announcement when the code can be deemed ready for
contributors to make changes - there are some code messes in here that
impair readability, and I'll fix those soon.
