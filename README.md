# RSS-Librarian

RSS-Librarian is a read-it-later service for RSS purists. You can store articles from the web in your own *personal RSS/Atom feed* and use your favorite feed-reader software to read your stored articles later. RSS-Librarian uses no database and works without accounts.

Main instance hosted here:
https://www.rsslibrarian.ch/librarian.php

The project was born out of [a personal frustration of mine](https://github.com/Ranchero-Software/NetNewsWire/issues/3023): My workflow for reading anything I am interested in is by adding a star in [my feed-reader](https://netnewswire.com/) to an article, which necessitates that anything I want to read is somehow a RSS/Atom subscription, and that isn't true for single articles I get sent by someone.

RSS-Librarian solves this issue with a self-hostable PHP file by extracting content from articles using [a readability service](https://www.fivefilters.org/) and directly writing them as new entries into a *personal feed*, without requiring special libraries, a database or user accounts.

Consider RSS-Librarian if you want to:
* Store a collection of articles in a feed-reader application
* Avoid third-party read-it-later services such as [Wallabag](https://wallabag.org/), [Instapaper](https://www.instapaper.com), [Pocket](https://getpocket.com) etc.
* Minimize the amount of necessary apps for reading articles
* Get rid of accounts and not sign up to anything
* Read articles (offline) in a readable format, but not categorize or store them indefinitely
* Synchronize stored articles to multiple devices
* Optionally be able to self-host the whole architecture
