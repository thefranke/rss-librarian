# RSS-Librarian

RSS-Librarian is a read-it-later service for RSS purists. Instead of creating another service such as Pocket or Instapaper (together with a separate app), RSS-Librarian will let you add articles you want to read to a *personal RSS feed* and they will show up in your RSS reader, where you can use the local functionality (for instance starring new articles) to mark them for later reading. RSS-Librarian uses no database and works without accounts.

Sample instance hosted here:
https://alternator.hstn.me/librarian.php
(**important note**: if your reader cannot deal with self-signed certificates, use http instead!)

The project was born out of [a personal frustration of mine](https://github.com/Ranchero-Software/NetNewsWire/issues/3023): My workflow for reading anything I am interested in is by adding a star in my RSS reader to an article, which necessitates that anything I want to read is somehow a subscription I can add to my RSS reader, and that isn't true for single articles I get sent by someone.

RSS-Librarian solves this issue with a self-hostable PHP file by extracting content from articles using [a readability service](https://www.fivefilters.org/) and directly writing them as new entries into a *personal RSS feed*, without requiring special libraries, a database or user accounts.

Consider RSS-Librarian if you want to:
* Store single articles in a RSS reader application
* Avoid third-party read-it-later services such as [Pocket](https://getpocket.com), [Instapaper](https://www.instapaper.com) or [Wallabag](https://wallabag.org/)
* Minimize the amount of necessary apps for reading articles
* Get rid of accounts and not sign up to anything
* Read articles (offline) in a readable format, but not categorize or store them indefinitely
* Synchronize stored articles to multiple devices
* Optionally be able to self-host the whole architecture
